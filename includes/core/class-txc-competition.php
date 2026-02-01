<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Competition {

    private $post_id;

    public function __construct( $post_id ) {
        $this->post_id = (int) $post_id;
    }

    public function get_id() {
        return $this->post_id;
    }

    public function get_title() {
        return get_the_title( $this->post_id );
    }

    public function get_slug() {
        return get_post_field( 'post_name', $this->post_id );
    }

    public function get_seo_description() {
        return get_post_meta( $this->post_id, '_txc_seo_description', true );
    }

    public function get_prize_types() {
        $types = get_post_meta( $this->post_id, '_txc_prize_type', true );
        return is_array( $types ) ? $types : [ $types ];
    }

    public function get_prize_description() {
        return get_post_meta( $this->post_id, '_txc_prize_description', true );
    }

    public function get_price() {
        return (float) get_post_meta( $this->post_id, '_txc_price', true );
    }

    public function get_max_tickets() {
        return (int) get_post_meta( $this->post_id, '_txc_max_tickets', true );
    }

    public function get_max_per_user() {
        return (int) get_post_meta( $this->post_id, '_txc_max_per_user', true );
    }

    public function get_draw_date() {
        return get_post_meta( $this->post_id, '_txc_draw_date', true );
    }

    public function get_sales_stop_time() {
        $draw = $this->get_draw_date();
        if ( empty( $draw ) ) {
            return '';
        }
        return gmdate( 'Y-m-d H:i:s', strtotime( $draw . ' +0000' ) - ( 5 * 60 ) );
    }

    public function get_must_sell_out() {
        return 'yes' === get_post_meta( $this->post_id, '_txc_must_sell_out', true );
    }

    public function get_draw_mode() {
        return get_post_meta( $this->post_id, '_txc_draw_mode', true ) ?: 'manual';
    }

    public function get_status() {
        return get_post_meta( $this->post_id, '_txc_status', true ) ?: 'draft';
    }

    public function set_status( $status ) {
        $valid = [ 'draft', 'live', 'paused', 'sold_out', 'drawing', 'drawn', 'cancelled', 'failed' ];
        if ( in_array( $status, $valid, true ) ) {
            update_post_meta( $this->post_id, '_txc_status', $status );
        }
    }

    public function get_tickets_sold() {
        return (int) get_post_meta( $this->post_id, '_txc_tickets_sold', true );
    }

    public function get_tickets_remaining() {
        return max( 0, $this->get_max_tickets() - $this->get_tickets_sold() );
    }

    public function increment_tickets_sold( $count = 1 ) {
        $current = $this->get_tickets_sold();
        update_post_meta( $this->post_id, '_txc_tickets_sold', $current + $count );

        if ( ( $current + $count ) >= $this->get_max_tickets() ) {
            $this->set_status( 'sold_out' );
        }
    }

    public function get_wc_product_id() {
        return (int) get_post_meta( $this->post_id, '_txc_wc_product_id', true );
    }

    public function get_instant_wins_count() {
        return (int) get_post_meta( $this->post_id, '_txc_instant_wins_count', true );
    }

    public function get_youtube_url() {
        return get_post_meta( $this->post_id, '_txc_youtube_url', true );
    }

    public function get_gallery_ids() {
        $ids = get_post_meta( $this->post_id, '_txc_gallery_ids', true );
        return is_array( $ids ) ? $ids : [];
    }

    /**
     * Check if the competition can currently accept entries.
     */
    public function can_enter() {
        $status = $this->get_status();
        if ( 'live' !== $status ) {
            return false;
        }

        if ( get_option( 'txc_pause_sales', '0' ) === '1' ) {
            return false;
        }

        $sales_stop = $this->get_sales_stop_time();
        if ( ! empty( $sales_stop ) && time() >= strtotime( $sales_stop . ' +0000' ) ) {
            return false;
        }

        if ( $this->get_tickets_remaining() <= 0 ) {
            return false;
        }

        $license = TXC_License_Client::instance();
        if ( ! $license->purchases_allowed() ) {
            return false;
        }

        return true;
    }

    /**
     * Check if draw can proceed.
     */
    public function can_draw() {
        $status = $this->get_status();
        if ( ! in_array( $status, [ 'live', 'sold_out' ], true ) ) {
            return false;
        }

        if ( $this->get_must_sell_out() && $this->get_tickets_remaining() > 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Get the time remaining until the draw as an array.
     */
    public function get_time_until_draw() {
        $draw = $this->get_draw_date();
        if ( empty( $draw ) ) {
            return null;
        }

        $diff = strtotime( $draw . ' +0000' ) - time();
        if ( $diff <= 0 ) {
            return [ 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'expired' => true ];
        }

        return [
            'days'    => floor( $diff / DAY_IN_SECONDS ),
            'hours'   => floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ),
            'minutes' => floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ),
            'seconds' => $diff % MINUTE_IN_SECONDS,
            'expired' => false,
        ];
    }

    /**
     * Get tickets for a specific user.
     */
    public function get_user_tickets( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d AND user_id = %d ORDER BY ticket_number ASC",
            $this->post_id,
            $user_id
        ) );
    }

    /**
     * Get count of tickets a user has for this competition.
     */
    public function get_user_ticket_count( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE competition_id = %d AND user_id = %d",
            $this->post_id,
            $user_id
        ) );
    }

    /**
     * Get the winner display data.
     */
    public function get_winner_display() {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';

        $winner = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d AND is_winner = 1 LIMIT 1",
            $this->post_id
        ) );

        if ( ! $winner ) {
            return null;
        }

        $user = get_userdata( $winner->user_id );
        $first_name = $user ? $user->first_name : 'Unknown';
        $last_name = $user ? $user->last_name : '';

        $obfuscated_last = '';
        if ( ! empty( $last_name ) ) {
            $obfuscated_last = mb_substr( $last_name, 0, 1 ) . str_repeat( '*', max( 0, mb_strlen( $last_name ) - 1 ) );
        }

        return [
            'ticket_number'   => (int) $winner->ticket_number,
            'first_name'      => $first_name,
            'last_name'       => $obfuscated_last,
            'display_name'    => $first_name . ( $obfuscated_last ? ' ' . $obfuscated_last : '' ),
            'competition_id'  => $this->post_id,
        ];
    }

    /**
     * Static: get all active competitions.
     */
    public static function get_active() {
        $posts = get_posts( [
            'post_type'      => 'txc_competition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_txc_status',
                    'value'   => [ 'live', 'sold_out' ],
                    'compare' => 'IN',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => '_txc_draw_date',
            'order'          => 'ASC',
        ] );

        return array_map( function ( $post ) {
            return new self( $post->ID );
        }, $posts );
    }

    /**
     * Static: get drawn competitions for winners page.
     */
    public static function get_drawn() {
        $posts = get_posts( [
            'post_type'      => 'txc_competition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_txc_status',
                    'value' => 'drawn',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => '_txc_draw_date',
            'order'          => 'DESC',
        ] );

        return array_map( function ( $post ) {
            return new self( $post->ID );
        }, $posts );
    }
}
