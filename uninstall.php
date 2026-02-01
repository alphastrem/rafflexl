<?php
/**
 * Fired when the plugin is uninstalled.
 * Only runs if plugin deletion is requested from the admin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'txc_tickets',
    $wpdb->prefix . 'txc_draws',
    $wpdb->prefix . 'txc_questions',
    $wpdb->prefix . 'txc_question_attempts',
    $wpdb->prefix . 'txc_instant_win_map',
    $wpdb->prefix . 'txc_consent_log',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove options
$options = [
    'txc_db_version',
    'txc_license_key',
    'txc_license_token',
    'txc_last_heartbeat',
    'txc_license_grace_start',
    'txc_site_fingerprint',
    'txc_allowed_countries',
    'txc_pause_sales',
    'txc_pause_message',
    'txc_minimum_age',
    'txc_social_facebook',
    'txc_social_twitter',
    'txc_social_instagram',
    'txc_social_tiktok',
    'txc_addon_instantwins_enabled',
    'txc_addon_youtube_enabled',
    'txc_youtube_min_watch_percent',
    'txc_tombstones',
    'txc_pending_cash_payouts',
    'txc_pending_physical_prizes',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove capabilities
$roles = [ 'administrator', 'shop_manager' ];
$caps = [
    'txc_view_competitions',
    'txc_manage_competitions',
    'txc_manage_draws',
    'txc_view_tickets',
    'txc_manage_settings',
    'txc_manage_license',
    'txc_delete_competitions',
    'txc_manage_questions',
    'txc_manage_refunds',
];

foreach ( $roles as $role_name ) {
    $role = get_role( $role_name );
    if ( $role ) {
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }
}

// Remove user meta
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'txc_%'" );

// Remove post meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_txc_%'" );

// Remove competition posts
$competitions = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'txc_competition'" );
foreach ( $competitions as $id ) {
    wp_delete_post( $id, true );
}

// Clear scheduled hooks
wp_clear_scheduled_hook( 'txc_heartbeat_event' );
wp_clear_scheduled_hook( 'txc_auto_draw_event' );
wp_clear_scheduled_hook( 'txc_tombstone_cleanup' );
wp_clear_scheduled_hook( 'txc_draw_retry_event' );

// Flush rewrite rules
flush_rewrite_rules();
