<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook( 'txc_heartbeat_event' );
        wp_clear_scheduled_hook( 'txc_auto_draw_event' );
        wp_clear_scheduled_hook( 'txc_tombstone_cleanup' );
        wp_clear_scheduled_hook( 'txc_draw_retry_event' );
        flush_rewrite_rules();
    }
}
