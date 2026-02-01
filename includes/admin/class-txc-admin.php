<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Admin {

    public function enqueue_styles( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }
        wp_enqueue_style( 'txc-admin', TXC_PLUGIN_URL . 'assets/css/txc-admin.css', [], TXC_VERSION );
    }

    public function enqueue_scripts( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'txc-admin', TXC_PLUGIN_URL . 'assets/js/txc-admin.js', [ 'jquery', 'wp-util' ], TXC_VERSION, true );
        wp_localize_script( 'txc-admin', 'txcAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'txc_admin_nonce' ),
        ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Competitions',
            'Competitions',
            'txc_view_competitions',
            'txc-competitions',
            [ $this, 'render_competitions_page' ],
            'dashicons-tickets-alt',
            30
        );

        add_submenu_page(
            'txc-competitions',
            'All Competitions',
            'All Competitions',
            'txc_view_competitions',
            'txc-competitions',
            [ $this, 'render_competitions_page' ]
        );

        add_submenu_page(
            'txc-competitions',
            'Add New',
            'Add New',
            'txc_manage_competitions',
            'post-new.php?post_type=txc_competition'
        );

        add_submenu_page(
            'txc-competitions',
            'Questions',
            'Questions',
            'txc_manage_questions',
            'txc-questions',
            [ $this, 'render_questions_page' ]
        );

        add_submenu_page(
            'txc-competitions',
            'Draws',
            'Draws',
            'txc_manage_draws',
            'txc-draws',
            [ $this, 'render_draws_page' ]
        );

        add_submenu_page(
            'txc-competitions',
            'Settings',
            'Settings',
            'txc_manage_settings',
            'txc-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'txc-competitions',
            'License',
            'License',
            'txc_manage_license',
            'txc-license',
            [ $this, 'render_license_page' ]
        );
    }

    public function render_competitions_page() {
        $url = admin_url( 'edit.php?post_type=txc_competition' );
        wp_redirect( $url );
        exit;
    }

    public function render_questions_page() {
        $questions_admin = new TXC_Questions_Admin();
        $questions_admin->render_page();
    }

    public function render_draws_page() {
        $draw_admin = new TXC_Draw_Admin();
        $draw_admin->render_page();
    }

    public function render_settings_page() {
        $settings = new TXC_Settings();
        $settings->render_page();
    }

    public function render_license_page() {
        $license = new TXC_License_Page();
        $license->render_page();
    }

    private function is_plugin_page( $hook ) {
        $screens = [
            'txc_competition', 'edit-txc_competition',
            'toplevel_page_txc-competitions',
            'competitions_page_txc-questions',
            'competitions_page_txc-draws',
            'competitions_page_txc-settings',
            'competitions_page_txc-license',
        ];

        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, $screens, true ) ) {
            return true;
        }

        return strpos( $hook, 'txc-' ) !== false;
    }
}
