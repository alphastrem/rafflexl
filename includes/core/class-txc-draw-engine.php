<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Draw_Engine {

    const MAX_ROLLS = 10;
    const SOLD_ONLY_AFTER = 3;

    /**
     * Execute a draw for a competition.
     *
     * @return array|WP_Error Draw result array or error.
     */
    public function execute( $competition_id, $draw_mode = 'manual' ) {
        global $wpdb;
        $draws_table = $wpdb->prefix . 'txc_draws';

        $comp = new TXC_Competition( $competition_id );

        // Validate
        if ( ! $comp->can_draw() ) {
            return new WP_Error( 'cannot_draw', 'This competition is not eligible for drawing.' );
        }

        // Check no existing completed draw (unless force redraw)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$draws_table} WHERE competition_id = %d AND status = 'completed' AND forced_redraw = 0",
            $competition_id
        ) );
        if ( $existing > 0 ) {
            return new WP_Error( 'already_drawn', 'This competition already has a completed draw.' );
        }

        // Generate cryptographic seed
        $seed = bin2hex( random_bytes( 32 ) );
        $seed_hash = hash( 'sha256', $seed );

        // Create draw record
        $comp->set_status( 'drawing' );
        $now = current_time( 'mysql', true );

        $wpdb->insert( $draws_table, [
            'competition_id' => $competition_id,
            'draw_mode'      => $draw_mode,
            'seed'           => $seed,
            'seed_hash'      => $seed_hash,
            'rolls'          => '[]',
            'status'         => 'running',
            'started_at'     => $now,
        ] );
        $draw_id = $wpdb->insert_id;

        // Get sold tickets
        $ticket_manager = new TXC_Ticket_Manager();
        $sold_tickets = array_map( 'intval', $ticket_manager->get_sold_tickets( $competition_id ) );

        if ( empty( $sold_tickets ) ) {
            $this->fail_draw( $draw_id, $competition_id, 'No tickets sold.' );
            return new WP_Error( 'no_tickets', 'No tickets have been sold for this competition.' );
        }

        $max_tickets = $comp->get_max_tickets();
        $rolls = [];
        $winning_ticket = null;

        // Draw sequence
        for ( $roll_num = 1; $roll_num <= self::MAX_ROLLS; $roll_num++ ) {
            if ( $roll_num > self::SOLD_ONLY_AFTER ) {
                // Rolls 4+: pick only from sold tickets
                $idx = random_int( 0, count( $sold_tickets ) - 1 );
                $number = $sold_tickets[ $idx ];
                $is_sold = true;
            } else {
                // Rolls 1-3: pick from entire range
                $number = random_int( 1, $max_tickets );
                $is_sold = in_array( $number, $sold_tickets, true );
            }

            $roll = [
                'roll_number' => $roll_num,
                'ticket'      => $number,
                'is_sold'     => $is_sold,
                'timestamp'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
            ];

            if ( $is_sold ) {
                $roll['result'] = 'winner';
                $rolls[] = $roll;
                $winning_ticket = $number;
                break;
            } else {
                $roll['result'] = 'rejected';
                $roll['message'] = 'No sold ticket';
                $rolls[] = $roll;
            }
        }

        // Update draw record
        $completed_at = current_time( 'mysql', true );

        if ( $winning_ticket !== null ) {
            // Mark winning ticket
            $ticket_manager->mark_winner( $competition_id, $winning_ticket );
            $ticket = $ticket_manager->get_ticket( $competition_id, $winning_ticket );

            $wpdb->update( $draws_table, [
                'winning_ticket_id' => $ticket ? $ticket->id : null,
                'rolls'             => wp_json_encode( $rolls ),
                'status'            => 'completed',
                'completed_at'      => $completed_at,
            ], [ 'id' => $draw_id ] );

            $comp->set_status( 'drawn' );

            return [
                'draw_id'        => $draw_id,
                'competition_id' => $competition_id,
                'winning_ticket' => $winning_ticket,
                'rolls'          => $rolls,
                'seed_hash'      => $seed_hash,
                'status'         => 'completed',
            ];
        }

        // Should not reach here since roll 4+ guarantees a sold ticket
        $this->fail_draw( $draw_id, $competition_id, 'Exhausted all roll attempts.' );
        return new WP_Error( 'draw_failed', 'Draw failed after maximum attempts.' );
    }

    /**
     * Execute a forced redraw with a public reason.
     */
    public function force_redraw( $competition_id, $reason ) {
        global $wpdb;
        $draws_table = $wpdb->prefix . 'txc_draws';

        if ( empty( trim( $reason ) ) ) {
            return new WP_Error( 'reason_required', 'A public reason is required for a forced redraw.' );
        }

        $comp = new TXC_Competition( $competition_id );

        // Reset winner flag on previous winning ticket
        $tickets_table = $wpdb->prefix . 'txc_tickets';
        $wpdb->update( $tickets_table, [ 'is_winner' => 0 ], [ 'competition_id' => $competition_id, 'is_winner' => 1 ] );

        // Generate new seed
        $seed = bin2hex( random_bytes( 32 ) );
        $seed_hash = hash( 'sha256', $seed );
        $now = current_time( 'mysql', true );

        $wpdb->insert( $draws_table, [
            'competition_id'      => $competition_id,
            'draw_mode'           => 'manual',
            'seed'                => $seed,
            'seed_hash'           => $seed_hash,
            'rolls'               => '[]',
            'status'              => 'running',
            'forced_redraw'       => 1,
            'forced_redraw_reason' => sanitize_textarea_field( $reason ),
            'started_at'          => $now,
        ] );
        $draw_id = $wpdb->insert_id;

        // Run draw
        $ticket_manager = new TXC_Ticket_Manager();
        $sold_tickets = array_map( 'intval', $ticket_manager->get_sold_tickets( $competition_id ) );
        $max_tickets = $comp->get_max_tickets();

        $rolls = [];
        $winning_ticket = null;

        for ( $roll_num = 1; $roll_num <= self::MAX_ROLLS; $roll_num++ ) {
            if ( $roll_num > self::SOLD_ONLY_AFTER ) {
                $idx = random_int( 0, count( $sold_tickets ) - 1 );
                $number = $sold_tickets[ $idx ];
                $is_sold = true;
            } else {
                $number = random_int( 1, $max_tickets );
                $is_sold = in_array( $number, $sold_tickets, true );
            }

            $roll = [
                'roll_number' => $roll_num,
                'ticket'      => $number,
                'is_sold'     => $is_sold,
                'timestamp'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
            ];

            if ( $is_sold ) {
                $roll['result'] = 'winner';
                $rolls[] = $roll;
                $winning_ticket = $number;
                break;
            } else {
                $roll['result'] = 'rejected';
                $roll['message'] = 'No sold ticket';
                $rolls[] = $roll;
            }
        }

        $completed_at = current_time( 'mysql', true );

        if ( $winning_ticket !== null ) {
            $ticket_manager->mark_winner( $competition_id, $winning_ticket );
            $ticket = $ticket_manager->get_ticket( $competition_id, $winning_ticket );

            $wpdb->update( $draws_table, [
                'winning_ticket_id' => $ticket ? $ticket->id : null,
                'rolls'             => wp_json_encode( $rolls ),
                'status'            => 'completed',
                'completed_at'      => $completed_at,
            ], [ 'id' => $draw_id ] );

            $comp->set_status( 'drawn' );

            return [
                'draw_id'        => $draw_id,
                'competition_id' => $competition_id,
                'winning_ticket' => $winning_ticket,
                'rolls'          => $rolls,
                'seed_hash'      => $seed_hash,
                'forced_redraw'  => true,
                'redraw_reason'  => $reason,
                'status'         => 'completed',
            ];
        }

        $this->fail_draw( $draw_id, $competition_id, 'Exhausted all roll attempts on redraw.' );
        return new WP_Error( 'redraw_failed', 'Redraw failed after maximum attempts.' );
    }

    /**
     * Get the latest draw for a competition.
     */
    public function get_draw( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_draws';
        $draw = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY id DESC LIMIT 1",
            $competition_id
        ) );

        if ( $draw && ! empty( $draw->rolls ) ) {
            $draw->rolls = json_decode( $draw->rolls, true );
        }

        return $draw;
    }

    /**
     * Get all draws for a competition (includes redraws).
     */
    public function get_all_draws( $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_draws';
        $draws = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY id ASC",
            $competition_id
        ) );

        foreach ( $draws as &$draw ) {
            if ( ! empty( $draw->rolls ) ) {
                $draw->rolls = json_decode( $draw->rolls, true );
            }
        }

        return $draws;
    }

    /**
     * Mark a draw as failed.
     */
    private function fail_draw( $draw_id, $competition_id, $reason = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_draws';

        $wpdb->update( $table, [
            'status'       => 'failed',
            'completed_at' => current_time( 'mysql', true ),
        ], [ 'id' => $draw_id ] );

        $comp = new TXC_Competition( $competition_id );
        $comp->set_status( 'failed' );

        // Schedule retry
        wp_schedule_single_event( time() + 120, 'txc_draw_retry_event', [ $competition_id ] );
    }
}
