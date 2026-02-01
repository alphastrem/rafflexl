<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Activator {

    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::seed_questions();
        self::schedule_cron();

        update_option( 'txc_db_version', TXC_DB_VERSION );
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Tickets
        $table = $wpdb->prefix . 'txc_tickets';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            competition_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            ticket_number INT UNSIGNED NOT NULL,
            is_winner TINYINT(1) NOT NULL DEFAULT 0,
            is_instant_win TINYINT(1) NOT NULL DEFAULT 0,
            instant_win_prize_type VARCHAR(50) DEFAULT NULL,
            instant_win_prize_value DECIMAL(10,2) DEFAULT NULL,
            instant_win_prize_label VARCHAR(255) DEFAULT NULL,
            allocated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY comp_ticket (competition_id, ticket_number),
            KEY comp_user (competition_id, user_id),
            KEY order_id (order_id)
        ) {$charset};";

        // Draws
        $table = $wpdb->prefix . 'txc_draws';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            competition_id BIGINT UNSIGNED NOT NULL,
            winning_ticket_id BIGINT UNSIGNED DEFAULT NULL,
            draw_mode VARCHAR(10) NOT NULL DEFAULT 'manual',
            seed VARCHAR(128) NOT NULL,
            seed_hash VARCHAR(128) NOT NULL,
            rolls LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            forced_redraw TINYINT(1) NOT NULL DEFAULT 0,
            forced_redraw_reason TEXT DEFAULT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY competition_id (competition_id)
        ) {$charset};";

        // Questions
        $table = $wpdb->prefix . 'txc_questions';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_text TEXT NOT NULL,
            option_a VARCHAR(255) NOT NULL,
            option_b VARCHAR(255) NOT NULL,
            option_c VARCHAR(255) NOT NULL,
            option_d VARCHAR(255) NOT NULL,
            correct_option CHAR(1) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'general',
            difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset};";

        // Question attempts
        $table = $wpdb->prefix . 'txc_question_attempts';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            competition_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            selected_option CHAR(1) NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            attempt_number TINYINT UNSIGNED NOT NULL,
            attempted_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_comp (user_id, competition_id)
        ) {$charset};";

        // Instant win map
        $table = $wpdb->prefix . 'txc_instant_win_map';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            competition_id BIGINT UNSIGNED NOT NULL,
            ticket_number INT UNSIGNED NOT NULL,
            prize_type VARCHAR(50) NOT NULL,
            prize_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            prize_label VARCHAR(255) NOT NULL,
            claimed TINYINT(1) NOT NULL DEFAULT 0,
            claimed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            claimed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY comp_ticket (competition_id, ticket_number)
        ) {$charset};";

        // Consent log
        $table = $wpdb->prefix . 'txc_consent_log';
        $sql[] = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            consent_type VARCHAR(50) NOT NULL,
            consented TINYINT(1) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NOT NULL,
            consented_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    private static function create_roles() {
        $shop_manager = get_role( 'shop_manager' );
        if ( $shop_manager ) {
            $shop_manager->add_cap( 'txc_view_competitions' );
            $shop_manager->add_cap( 'txc_manage_draws' );
            $shop_manager->add_cap( 'txc_view_tickets' );
        }

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'txc_view_competitions' );
            $admin->add_cap( 'txc_manage_competitions' );
            $admin->add_cap( 'txc_manage_draws' );
            $admin->add_cap( 'txc_view_tickets' );
            $admin->add_cap( 'txc_manage_settings' );
            $admin->add_cap( 'txc_manage_license' );
            $admin->add_cap( 'txc_delete_competitions' );
            $admin->add_cap( 'txc_manage_questions' );
            $admin->add_cap( 'txc_manage_refunds' );
        }
    }

    private static function seed_questions() {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_questions';

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) {
            return;
        }

        $file = TXC_PLUGIN_DIR . 'data/questions.json';
        if ( ! file_exists( $file ) ) {
            return;
        }

        $json = file_get_contents( $file );
        $questions = json_decode( $json, true );
        if ( ! is_array( $questions ) ) {
            return;
        }

        foreach ( $questions as $q ) {
            $wpdb->insert( $table, [
                'question_text'  => $q['question'],
                'option_a'       => $q['a'],
                'option_b'       => $q['b'],
                'option_c'       => $q['c'],
                'option_d'       => $q['d'],
                'correct_option' => $q['correct'],
                'category'       => $q['category'],
                'difficulty'     => $q['difficulty'],
                'active'         => 1,
            ] );
        }
    }

    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'txc_heartbeat_event' ) ) {
            wp_schedule_event( time(), 'txc_six_hours', 'txc_heartbeat_event' );
        }
        if ( ! wp_next_scheduled( 'txc_tombstone_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'txc_tombstone_cleanup' );
        }
    }
}
