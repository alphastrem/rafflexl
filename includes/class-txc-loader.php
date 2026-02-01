<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Loader {

    private $actions = [];
    private $filters = [];

    public function run() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
        $this->register_hooks();

        // GitHub-based auto-update checker.
        TXC_Updater::init();
    }

    private function load_dependencies() {
        // Core
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-license-client.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-competition-cpt.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-competition.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-ticket-manager.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-draw-engine.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-qualifying.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-instant-wins.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-youtube-watch.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-compliance.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-cron.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-email.php';
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-social.php';

        // WooCommerce
        require_once TXC_PLUGIN_DIR . 'includes/woocommerce/class-txc-cart.php';
        require_once TXC_PLUGIN_DIR . 'includes/woocommerce/class-txc-checkout.php';
        require_once TXC_PLUGIN_DIR . 'includes/woocommerce/class-txc-order-handler.php';

        // Admin
        if ( is_admin() ) {
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-admin.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-competition-meta.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-settings.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-license-page.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-questions-admin.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-draw-admin.php';
            require_once TXC_PLUGIN_DIR . 'includes/admin/class-txc-pause-mode.php';
        }

        // Public
        require_once TXC_PLUGIN_DIR . 'includes/public/class-txc-public.php';
        require_once TXC_PLUGIN_DIR . 'includes/public/class-txc-shortcodes.php';

        // REST API
        require_once TXC_PLUGIN_DIR . 'includes/api/class-txc-rest-api.php';

        // Updater
        require_once TXC_PLUGIN_DIR . 'includes/core/class-txc-updater.php';
    }

    private function define_admin_hooks() {
        if ( ! is_admin() ) {
            return;
        }

        $admin = new TXC_Admin();
        $this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
        $this->add_action( 'admin_menu', $admin, 'add_admin_menu' );

        $meta = new TXC_Competition_Meta();
        $this->add_action( 'add_meta_boxes', $meta, 'add_meta_boxes' );
        $this->add_action( 'save_post_txc_competition', $meta, 'save_meta', 10, 2 );

        $settings = new TXC_Settings();
        $this->add_action( 'admin_init', $settings, 'register_settings' );

        $license_page = new TXC_License_Page();
        $this->add_action( 'admin_init', $license_page, 'handle_activation' );

        $questions = new TXC_Questions_Admin();
        $this->add_action( 'admin_init', $questions, 'handle_actions' );

        $draw_admin = new TXC_Draw_Admin();
        $this->add_action( 'wp_ajax_txc_manual_draw', $draw_admin, 'handle_manual_draw' );
        $this->add_action( 'wp_ajax_txc_force_redraw', $draw_admin, 'handle_force_redraw' );

        // Instant wins AJAX
        $this->add_action( 'wp_ajax_txc_generate_instant_wins', 'TXC_Instant_Wins', 'ajax_generate_map' );

        $pause = new TXC_Pause_Mode();
        $this->add_action( 'admin_init', $pause, 'handle_toggle' );
    }

    private function define_public_hooks() {
        $public = new TXC_Public();
        $this->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
        $this->add_action( 'wp_footer', $public, 'maybe_show_pause_banner' );

        // CPT
        $cpt = new TXC_Competition_CPT();
        $this->add_action( 'init', $cpt, 'register_post_type' );
        $this->add_filter( 'single_template', $cpt, 'single_template' );
        $this->add_filter( 'template_include', $cpt, 'maybe_override_template' );

        // Shortcodes
        $shortcodes = new TXC_Shortcodes();
        $this->add_action( 'init', $shortcodes, 'register_shortcodes' );

        // Cart
        $cart = new TXC_Cart();
        $this->add_action( 'wp_ajax_txc_add_to_cart', $cart, 'ajax_add_to_cart' );
        $this->add_filter( 'woocommerce_get_item_data', $cart, 'display_cart_item_data', 10, 2 );
        $this->add_filter( 'woocommerce_cart_item_name', $cart, 'cart_item_name', 10, 3 );
        $this->add_action( 'woocommerce_check_cart_items', $cart, 'validate_cart_items' );

        // Checkout
        $checkout = new TXC_Checkout();
        $this->add_action( 'woocommerce_checkout_process', $checkout, 'validate_checkout' );

        // Order handler
        $order_handler = new TXC_Order_Handler();
        $this->add_action( 'woocommerce_order_status_completed', $order_handler, 'allocate_tickets', 10, 1 );
        $this->add_action( 'woocommerce_order_status_processing', $order_handler, 'allocate_tickets', 10, 1 );

        // Qualifying questions
        $qualifying = new TXC_Qualifying();
        $this->add_action( 'wp_ajax_txc_get_question', $qualifying, 'ajax_get_question' );
        $this->add_action( 'wp_ajax_txc_submit_answer', $qualifying, 'ajax_submit_answer' );

        // License client
        $license = TXC_License_Client::instance();
        $this->add_action( 'init', $license, 'maybe_refresh_token' );
        $this->add_action( 'admin_notices', $license, 'maybe_show_license_notice' );

        // Compliance
        $compliance = new TXC_Compliance();
        $this->add_action( 'woocommerce_register_form', $compliance, 'registration_fields' );
        $this->add_action( 'woocommerce_created_customer', $compliance, 'save_registration_fields', 10, 1 );
        $this->add_filter( 'woocommerce_registration_errors', $compliance, 'validate_registration', 10, 3 );

        // My Account tab
        $this->add_filter( 'woocommerce_account_menu_items', $public, 'account_menu_items' );
        $this->add_action( 'woocommerce_account_competitions_endpoint', $public, 'account_competitions_page' );
        $this->add_action( 'init', $public, 'register_account_endpoint' );

        // Emails
        $email = new TXC_Email();
        $this->add_action( 'txc_tickets_allocated', $email, 'send_ticket_confirmation', 10, 3 );

        // REST API
        $api = new TXC_Rest_API();
        $this->add_action( 'rest_api_init', $api, 'register_routes' );

        // Instant wins (add-on)
        if ( txc_addon_enabled( 'instantwins' ) ) {
            $iw = new TXC_Instant_Wins();
            $this->add_action( 'txc_tickets_allocated', $iw, 'check_instant_wins', 10, 3 );
        }


        // Privacy / GDPR
        $this->add_filter( 'wp_privacy_personal_data_exporters', 'TXC_Compliance', 'register_privacy_exporter' );
    }

    private function define_cron_hooks() {
        $cron = new TXC_Cron();
        $this->add_action( 'txc_heartbeat_event', $cron, 'do_heartbeat' );
        $this->add_action( 'txc_auto_draw_event', $cron, 'do_auto_draw' );
        $this->add_action( 'txc_tombstone_cleanup', $cron, 'cleanup_tombstones' );
        $this->add_action( 'txc_draw_retry_event', $cron, 'retry_failed_draw' );
    }

    private function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    private function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    private function register_hooks() {
        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], [ $hook['component'], $hook['callback'] ], $hook['priority'], $hook['accepted_args'] );
        }
    }
}

/**
 * Global helper: check if an add-on is enabled via license token.
 */
function txc_addon_enabled( $addon ) {
    $client = TXC_License_Client::instance();
    return $client->is_addon_enabled( $addon );
}
