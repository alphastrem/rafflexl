<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Ticket_Manager {

    /**
     * Allocate random ticket numbers for a competition purchase.
     *
     * @return array|WP_Error Array of ticket rows on success, WP_Error on failure.
     */
    public function allocate( $competition_id, $order_id, $user_id, $quantity ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        $comp = new TXC_Competition( $competition_id );
        $max_tickets = $comp->get_max_tickets();

        if ( $max_tickets < 1 ) {
            return new WP_Error( 'invalid_competition', 'Competition has no ticket cap set.' );
        }

        // Get all already-allocated ticket numbers
        $taken = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$table} WHERE competition_id = %d",
            $competition_id
        ) );
        $taken = array_map( 'intval', $taken );

        // Build pool of available numbers
        $all = range( 1, $max_tickets );
        $available = array_diff( $all, $taken );
        $available = array_values( $available );

        if ( count( $available ) < $quantity ) {
            return new WP_Error( 'sold_out', sprintf( 'Only %d tickets remaining.', count( $available ) ) );
        }

        // Pick random ticket numbers using CSPRNG
        $selected = [];
        $pool = $available;
        for ( $i = 0; $i < $quantity; $i++ ) {
            $idx = random_int( 0, count( $pool ) - 1 );
            $selected[] = $pool[ $idx ];
            array_splice( $pool, $idx, 1 );
        }

        sort( $selected );

        // Insert tickets with unique index protection
        $allocated = [];
        $now = current_time( 'mysql', true );

        foreach ( $selected as $number ) {
            $result = $wpdb->insert( $table, [
                'competition_id' => $competition_id,
                'order_id'       => $order_id,
                'user_id'        => $user_id,
                'ticket_number'  => $number,
                'is_winner'      => 0,
                'is_instant_win' => 0,
                'allocated_at'   => $now,
            ] );

            if ( false === $result ) {
                // Unique constraint violation - race condition
                // Rollback allocated tickets for this order
                $wpdb->delete( $table, [
                    'order_id'       => $order_id,
                    'competition_id' => $competition_id,
                ] );
                return new WP_Error( 'allocation_conflict', 'Ticket allocation conflict. Please try again.' );
            }

            $allocated[] = (object) [
                'id'            => $wpdb->insert_id,
                'ticket_number' => $number,
                'competition_id' => $competition_id,
                'user_id'       => $user_id,
                'order_id'      => $order_id,
            ];
        }

        return $allocated;
    }

    /**
     * Get all sold ticket numbers for a competition.
     */
    public function get_sold_tickets( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$table} WHERE competition_id = %d ORDER BY ticket_number ASC",
            $competition_id
        ) );
    }

    /**
     * Get a specific ticket by competition and number.
     */
    public function get_ticket( $competition_id, $ticket_number ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d AND ticket_number = %d",
            $competition_id,
            $ticket_number
        ) );
    }

    /**
     * Mark a ticket as the main draw winner.
     */
    public function mark_winner( $competition_id, $ticket_number ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->update(
            $table,
            [ 'is_winner' => 1 ],
            [ 'competition_id' => $competition_id, 'ticket_number' => $ticket_number ]
        );
    }

    /**
     * Mark a ticket as an instant win.
     */
    public function mark_instant_win( $ticket_id, $prize_type, $prize_value, $prize_label ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->update(
            $table,
            [
                'is_instant_win'        => 1,
                'instant_win_prize_type' => $prize_type,
                'instant_win_prize_value' => $prize_value,
                'instant_win_prize_label' => $prize_label,
            ],
            [ 'id' => $ticket_id ]
        );
    }

    /**
     * Get all tickets for an order.
     */
    public function get_order_tickets( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d ORDER BY competition_id ASC, ticket_number ASC",
            $order_id
        ) );
    }

    /**
     * Get all tickets for a user across all competitions.
     */
    public function get_user_tickets( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.*, p.post_title as competition_title
            FROM {$table} t
            LEFT JOIN {$wpdb->posts} p ON t.competition_id = p.ID
            WHERE t.user_id = %d
            ORDER BY t.allocated_at DESC",
            $user_id
        ) );
    }

    /**
     * Delete all tickets for a competition (used in hard delete).
     */
    public function delete_competition_tickets( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_tickets';
        return $wpdb->delete( $table, [ 'competition_id' => $competition_id ] );
    }
}
