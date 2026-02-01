<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Pause_Mode {

    public function handle_toggle() {
        // Handled via settings page save - no separate action needed
    }

    /**
     * Check if sales are paused.
     */
    public static function is_paused() {
        return get_option( 'txc_pause_sales', '0' ) === '1';
    }

    /**
     * Get the pause banner message.
     */
    public static function get_message() {
        return get_option( 'txc_pause_message', 'Sales are temporarily paused for site maintenance. Please check back shortly.' );
    }
}
