<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Settings {

    public function register_settings() {
        // General settings
        register_setting( 'txc_settings', 'txc_allowed_countries' );
        register_setting( 'txc_settings', 'txc_pause_sales' );
        register_setting( 'txc_settings', 'txc_pause_message' );
        register_setting( 'txc_settings', 'txc_minimum_age', [ 'default' => 18 ] );

        // Social links
        register_setting( 'txc_settings', 'txc_social_facebook' );
        register_setting( 'txc_settings', 'txc_social_twitter' );
        register_setting( 'txc_settings', 'txc_social_instagram' );
        register_setting( 'txc_settings', 'txc_social_tiktok' );

        // Add-on toggles
        register_setting( 'txc_settings', 'txc_addon_instantwins_enabled' );
        register_setting( 'txc_settings', 'txc_addon_youtube_enabled' );

        // General section
        add_settings_section( 'txc_general', 'General Settings', '__return_false', 'txc-settings' );

        add_settings_field( 'txc_allowed_countries', 'Allowed Countries', [ $this, 'render_countries_field' ], 'txc-settings', 'txc_general' );
        add_settings_field( 'txc_minimum_age', 'Minimum Age', [ $this, 'render_age_field' ], 'txc-settings', 'txc_general' );
        add_settings_field( 'txc_pause_sales', 'Pause All Sales', [ $this, 'render_pause_field' ], 'txc-settings', 'txc_general' );
        add_settings_field( 'txc_pause_message', 'Pause Banner Message', [ $this, 'render_pause_message_field' ], 'txc-settings', 'txc_general' );

        // Social section
        add_settings_section( 'txc_social', 'Social Media Links', '__return_false', 'txc-settings' );

        add_settings_field( 'txc_social_facebook', 'Facebook URL', [ $this, 'render_url_field' ], 'txc-settings', 'txc_social', [ 'option' => 'txc_social_facebook' ] );
        add_settings_field( 'txc_social_twitter', 'X (Twitter) URL', [ $this, 'render_url_field' ], 'txc-settings', 'txc_social', [ 'option' => 'txc_social_twitter' ] );
        add_settings_field( 'txc_social_instagram', 'Instagram URL', [ $this, 'render_url_field' ], 'txc-settings', 'txc_social', [ 'option' => 'txc_social_instagram' ] );
        add_settings_field( 'txc_social_tiktok', 'TikTok URL', [ $this, 'render_url_field' ], 'txc-settings', 'txc_social', [ 'option' => 'txc_social_tiktok' ] );

        // Add-ons section
        add_settings_section( 'txc_addons', 'Add-On Features', [ $this, 'render_addons_section' ], 'txc-settings' );

        add_settings_field( 'txc_addon_instantwins_enabled', 'Instant Wins', [ $this, 'render_addon_toggle' ], 'txc-settings', 'txc_addons', [ 'addon' => 'instantwins', 'label' => 'Enable Instant Wins' ] );
        add_settings_field( 'txc_addon_youtube_enabled', 'YouTube Draw Links', [ $this, 'render_addon_toggle' ], 'txc-settings', 'txc_addons', [ 'addon' => 'youtube', 'label' => 'Enable YouTube Draw Links' ] );
    }

    public function render_countries_field() {
        $value = get_option( 'txc_allowed_countries', 'GB' );
        echo '<textarea name="txc_allowed_countries" rows="3" cols="50" class="regular-text">' . esc_textarea( $value ) . '</textarea>';
        echo '<p class="description">Comma-separated ISO country codes. Default: GB</p>';
    }

    public function render_age_field() {
        $value = get_option( 'txc_minimum_age', 18 );
        echo '<input type="number" name="txc_minimum_age" value="' . esc_attr( $value ) . '" min="18" max="21" />';
    }

    public function render_pause_field() {
        $value = get_option( 'txc_pause_sales', '0' );
        echo '<label><input type="checkbox" name="txc_pause_sales" value="1" ' . checked( '1', $value, false ) . ' /> Pause all competition sales</label>';
    }

    public function render_pause_message_field() {
        $value = get_option( 'txc_pause_message', 'Sales are temporarily paused for site maintenance. Please check back shortly.' );
        echo '<textarea name="txc_pause_message" rows="2" cols="50" class="large-text">' . esc_textarea( $value ) . '</textarea>';
    }

    public function render_url_field( $args ) {
        $value = get_option( $args['option'], '' );
        echo '<input type="url" name="' . esc_attr( $args['option'] ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function render_addons_section() {
        echo '<p>Add-on features require an active license with the corresponding add-on enabled. If your license does not include an add-on, the toggle will be disabled.</p>';
    }

    public function render_addon_toggle( $args ) {
        $addon = $args['addon'];
        $label = $args['label'];
        $option = "txc_addon_{$addon}_enabled";
        $value = get_option( $option, '0' );

        $license = TXC_License_Client::instance();
        $token_data = $license->get_token_data();
        $token_enabled = ! empty( $token_data['addons'][ $addon ] );
        $disabled = ! $token_enabled;

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr( $option ) . '" value="1" ' . checked( '1', $value, false ) . ' ' . disabled( $disabled, true, false ) . ' />';
        echo ' ' . esc_html( $label );
        echo '</label>';

        if ( $disabled ) {
            echo '<p class="description" style="color:#d63638;">Not included in your license. Contact support to upgrade.</p>';
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'txc_manage_settings' ) ) {
            wp_die( 'Unauthorized' );
        }
        ?>
        <div class="wrap">
            <h1>TelXL Competitions Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'txc_settings' );
                do_settings_sections( 'txc-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
