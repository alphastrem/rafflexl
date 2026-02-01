<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Public {

    public function enqueue_styles() {
        if ( $this->is_competition_page() || $this->is_account_page() ) {
            wp_enqueue_style( 'txc-public', TXC_PLUGIN_URL . 'assets/css/txc-public.css', [], TXC_VERSION );
        }
    }

    public function enqueue_scripts() {
        if ( ! $this->is_competition_page() && ! $this->is_account_page() ) {
            return;
        }

        // Alpine.js — must load AFTER component scripts so functions are
        // defined before Alpine auto-initialises. Load without defer so
        // execution order is deterministic.
        wp_enqueue_script( 'txc-competition', TXC_PLUGIN_URL . 'assets/js/txc-competition.js', [], TXC_VERSION, true );
        wp_enqueue_script( 'txc-qualifying', TXC_PLUGIN_URL . 'assets/js/txc-qualifying.js', [], TXC_VERSION, true );
        wp_enqueue_script( 'txc-draw', TXC_PLUGIN_URL . 'assets/js/txc-draw.js', [], TXC_VERSION, true );
        wp_enqueue_script( 'alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js', [ 'txc-competition', 'txc-qualifying', 'txc-draw' ], '3.14.8', true );

        wp_localize_script( 'txc-competition', 'txcPublic', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'txc_public_nonce' ),
            'cartUrl'   => wc_get_cart_url(),
            'accountUrl' => wc_get_account_endpoint_url( 'competitions' ),
        ] );

        // YouTube IFrame API if needed
        if ( txc_addon_enabled( 'youtube' ) ) {
            wp_enqueue_script( 'youtube-iframe-api', 'https://www.youtube.com/iframe_api', [], null, true );
        }
    }

    /**
     * Show pause sales banner on frontend.
     */
    public function maybe_show_pause_banner() {
        if ( TXC_Pause_Mode::is_paused() ) {
            $message = TXC_Pause_Mode::get_message();
            echo '<div class="txc-pause-banner"><div class="txc-pause-banner-inner">' . esc_html( $message ) . '</div></div>';
        }

        // License warning banner
        $license = TXC_License_Client::instance();
        $state = $license->get_license_state();
        if ( 'warning' === $state ) {
            echo '<div class="txc-license-banner">Subscription service update required. Some features may be limited.</div>';
        } elseif ( 'locked' === $state ) {
            echo '<div class="txc-license-banner txc-license-locked">Competition entries are temporarily unavailable.</div>';
        }
    }

    /**
     * Add My Competitions to WooCommerce My Account menu.
     */
    public function account_menu_items( $items ) {
        $new_items = [];
        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;
            if ( 'orders' === $key ) {
                $new_items['competitions'] = 'My Competitions';
            }
        }
        return $new_items;
    }

    /**
     * Register the competitions endpoint for My Account.
     */
    public function register_account_endpoint() {
        add_rewrite_endpoint( 'competitions', EP_ROOT | EP_PAGES );
    }

    /**
     * Render the My Competitions account page.
     */
    public function account_competitions_page() {
        $user_id = get_current_user_id();
        $ticket_manager = new TXC_Ticket_Manager();
        $all_tickets = $ticket_manager->get_user_tickets( $user_id );

        // Group by competition
        $grouped = [];
        foreach ( $all_tickets as $ticket ) {
            $cid = $ticket->competition_id;
            if ( ! isset( $grouped[ $cid ] ) ) {
                $grouped[ $cid ] = [
                    'competition' => new TXC_Competition( $cid ),
                    'tickets'     => [],
                ];
            }
            $grouped[ $cid ]['tickets'][] = $ticket;
        }

        // Get instant wins
        $instant_wins = [];
        if ( txc_addon_enabled( 'instantwins' ) ) {
            $instant_wins = TXC_Instant_Wins::get_user_wins( $user_id );
        }

        // Store credit balance
        $credit = 0;
        if ( function_exists( 'woo_wallet' ) ) {
            $credit = woo_wallet()->wallet->get_wallet_balance( $user_id, 'number' );
        } else {
            $credit = (float) get_user_meta( $user_id, 'txc_store_credit', true );
        }

        include TXC_PLUGIN_DIR . 'templates/myaccount/competitions.php';
    }

    private function is_competition_page() {
        // Direct conditional checks.
        if ( is_singular( 'txc_competition' ) || is_post_type_archive( 'txc_competition' ) ) {
            return true;
        }

        // Fallback: check the global $post directly (handles block themes
        // where is_singular() may not resolve correctly during enqueue).
        global $post;
        if ( $post && 'txc_competition' === get_post_type( $post ) ) {
            return true;
        }

        // Page with slug 'competitions' (our template_include override).
        if ( is_page( 'competitions' ) ) {
            return true;
        }

        // Pages with competition shortcodes.
        $content = ( $post && ! empty( $post->post_content ) ) ? $post->post_content : '';
        if ( has_shortcode( $content, 'txc_competitions' )
            || has_shortcode( $content, 'txc_competition' )
            || has_shortcode( $content, 'txc_winners' ) ) {
            return true;
        }

        return false;
    }

    private function is_account_page() {
        return is_account_page();
    }
}
