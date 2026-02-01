<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Cart {

    /**
     * AJAX handler: add competition entry to cart.
     */
    public function ajax_add_to_cart() {
        check_ajax_referer( 'txc_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'You must be logged in to enter a competition.' ] );
        }

        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        $quantity       = absint( $_POST['quantity'] ?? 1 );

        if ( ! $competition_id ) {
            wp_send_json_error( [ 'message' => 'Invalid competition.' ] );
        }

        $comp = new TXC_Competition( $competition_id );

        // Check competition can accept entries
        if ( ! $comp->can_enter() ) {
            wp_send_json_error( [ 'message' => 'This competition is not currently accepting entries.' ] );
        }

        // Check quantity against per-user total limit
        $max_per_user = $comp->get_max_per_user();
        if ( $max_per_user > 0 ) {
            $owned = $comp->get_user_ticket_count( get_current_user_id() );
            $remaining_allowance = max( 0, $max_per_user - $owned );

            if ( $remaining_allowance <= 0 ) {
                wp_send_json_error( [ 'message' => sprintf( 'You already own %d ticket(s) for this competition, which is the maximum allowed.', $owned ) ] );
            }

            if ( $quantity > $remaining_allowance ) {
                wp_send_json_error( [ 'message' => sprintf( 'You already own %d of %d allowed tickets. You can buy up to %d more.', $owned, $max_per_user, $remaining_allowance ) ] );
            }
        }

        if ( $quantity < 1 ) {
            $quantity = 1;
        }

        // Check sufficient tickets remaining
        $remaining = $comp->get_tickets_remaining();
        if ( $quantity > $remaining ) {
            wp_send_json_error( [ 'message' => sprintf( 'Only %d tickets remaining.', $remaining ) ] );
        }

        // Check qualifying question passed
        $qualifying_key = 'txc_qualified_' . $competition_id;
        if ( ! WC()->session->get( $qualifying_key ) ) {
            wp_send_json_error( [ 'message' => 'Please answer the qualifying question first.', 'require_question' => true ] );
        }

        // Check compliance
        $compliance = new TXC_Compliance();
        $age_check = $compliance->check_user_age( get_current_user_id() );
        if ( ! $age_check ) {
            wp_send_json_error( [ 'message' => 'You must be 18 or over to enter competitions.' ] );
        }

        $country_check = $compliance->check_user_country( get_current_user_id() );
        if ( ! $country_check ) {
            wp_send_json_error( [ 'message' => 'Competitions are not available in your country.' ] );
        }

        // Get WC product
        $product_id = $comp->get_wc_product_id();
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => 'Competition product not found.' ] );
        }

        // Remove any existing cart items for this competition
        $this->remove_competition_from_cart( $competition_id );

        // Add to cart with competition meta
        $cart_item_data = [
            'txc_competition_id' => $competition_id,
            'txc_quantity'       => $quantity,
        ];

        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );

        if ( ! $cart_item_key ) {
            wp_send_json_error( [ 'message' => 'Could not add to cart.' ] );
        }

        wp_send_json_success( [
            'message'  => sprintf( '%d ticket(s) added to cart.', $quantity ),
            'cart_url' => wc_get_cart_url(),
        ] );
    }

    /**
     * Display competition info in cart item data.
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['txc_competition_id'] ) ) {
            $comp = new TXC_Competition( $cart_item['txc_competition_id'] );
            $item_data[] = [
                'key'   => 'Competition',
                'value' => $comp->get_title(),
            ];
            $item_data[] = [
                'key'   => 'Draw Date',
                'value' => gmdate( 'd M Y, H:i', strtotime( $comp->get_draw_date() ) ) . ' GMT',
            ];
        }
        return $item_data;
    }

    /**
     * Override cart item name.
     */
    public function cart_item_name( $name, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['txc_competition_id'] ) ) {
            $comp = new TXC_Competition( $cart_item['txc_competition_id'] );
            return esc_html( $comp->get_title() ) . ' — Competition Entry';
        }
        return $name;
    }

    /**
     * Validate cart items before checkout.
     */
    public function validate_cart_items() {
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( empty( $item['txc_competition_id'] ) ) {
                continue;
            }

            $comp = new TXC_Competition( $item['txc_competition_id'] );

            // Check still accepting entries
            if ( ! $comp->can_enter() ) {
                wc_add_notice(
                    sprintf( 'Competition "%s" is no longer accepting entries. It has been removed from your cart.', $comp->get_title() ),
                    'error'
                );
                WC()->cart->remove_cart_item( $key );
                continue;
            }

            // Check quantity still available
            $remaining = $comp->get_tickets_remaining();
            if ( $item['quantity'] > $remaining ) {
                if ( $remaining <= 0 ) {
                    wc_add_notice(
                        sprintf( 'Competition "%s" is sold out. It has been removed from your cart.', $comp->get_title() ),
                        'error'
                    );
                    WC()->cart->remove_cart_item( $key );
                } else {
                    WC()->cart->set_quantity( $key, $remaining );
                    wc_add_notice(
                        sprintf( 'Only %d tickets remain for "%s". Your quantity has been adjusted.', $remaining, $comp->get_title() ),
                        'notice'
                    );
                }
            }

            // Check per-user total limit
            $max = $comp->get_max_per_user();
            if ( $max > 0 && is_user_logged_in() ) {
                $owned = $comp->get_user_ticket_count( get_current_user_id() );
                $remaining_allowance = max( 0, $max - $owned );

                if ( $item['quantity'] > $remaining_allowance ) {
                    if ( $remaining_allowance <= 0 ) {
                        wc_add_notice(
                            sprintf( 'You already own the maximum %d tickets for "%s". It has been removed from your cart.', $max, $comp->get_title() ),
                            'error'
                        );
                        WC()->cart->remove_cart_item( $key );
                    } else {
                        WC()->cart->set_quantity( $key, $remaining_allowance );
                        wc_add_notice(
                            sprintf( 'You already own %d of %d allowed tickets for "%s". Quantity adjusted to %d.', $owned, $max, $comp->get_title(), $remaining_allowance ),
                            'notice'
                        );
                    }
                }
            }
        }
    }

    /**
     * Remove existing cart entries for a competition.
     */
    private function remove_competition_from_cart( $competition_id ) {
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            if ( isset( $item['txc_competition_id'] ) && (int) $item['txc_competition_id'] === $competition_id ) {
                WC()->cart->remove_cart_item( $key );
            }
        }
    }
}
