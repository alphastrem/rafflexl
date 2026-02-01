<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_License_Client {

    private static $instance = null;
    private $token_data = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );
    }

    public function add_cron_schedule( $schedules ) {
        $schedules['txc_six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __( 'Every 6 Hours', 'telxl-competitions' ),
        ];
        return $schedules;
    }

    /**
     * Activate a license key with the license server.
     */
    public function activate( $license_key ) {
        $domain      = $this->get_domain();
        $fingerprint = $this->get_fingerprint();

        $payload = [
            'license_key'      => sanitize_text_field( $license_key ),
            'domain'           => $domain,
            'site_fingerprint' => $fingerprint,
        ];

        $response = wp_remote_post( TXC_LICENSE_SERVER . '/activate', [
            'timeout'   => 15,
            'sslverify' => true,
            'headers'   => [ 'Content-Type' => 'application/json' ],
            'body'      => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => 'Connection error: ' . $response->get_error_message(),
            ];
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $body_raw = wp_remote_retrieve_body( $response );
        $body     = json_decode( $body_raw, true );

        if ( 200 !== $code ) {
            $server_error = $body['error'] ?? $body_raw;
            return [
                'success' => false,
                'error'   => sprintf( 'Server returned HTTP %d: %s', $code, $server_error ),
            ];
        }

        $token = $body['token'] ?? '';
        if ( empty( $token ) ) {
            return [
                'success' => false,
                'error'   => 'Server returned 200 but no token in response.',
            ];
        }

        update_option( 'txc_license_key', sanitize_text_field( $license_key ) );
        update_option( 'txc_license_token', $token );
        update_option( 'txc_last_heartbeat', time() );
        delete_option( 'txc_license_grace_start' );

        $this->token_data = null;

        return [ 'success' => true ];
    }

    /**
     * Heartbeat: refresh the token.
     */
    public function heartbeat() {
        $token = get_option( 'txc_license_token', '' );
        if ( empty( $token ) ) {
            return false;
        }

        $response = wp_remote_post( TXC_LICENSE_SERVER . '/heartbeat', [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'token' => $token ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            $this->start_grace_period();
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            if ( isset( $body['error'] ) && 'inactive' === $body['error'] ) {
                $this->start_grace_period();
            }
            return false;
        }

        $new_token = $body['token'] ?? '';
        if ( ! empty( $new_token ) ) {
            update_option( 'txc_license_token', $new_token );
            update_option( 'txc_last_heartbeat', time() );
            delete_option( 'txc_license_grace_start' );
            $this->token_data = null;
        }

        return true;
    }

    /**
     * Check if token should be refreshed (stale > 6 hours).
     */
    public function maybe_refresh_token() {
        if ( ! is_admin() ) {
            return;
        }

        $token = get_option( 'txc_license_token', '' );
        if ( empty( $token ) ) {
            return;
        }

        $last = (int) get_option( 'txc_last_heartbeat', 0 );
        if ( ( time() - $last ) > ( 6 * HOUR_IN_SECONDS ) ) {
            $this->heartbeat();
        }
    }

    /**
     * Decode token payload (base64 JWT middle section).
     */
    public function get_token_data() {
        if ( null !== $this->token_data ) {
            return $this->token_data;
        }

        $token = get_option( 'txc_license_token', '' );
        if ( empty( $token ) ) {
            $this->token_data = false;
            return false;
        }

        $parts = explode( '.', $token );
        if ( count( $parts ) !== 3 ) {
            $this->token_data = false;
            return false;
        }

        $payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
        if ( ! is_array( $payload ) ) {
            $this->token_data = false;
            return false;
        }

        $this->token_data = $payload;
        return $payload;
    }

    /**
     * Check if an add-on is enabled in the license token AND local toggle is on.
     */
    public function is_addon_enabled( $addon ) {
        $data = $this->get_token_data();
        if ( ! $data ) {
            return false;
        }

        $token_enabled = ! empty( $data['addons'][ $addon ] );
        if ( ! $token_enabled ) {
            return false;
        }

        $local_toggle = get_option( "txc_addon_{$addon}_enabled", '0' );
        return '1' === $local_toggle;
    }

    /**
     * Check if the license is in a valid state.
     */
    public function get_license_state() {
        $token = get_option( 'txc_license_token', '' );
        if ( empty( $token ) ) {
            return 'none';
        }

        $data = $this->get_token_data();
        if ( ! $data ) {
            return 'invalid';
        }

        // Check token expiry
        $exp = $data['exp'] ?? 0;
        if ( time() < $exp ) {
            return 'valid';
        }

        // Token expired - check grace period
        $grace_start = (int) get_option( 'txc_license_grace_start', 0 );
        if ( 0 === $grace_start ) {
            $this->start_grace_period();
            $grace_start = time();
        }

        $days_elapsed = ( time() - $grace_start ) / DAY_IN_SECONDS;

        if ( $days_elapsed <= 3 ) {
            return 'grace';
        }
        if ( $days_elapsed <= 7 ) {
            return 'warning';
        }
        return 'locked';
    }

    /**
     * Whether purchases are currently allowed.
     */
    public function purchases_allowed() {
        $state = $this->get_license_state();
        return in_array( $state, [ 'valid', 'grace', 'warning' ], true );
    }

    /**
     * Show admin notice based on license state.
     */
    public function maybe_show_license_notice() {
        $state = $this->get_license_state();

        if ( 'warning' === $state ) {
            echo '<div class="notice notice-error"><p><strong>TelXL Competitions:</strong> Subscription service update required. Please contact support.</p></div>';
        } elseif ( 'locked' === $state ) {
            echo '<div class="notice notice-error"><p><strong>TelXL Competitions:</strong> License expired. Competition purchases are disabled until the license is renewed.</p></div>';
        } elseif ( 'none' === $state ) {
            $page = admin_url( 'admin.php?page=txc-license' );
            echo '<div class="notice notice-warning"><p><strong>TelXL Competitions:</strong> No license key activated. <a href="' . esc_url( $page ) . '">Activate now</a>.</p></div>';
        }
    }

    private function start_grace_period() {
        if ( ! get_option( 'txc_license_grace_start' ) ) {
            update_option( 'txc_license_grace_start', time() );
        }
    }

    private function get_domain() {
        $url = home_url();
        $parsed = wp_parse_url( $url );
        return strtolower( $parsed['host'] ?? '' );
    }

    private function get_fingerprint() {
        $fp = get_option( 'txc_site_fingerprint', '' );
        if ( empty( $fp ) ) {
            $fp = wp_generate_uuid4();
            update_option( 'txc_site_fingerprint', $fp );
        }
        return $fp;
    }
}
