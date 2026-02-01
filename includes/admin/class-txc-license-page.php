<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_License_Page {

    public function handle_activation() {
        if ( ! current_user_can( 'txc_manage_license' ) ) {
            return;
        }

        if ( ! isset( $_POST['txc_activate_license_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['txc_activate_license_nonce'], 'txc_activate_license' ) ) {
            return;
        }

        $key = sanitize_text_field( $_POST['txc_license_key'] ?? '' );
        if ( empty( $key ) ) {
            add_settings_error( 'txc_license', 'empty_key', 'Please enter a license key.' );
            return;
        }

        $client = TXC_License_Client::instance();
        $result = $client->activate( $key );

        if ( $result['success'] ) {
            add_settings_error( 'txc_license', 'activated', 'License activated successfully.', 'updated' );
        } else {
            add_settings_error( 'txc_license', 'activation_failed', 'Activation failed: ' . esc_html( $result['error'] ) );
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'txc_manage_license' ) ) {
            wp_die( 'Unauthorized' );
        }

        $license_key = get_option( 'txc_license_key', '' );
        $client = TXC_License_Client::instance();
        $state = $client->get_license_state();
        $token_data = $client->get_token_data();
        ?>
        <div class="wrap">
            <h1>License Management</h1>

            <?php settings_errors( 'txc_license' ); ?>

            <?php if ( $token_data ) : ?>
                <div class="txc-license-status" style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:16px;margin-bottom:20px;">
                    <h2 style="margin-top:0;">License Status</h2>
                    <table class="form-table">
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php
                                $badges = [
                                    'valid'   => '<span style="color:#00a32a;font-weight:bold;">Active</span>',
                                    'grace'   => '<span style="color:#dba617;font-weight:bold;">Grace Period</span>',
                                    'warning' => '<span style="color:#d63638;font-weight:bold;">Warning - Renewal Required</span>',
                                    'locked'  => '<span style="color:#d63638;font-weight:bold;">Locked - Purchases Disabled</span>',
                                ];
                                echo $badges[ $state ] ?? '<span>Unknown</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>License Key</th>
                            <td><code><?php echo esc_html( $license_key ); ?></code></td>
                        </tr>
                        <tr>
                            <th>Bound Domain</th>
                            <td><?php echo esc_html( $token_data['domain'] ?? 'N/A' ); ?></td>
                        </tr>
                        <tr>
                            <th>Plan</th>
                            <td><?php echo esc_html( ucfirst( $token_data['plan'] ?? 'core' ) ); ?></td>
                        </tr>
                        <tr>
                            <th>Add-Ons</th>
                            <td>
                                <?php
                                $addons = $token_data['addons'] ?? [];
                                if ( ! empty( $addons['youtube'] ) ) {
                                    echo '<span class="txc-badge txc-badge-green">YouTube Watch</span> ';
                                }
                                if ( ! empty( $addons['instantwins'] ) ) {
                                    echo '<span class="txc-badge txc-badge-green">Instant Wins</span> ';
                                }
                                if ( empty( $addons['youtube'] ) && empty( $addons['instantwins'] ) ) {
                                    echo 'None';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Token Expires</th>
                            <td>
                                <?php
                                $exp = $token_data['exp'] ?? 0;
                                if ( $exp > 0 ) {
                                    $remaining = $exp - time();
                                    echo esc_html( date_i18n( 'Y-m-d H:i:s', $exp ) );
                                    if ( $remaining > 0 ) {
                                        echo ' <small>(' . esc_html( human_time_diff( time(), $exp ) ) . ' remaining)</small>';
                                    } else {
                                        echo ' <span style="color:#d63638;"><strong>(expired)</strong></span>';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Heartbeat</th>
                            <td>
                                <?php
                                $last = (int) get_option( 'txc_last_heartbeat', 0 );
                                echo $last > 0 ? esc_html( date_i18n( 'Y-m-d H:i:s', $last ) ) : 'Never';
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <h2><?php echo $token_data ? 'Re-activate License' : 'Activate License'; ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'txc_activate_license', 'txc_activate_license_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="txc_license_key">License Key</label></th>
                        <td>
                            <input type="text" id="txc_license_key" name="txc_license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" placeholder="Enter your license key" />
                            <p class="description">Enter the license key provided to you and click Activate.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Activate License' ); ?>
            </form>
        </div>
        <?php
    }
}
