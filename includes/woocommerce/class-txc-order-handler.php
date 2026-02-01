<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Order_Handler {

    /**
     * Allocate tickets when order payment is confirmed.
     */
    public function allocate_tickets( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Prevent double allocation
        if ( $order->get_meta( '_txc_tickets_allocated' ) === 'yes' ) {
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return;
        }

        $ticket_manager = new TXC_Ticket_Manager();
        $all_allocated = [];

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();

            // Find competition linked to this product
            $competition_id = $this->find_competition_by_product( $product_id );
            if ( ! $competition_id ) {
                continue;
            }

            $comp = new TXC_Competition( $competition_id );

            // Allocate tickets
            $tickets = $ticket_manager->allocate(
                $competition_id,
                $order_id,
                $user_id,
                $quantity
            );

            if ( is_wp_error( $tickets ) ) {
                // Allocation failed - likely sold out race condition
                $order->add_order_note(
                    sprintf(
                        'Ticket allocation failed for competition #%d: %s. Initiating refund for this item.',
                        $competition_id,
                        $tickets->get_error_message()
                    )
                );
                $this->refund_competition_item( $order, $item, $comp );
                continue;
            }

            $all_allocated[ $competition_id ] = $tickets;

            // Store ticket info in order item meta
            $ticket_numbers = wp_list_pluck( $tickets, 'ticket_number' );
            $item->add_meta_data( '_txc_ticket_numbers', $ticket_numbers );
            $item->add_meta_data( '_txc_competition_id', $competition_id );
            $item->save();

            // Update competition sold count
            $comp->increment_tickets_sold( count( $tickets ) );
        }

        $order->update_meta_data( '_txc_tickets_allocated', 'yes' );
        $order->save();

        // Fire action for email and instant win checks
        if ( ! empty( $all_allocated ) ) {
            do_action( 'txc_tickets_allocated', $order_id, $user_id, $all_allocated );
        }
    }

    /**
     * Find competition ID from WC product ID.
     */
    private function find_competition_by_product( $product_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_txc_wc_product_id' AND meta_value = %d LIMIT 1",
            $product_id
        ) );
    }

    /**
     * Refund a specific order item.
     */
    private function refund_competition_item( $order, $item, $comp ) {
        $refund_amount = $item->get_total();

        $refund = wc_create_refund( [
            'amount'     => $refund_amount,
            'reason'     => sprintf( 'Automatic refund: competition "%s" sold out during processing.', $comp->get_title() ),
            'order_id'   => $order->get_id(),
            'line_items' => [
                $item->get_id() => [
                    'qty'          => $item->get_quantity(),
                    'refund_total' => $refund_amount,
                ],
            ],
        ] );

        if ( is_wp_error( $refund ) ) {
            $order->add_order_note( 'Auto-refund failed: ' . $refund->get_error_message() );
        }
    }
}
