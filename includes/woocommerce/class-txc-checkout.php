<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Checkout {

    /**
     * Final validation at checkout submission.
     */
    public function validate_checkout() {
        if ( ! is_user_logged_in() ) {
            wc_add_notice( 'You must be logged in to purchase competition entries.', 'error' );
            return;
        }

        // Check license allows purchases
        $license = TXC_License_Client::instance();
        if ( ! $license->purchases_allowed() ) {
            wc_add_notice( 'Competition purchases are temporarily unavailable.', 'error' );
            return;
        }

        // Check pause mode
        if ( get_option( 'txc_pause_sales', '0' ) === '1' ) {
            wc_add_notice( 'All competition sales are currently paused.', 'error' );
            return;
        }

        $user_id = get_current_user_id();
        $compliance = new TXC_Compliance();

        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( empty( $item['txc_competition_id'] ) ) {
                continue;
            }

            $comp = new TXC_Competition( $item['txc_competition_id'] );

            // Re-validate availability
            if ( ! $comp->can_enter() ) {
                wc_add_notice(
                    sprintf( 'Competition "%s" is no longer accepting entries.', $comp->get_title() ),
                    'error'
                );
                continue;
            }

            // Verify remaining tickets
            $remaining = $comp->get_tickets_remaining();
            if ( $item['quantity'] > $remaining ) {
                wc_add_notice(
                    sprintf( 'Not enough tickets remaining for "%s". Available: %d.', $comp->get_title(), $remaining ),
                    'error'
                );
                continue;
            }

            // Check per-user total limit
            $max_per_user = $comp->get_max_per_user();
            if ( $max_per_user > 0 ) {
                $owned = $comp->get_user_ticket_count( $user_id );
                $remaining_allowance = max( 0, $max_per_user - $owned );
                if ( $item['quantity'] > $remaining_allowance ) {
                    wc_add_notice(
                        sprintf( 'You already own %d of %d allowed tickets for "%s". Please reduce your quantity to %d or fewer.', $owned, $max_per_user, $comp->get_title(), $remaining_allowance ),
                        'error'
                    );
                    continue;
                }
            }

            // Age check
            if ( ! $compliance->check_user_age( $user_id ) ) {
                wc_add_notice( 'You must be 18 or over to enter competitions.', 'error' );
                return;
            }

            // Country check
            if ( ! $compliance->check_user_country( $user_id ) ) {
                wc_add_notice( 'Competitions are not available in your country.', 'error' );
                return;
            }
        }
    }
}
