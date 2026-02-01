<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Compliance {

    /**
     * Add fields to WooCommerce registration form.
     */
    public function registration_fields() {
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="txc_dob"><?php esc_html_e( 'Date of Birth', 'telxl-competitions' ); ?> <span class="required">*</span></label>
            <input type="date" class="woocommerce-Input input-text" name="txc_dob" id="txc_dob" max="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-18 years' ) ) ); ?>" required />
            <small>You must be 18 or over to register.</small>
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="txc_country"><?php esc_html_e( 'Country', 'telxl-competitions' ); ?> <span class="required">*</span></label>
            <select name="txc_country" id="txc_country" class="woocommerce-Input input-text" required>
                <option value="">Select country</option>
                <?php
                $allowed = $this->get_allowed_countries();
                $country_names = $this->get_country_names();
                foreach ( $allowed as $code ) {
                    $code = trim( strtoupper( $code ) );
                    $name = $country_names[ $code ] ?? $code;
                    echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
                }
                ?>
            </select>
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="txc_marketing_consent" id="txc_marketing_consent" value="1" />
                <span><?php esc_html_e( 'I agree to receive marketing emails about competitions and promotions.', 'telxl-competitions' ); ?></span>
            </label>
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="txc_terms_consent" id="txc_terms_consent" value="1" required />
                <span><?php esc_html_e( 'I accept the terms and conditions and confirm I am 18 or over.', 'telxl-competitions' ); ?> <span class="required">*</span></span>
            </label>
        </p>
        <?php
    }

    /**
     * Validate registration fields.
     */
    public function validate_registration( $errors, $username, $email ) {
        $dob = sanitize_text_field( $_POST['txc_dob'] ?? '' );
        $country = sanitize_text_field( $_POST['txc_country'] ?? '' );
        $terms = sanitize_text_field( $_POST['txc_terms_consent'] ?? '' );

        if ( empty( $dob ) ) {
            $errors->add( 'txc_dob_error', '<strong>Date of Birth</strong> is required.' );
        } else {
            $age = $this->calculate_age( $dob );
            $min_age = (int) get_option( 'txc_minimum_age', 18 );
            if ( $age < $min_age ) {
                $errors->add( 'txc_age_error', sprintf( 'You must be %d or over to register.', $min_age ) );
            }
        }

        if ( empty( $country ) ) {
            $errors->add( 'txc_country_error', '<strong>Country</strong> is required.' );
        } else {
            $allowed = $this->get_allowed_countries();
            if ( ! in_array( strtoupper( $country ), array_map( 'strtoupper', $allowed ), true ) ) {
                $errors->add( 'txc_country_error', 'Registration is not available in your country.' );
            }
        }

        if ( empty( $terms ) ) {
            $errors->add( 'txc_terms_error', 'You must accept the terms and conditions.' );
        }

        return $errors;
    }

    /**
     * Save registration fields and log consent.
     */
    public function save_registration_fields( $customer_id ) {
        $dob = sanitize_text_field( $_POST['txc_dob'] ?? '' );
        $country = sanitize_text_field( $_POST['txc_country'] ?? '' );
        $marketing = ! empty( $_POST['txc_marketing_consent'] );

        if ( $dob ) {
            update_user_meta( $customer_id, 'txc_dob', $dob );
        }
        if ( $country ) {
            update_user_meta( $customer_id, 'txc_country', strtoupper( $country ) );
        }

        // Log consent
        $this->log_consent( $customer_id, 'age_verified', true );
        $this->log_consent( $customer_id, 'terms', true );
        $this->log_consent( $customer_id, 'marketing_email', $marketing );
    }

    /**
     * Check if a user meets the age requirement.
     */
    public function check_user_age( $user_id ) {
        $dob = get_user_meta( $user_id, 'txc_dob', true );
        if ( empty( $dob ) ) {
            return false;
        }

        $min_age = (int) get_option( 'txc_minimum_age', 18 );
        return $this->calculate_age( $dob ) >= $min_age;
    }

    /**
     * Check if a user's country is allowed.
     */
    public function check_user_country( $user_id ) {
        $country = get_user_meta( $user_id, 'txc_country', true );
        if ( empty( $country ) ) {
            return false;
        }

        $allowed = $this->get_allowed_countries();
        return in_array( strtoupper( $country ), array_map( 'strtoupper', $allowed ), true );
    }

    /**
     * Calculate age from date of birth.
     */
    private function calculate_age( $dob ) {
        $birth = new DateTime( $dob );
        $now = new DateTime();
        return $now->diff( $birth )->y;
    }

    /**
     * Get list of allowed country codes.
     */
    private function get_allowed_countries() {
        $raw = get_option( 'txc_allowed_countries', 'GB' );
        return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
    }

    /**
     * Log a consent action.
     */
    private function log_consent( $user_id, $type, $consented ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_consent_log';

        $wpdb->insert( $table, [
            'user_id'      => $user_id,
            'consent_type' => $type,
            'consented'    => $consented ? 1 : 0,
            'ip_address'   => $this->get_ip(),
            'user_agent'   => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            'consented_at' => current_time( 'mysql', true ),
        ] );
    }

    /**
     * Get client IP address.
     */
    private function get_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return sanitize_text_field( $ip );
    }

    /**
     * Country code to name mapping (common countries).
     */
    private function get_country_names() {
        return [
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'PT' => 'Portugal',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
        ];
    }

    /**
     * WP Privacy: register personal data exporter.
     */
    public static function register_privacy_exporter( $exporters ) {
        $exporters['txc-competitions'] = [
            'exporter_friendly_name' => 'TelXL Competitions Data',
            'callback'               => [ __CLASS__, 'privacy_exporter' ],
        ];
        return $exporters;
    }

    /**
     * WP Privacy: export user data.
     */
    public static function privacy_exporter( $email, $page = 1 ) {
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            return [ 'data' => [], 'done' => true ];
        }

        global $wpdb;
        $data = [];

        // Tickets
        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.post_title FROM {$wpdb->prefix}txc_tickets t LEFT JOIN {$wpdb->posts} p ON t.competition_id = p.ID WHERE t.user_id = %d",
            $user->ID
        ) );

        foreach ( $tickets as $t ) {
            $data[] = [
                'group_id'    => 'txc-tickets',
                'group_label' => 'Competition Tickets',
                'item_id'     => 'ticket-' . $t->id,
                'data'        => [
                    [ 'name' => 'Competition', 'value' => $t->post_title ],
                    [ 'name' => 'Ticket Number', 'value' => $t->ticket_number ],
                    [ 'name' => 'Allocated', 'value' => $t->allocated_at ],
                    [ 'name' => 'Winner', 'value' => $t->is_winner ? 'Yes' : 'No' ],
                ],
            ];
        }

        // Consent log
        $consents = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}txc_consent_log WHERE user_id = %d",
            $user->ID
        ) );

        foreach ( $consents as $c ) {
            $data[] = [
                'group_id'    => 'txc-consent',
                'group_label' => 'Competition Consent Records',
                'item_id'     => 'consent-' . $c->id,
                'data'        => [
                    [ 'name' => 'Type', 'value' => $c->consent_type ],
                    [ 'name' => 'Consented', 'value' => $c->consented ? 'Yes' : 'No' ],
                    [ 'name' => 'Date', 'value' => $c->consented_at ],
                    [ 'name' => 'IP', 'value' => $c->ip_address ],
                ],
            ];
        }

        return [ 'data' => $data, 'done' => true ];
    }
}
