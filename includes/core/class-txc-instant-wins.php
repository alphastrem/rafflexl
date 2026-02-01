<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Instant_Wins {

    /**
     * Generate instant win ticket assignments for a competition.
     * Called when competition goes live and has instant wins configured.
     */
    public static function generate_map( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_instant_win_map';

        // Don't regenerate if map already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE competition_id = %d",
            $competition_id
        ) );
        if ( $existing > 0 ) {
            return;
        }

        $comp = new TXC_Competition( $competition_id );
        $count = $comp->get_instant_wins_count();
        $max_tickets = $comp->get_max_tickets();

        if ( $count < 1 || $max_tickets < 1 || $count > $max_tickets ) {
            return;
        }

        // Pick random ticket positions
        $all_numbers = range( 1, $max_tickets );
        $selected = [];
        $pool = $all_numbers;

        for ( $i = 0; $i < $count; $i++ ) {
            $idx = random_int( 0, count( $pool ) - 1 );
            $selected[] = $pool[ $idx ];
            array_splice( $pool, $idx, 1 );
        }

        // Get prize config (admin configures this via the draw admin page)
        $prizes = self::get_prize_config( $competition_id );

        foreach ( $selected as $index => $ticket_number ) {
            $prize = $prizes[ $index ] ?? [
                'type'  => 'credit',
                'value' => 5.00,
                'label' => '£5 Site Credit',
            ];

            $wpdb->insert( $table, [
                'competition_id' => $competition_id,
                'ticket_number'  => $ticket_number,
                'prize_type'     => $prize['type'],
                'prize_value'    => $prize['value'],
                'prize_label'    => $prize['label'],
                'claimed'        => 0,
            ] );
        }
    }

    /**
     * Check allocated tickets for instant wins.
     * Hooked to 'txc_tickets_allocated'.
     */
    public function check_instant_wins( $order_id, $user_id, $all_allocated ) {
        if ( ! txc_addon_enabled( 'instantwins' ) ) {
            return;
        }

        global $wpdb;
        $iw_table = $wpdb->prefix . 'txc_instant_win_map';
        $ticket_table = $wpdb->prefix . 'txc_tickets';
        $ticket_manager = new TXC_Ticket_Manager();
        $email = new TXC_Email();

        foreach ( $all_allocated as $competition_id => $tickets ) {
            foreach ( $tickets as $ticket ) {
                $iw = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$iw_table} WHERE competition_id = %d AND ticket_number = %d AND claimed = 0",
                    $competition_id,
                    $ticket->ticket_number
                ) );

                if ( ! $iw ) {
                    continue;
                }

                // Mark instant win on the ticket
                $ticket_manager->mark_instant_win(
                    $ticket->id,
                    $iw->prize_type,
                    $iw->prize_value,
                    $iw->prize_label
                );

                // Mark claimed in map
                $wpdb->update( $iw_table, [
                    'claimed'            => 1,
                    'claimed_by_user_id' => $user_id,
                    'claimed_at'         => current_time( 'mysql', true ),
                ], [ 'id' => $iw->id ] );

                // Fulfil prize
                $this->fulfil_prize( $user_id, $iw );

                // Send notification email
                $email->send_instant_win_notification(
                    $user_id,
                    $competition_id,
                    $ticket->ticket_number,
                    $iw->prize_label,
                    $iw->prize_value
                );
            }
        }
    }

    /**
     * Fulfil an instant win prize.
     */
    private function fulfil_prize( $user_id, $iw ) {
        switch ( $iw->prize_type ) {
            case 'credit':
                $this->add_store_credit( $user_id, (float) $iw->prize_value );
                break;

            case 'coupon':
                $this->generate_coupon( $user_id, (float) $iw->prize_value, $iw->prize_label );
                break;

            case 'cash':
                // Flag for manual admin payout
                $this->flag_cash_payout( $user_id, $iw );
                break;

            case 'physical':
                // Flag for shipping
                $this->flag_physical_prize( $user_id, $iw );
                break;
        }
    }

    /**
     * Add store credit to user's WooCommerce wallet.
     */
    private function add_store_credit( $user_id, $amount ) {
        // Try TeraWallet plugin (most popular WC wallet)
        if ( function_exists( 'woo_wallet' ) ) {
            woo_wallet()->wallet->credit( $user_id, $amount, 'Instant Win Prize' );
            return;
        }

        // Fallback: store as user meta for manual processing
        $current = (float) get_user_meta( $user_id, 'txc_store_credit', true );
        update_user_meta( $user_id, 'txc_store_credit', $current + $amount );
    }

    /**
     * Generate a WooCommerce coupon for the user.
     */
    private function generate_coupon( $user_id, $amount, $label ) {
        $code = 'TXC-WIN-' . strtoupper( wp_generate_password( 8, false ) );

        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_description( sprintf( 'Instant Win: %s for user #%d', $label, $user_id ) );
        $coupon->set_discount_type( 'fixed_cart' );
        $coupon->set_amount( $amount );
        $coupon->set_usage_limit( 1 );
        $coupon->set_email_restrictions( [ get_userdata( $user_id )->user_email ] );
        $coupon->set_individual_use( false );
        $coupon->save();

        // Store coupon code on user for display
        $coupons = get_user_meta( $user_id, 'txc_instant_win_coupons', true ) ?: [];
        $coupons[] = $code;
        update_user_meta( $user_id, 'txc_instant_win_coupons', $coupons );
    }

    /**
     * Flag a cash prize for admin payout.
     */
    private function flag_cash_payout( $user_id, $iw ) {
        $payouts = get_option( 'txc_pending_cash_payouts', [] );
        $payouts[] = [
            'user_id'        => $user_id,
            'competition_id' => $iw->competition_id,
            'ticket_number'  => $iw->ticket_number,
            'amount'         => $iw->prize_value,
            'label'          => $iw->prize_label,
            'created_at'     => current_time( 'mysql', true ),
        ];
        update_option( 'txc_pending_cash_payouts', $payouts );
    }

    /**
     * Flag a physical prize for shipping.
     */
    private function flag_physical_prize( $user_id, $iw ) {
        $prizes = get_option( 'txc_pending_physical_prizes', [] );
        $prizes[] = [
            'user_id'        => $user_id,
            'competition_id' => $iw->competition_id,
            'ticket_number'  => $iw->ticket_number,
            'label'          => $iw->prize_label,
            'created_at'     => current_time( 'mysql', true ),
        ];
        update_option( 'txc_pending_physical_prizes', $prizes );
    }

    /**
     * Get prize configuration for a competition.
     */
    public static function get_prize_config( $competition_id ) {
        $config = get_post_meta( $competition_id, '_txc_instant_win_prizes', true );
        return is_array( $config ) ? $config : [];
    }

    /**
     * Save prize configuration for a competition.
     */
    public static function save_prize_config( $competition_id, $prizes ) {
        update_post_meta( $competition_id, '_txc_instant_win_prizes', $prizes );
    }

    /**
     * Get all instant wins for a user.
     */
    public static function get_user_wins( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_instant_win_map';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT iw.*, p.post_title as competition_title
            FROM {$table} iw
            LEFT JOIN {$wpdb->posts} p ON iw.competition_id = p.ID
            WHERE iw.claimed_by_user_id = %d
            ORDER BY iw.claimed_at DESC",
            $user_id
        ) );
    }

    /**
     * AJAX handler: pick random instant win numbers for a competition.
     */
    public static function ajax_generate_map() {
        check_ajax_referer( 'txc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        $count          = absint( $_POST['count'] ?? 0 );

        if ( ! $competition_id || ! $count ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid number of instant wins.' ] );
        }

        $comp        = new TXC_Competition( $competition_id );
        $max_tickets = $comp->get_max_tickets();

        if ( $max_tickets < 1 ) {
            wp_send_json_error( [ 'message' => 'Set Max Tickets Total first, then save the competition.' ] );
        }

        if ( $count > $max_tickets ) {
            wp_send_json_error( [ 'message' => 'Instant win count cannot exceed total tickets (' . $max_tickets . ').' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'txc_instant_win_map';

        // Delete existing unclaimed entries only
        $wpdb->delete( $table, [
            'competition_id' => $competition_id,
            'claimed'        => 0,
        ] );

        // Build pool excluding any already-claimed numbers
        $pool = range( 1, $max_tickets );

        $claimed_numbers = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$table} WHERE competition_id = %d AND claimed = 1",
            $competition_id
        ) );
        if ( ! empty( $claimed_numbers ) ) {
            $pool = array_values( array_diff( $pool, $claimed_numbers ) );
        }

        if ( $count > count( $pool ) ) {
            wp_send_json_error( [ 'message' => 'Not enough available ticket numbers.' ] );
        }

        // CSPRNG pick
        $selected = [];
        for ( $i = 0; $i < $count; $i++ ) {
            $idx        = random_int( 0, count( $pool ) - 1 );
            $selected[] = $pool[ $idx ];
            array_splice( $pool, $idx, 1 );
        }
        sort( $selected );

        // Insert with default prizes
        foreach ( $selected as $ticket_number ) {
            $wpdb->insert( $table, [
                'competition_id' => $competition_id,
                'ticket_number'  => $ticket_number,
                'prize_type'     => 'credit',
                'prize_value'    => 5.00,
                'prize_label'    => "\xC2\xA35 Site Credit",
                'claimed'        => 0,
            ] );
        }

        // Update count meta
        update_post_meta( $competition_id, '_txc_instant_wins_count', $count );

        // Fetch all entries (including any claimed)
        $entries = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY ticket_number ASC",
            $competition_id
        ) );

        ob_start();
        self::render_admin_entries_table( $entries, true );
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html, 'count' => count( $entries ) ] );
    }

    /**
     * Render the admin entries table for the meta box.
     */
    public static function render_admin_entries_table( $entries, $editable = true ) {
        $types = [
            'credit'   => 'Site Credit',
            'coupon'   => 'Coupon',
            'cash'     => 'Cash',
            'physical' => 'Physical Prize',
        ];
        ?>
        <table class="widefat striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th style="width:80px;">Ticket #</th>
                    <th style="width:140px;">Prize Type</th>
                    <th style="width:90px;">Value (&pound;)</th>
                    <th>Label</th>
                    <th style="width:90px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $entries as $entry ) : ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( $entry->ticket_number ); ?></strong></td>
                        <?php if ( $editable && ! $entry->claimed ) : ?>
                            <td>
                                <select name="txc_iw[<?php echo esc_attr( $entry->id ); ?>][prize_type]" style="width:100%;">
                                    <?php foreach ( $types as $key => $label ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $entry->prize_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="txc_iw[<?php echo esc_attr( $entry->id ); ?>][prize_value]" value="<?php echo esc_attr( $entry->prize_value ); ?>" step="0.01" min="0" style="width:100%;" />
                            </td>
                            <td>
                                <input type="text" name="txc_iw[<?php echo esc_attr( $entry->id ); ?>][prize_label]" value="<?php echo esc_attr( $entry->prize_label ); ?>" style="width:100%;" />
                            </td>
                        <?php else : ?>
                            <td><?php echo esc_html( $types[ $entry->prize_type ] ?? $entry->prize_type ); ?></td>
                            <td>&pound;<?php echo esc_html( number_format( (float) $entry->prize_value, 2 ) ); ?></td>
                            <td><?php echo esc_html( $entry->prize_label ); ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ( $entry->claimed ) : ?>
                                <span class="txc-badge txc-badge-green">Claimed</span>
                            <?php else : ?>
                                <span class="txc-badge txc-badge-yellow">Unclaimed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get entries from the DB for a competition (admin use).
     */
    public static function get_admin_entries( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_instant_win_map';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY ticket_number ASC",
            $competition_id
        ) );
    }

    /**
     * Get all instant win entries for a competition with winner info.
     */
    public static function get_competition_wins( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_instant_win_map';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ticket_number, prize_label, claimed, claimed_by_user_id, claimed_at
            FROM {$table}
            WHERE competition_id = %d
            ORDER BY ticket_number ASC",
            $competition_id
        ) );

        if ( empty( $results ) ) {
            return [];
        }

        $wins = [];
        foreach ( $results as $row ) {
            $entry = [
                'ticket_number' => (int) $row->ticket_number,
                'prize_label'   => $row->prize_label,
                'claimed'       => (bool) $row->claimed,
                'winner_name'   => '',
                'claimed_at'    => $row->claimed_at,
            ];

            if ( $row->claimed && $row->claimed_by_user_id ) {
                $user = get_userdata( $row->claimed_by_user_id );
                if ( $user ) {
                    $first = $user->first_name ?: 'Winner';
                    $last  = $user->last_name;
                    $obfuscated_last = '';
                    if ( ! empty( $last ) ) {
                        $obfuscated_last = mb_substr( $last, 0, 1 ) . str_repeat( '*', max( 0, mb_strlen( $last ) - 1 ) );
                    }
                    $entry['winner_name'] = $first . ( $obfuscated_last ? ' ' . $obfuscated_last : '' );
                }
            }

            $wins[] = $entry;
        }

        return $wins;
    }
}
