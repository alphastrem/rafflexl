<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Cron {

    /**
     * License heartbeat - runs every 6 hours.
     */
    public function do_heartbeat() {
        $client = TXC_License_Client::instance();
        $client->heartbeat();
    }

    /**
     * Auto draw - triggered at scheduled draw time.
     */
    public function do_auto_draw( $competition_id = null ) {
        if ( ! $competition_id ) {
            // Find competitions due for auto draw
            $this->process_pending_auto_draws();
            return;
        }

        $comp = new TXC_Competition( $competition_id );
        $status = $comp->get_status();

        // Only proceed if competition is in a drawable state
        if ( ! in_array( $status, [ 'live', 'sold_out' ], true ) ) {
            return;
        }

        // Check if must sell out
        if ( $comp->get_must_sell_out() && $comp->get_tickets_remaining() > 0 ) {
            return;
        }

        $engine = new TXC_Draw_Engine();
        $result = $engine->execute( $competition_id, 'auto' );

        if ( is_wp_error( $result ) ) {
            $comp->set_status( 'failed' );
            error_log( sprintf( 'TXC auto draw failed for competition #%d: %s', $competition_id, $result->get_error_message() ) );
        }
    }

    /**
     * Process all competitions that are past draw time and need auto draw.
     * Covers both auto-mode competitions and manual-mode fallback.
     */
    private function process_pending_auto_draws() {
        $now = current_time( 'mysql', true );

        $posts = get_posts( [
            'post_type'      => 'txc_competition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_txc_status',
                    'value'   => [ 'live', 'sold_out' ],
                    'compare' => 'IN',
                ],
                [
                    'key'     => '_txc_draw_date',
                    'value'   => $now,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ],
            ],
        ] );

        foreach ( $posts as $post ) {
            $this->do_auto_draw( $post->ID );
        }
    }

    /**
     * Retry a failed draw after the 2-minute window.
     */
    public function retry_failed_draw( $competition_id ) {
        $comp = new TXC_Competition( $competition_id );

        if ( 'failed' !== $comp->get_status() ) {
            return;
        }

        // Check if there's already a completed draw (someone manually retried)
        global $wpdb;
        $table = $wpdb->prefix . 'txc_draws';
        $completed = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE competition_id = %d AND status = 'completed'",
            $competition_id
        ) );

        if ( $completed > 0 ) {
            return;
        }

        $engine = new TXC_Draw_Engine();
        $result = $engine->execute( $competition_id, 'auto' );

        if ( is_wp_error( $result ) ) {
            // Final failure - leave in failed state for admin intervention
            $comp->set_status( 'failed' );
            error_log( sprintf( 'TXC draw retry failed for competition #%d: %s', $competition_id, $result->get_error_message() ) );
        }
    }

    /**
     * Clean up expired tombstone pages (older than 30 days).
     */
    public function cleanup_tombstones() {
        $tombstones = get_option( 'txc_tombstones', [] );
        $now = time();
        $updated = [];

        foreach ( $tombstones as $id => $data ) {
            $deleted_at = $data['deleted_at'] ?? 0;
            if ( ( $now - $deleted_at ) < ( 30 * DAY_IN_SECONDS ) ) {
                $updated[ $id ] = $data;
            }
        }

        update_option( 'txc_tombstones', $updated );
    }

    /**
     * Schedule an auto draw for a competition.
     */
    public static function schedule_draw( $competition_id, $draw_time ) {
        $timestamp = strtotime( $draw_time );
        if ( $timestamp <= time() ) {
            return;
        }

        $hook = 'txc_auto_draw_event';
        $args = [ $competition_id ];

        // Clear existing schedule for this competition
        wp_clear_scheduled_hook( $hook, $args );

        // Schedule new event
        wp_schedule_single_event( $timestamp, $hook, $args );
    }
}
