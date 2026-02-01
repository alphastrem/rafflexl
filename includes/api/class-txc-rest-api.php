<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Rest_API {

    const NAMESPACE = 'txc/v1';

    public function register_routes() {
        register_rest_route( self::NAMESPACE, '/competitions', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_competitions' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/competitions/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_competition' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/competitions/(?P<id>\d+)/tickets-remaining', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_tickets_remaining' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/winners', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_winners' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NAMESPACE, '/competitions/(?P<id>\d+)/draw', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_draw' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * GET /competitions - list active competitions.
     */
    public function get_competitions( $request ) {
        $competitions = TXC_Competition::get_active();
        $data = [];

        foreach ( $competitions as $comp ) {
            $data[] = $this->format_competition( $comp );
        }

        return rest_ensure_response( $data );
    }

    /**
     * GET /competitions/{id} - single competition.
     */
    public function get_competition( $request ) {
        $id = (int) $request['id'];
        $post = get_post( $id );

        if ( ! $post || 'txc_competition' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Competition not found.', [ 'status' => 404 ] );
        }

        $comp = new TXC_Competition( $id );
        return rest_ensure_response( $this->format_competition( $comp, true ) );
    }

    /**
     * GET /competitions/{id}/tickets-remaining - live ticket count.
     */
    public function get_tickets_remaining( $request ) {
        $id = (int) $request['id'];
        $post = get_post( $id );

        if ( ! $post || 'txc_competition' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Competition not found.', [ 'status' => 404 ] );
        }

        $comp = new TXC_Competition( $id );
        return rest_ensure_response( [
            'competition_id'    => $id,
            'tickets_remaining' => $comp->get_tickets_remaining(),
            'tickets_sold'      => $comp->get_tickets_sold(),
            'max_tickets'       => $comp->get_max_tickets(),
        ] );
    }

    /**
     * GET /winners - all drawn competitions with winner info.
     */
    public function get_winners( $request ) {
        $competitions = TXC_Competition::get_drawn();
        $engine = new TXC_Draw_Engine();
        $data = [];

        foreach ( $competitions as $comp ) {
            $winner = $comp->get_winner_display();
            $draw = $engine->get_draw( $comp->get_id() );

            if ( ! $winner || ! $draw ) {
                continue;
            }

            $rejected = [];
            if ( ! empty( $draw->rolls ) ) {
                foreach ( $draw->rolls as $roll ) {
                    if ( 'rejected' === $roll['result'] ) {
                        $rejected[] = [
                            'roll_number' => $roll['roll_number'],
                            'ticket'      => $roll['ticket'],
                            'message'     => $roll['message'],
                        ];
                    }
                }
            }

            $data[] = [
                'competition_id'   => $comp->get_id(),
                'title'            => $comp->get_title(),
                'prize'            => $comp->get_prize_description(),
                'tickets_sold'     => $comp->get_tickets_sold(),
                'winning_ticket'   => $winner['ticket_number'],
                'winner_name'      => $winner['display_name'],
                'draw_date'        => $draw->completed_at,
                'seed_hash'        => $draw->seed_hash,
                'rejected_rolls'   => $rejected,
                'forced_redraw'    => (bool) $draw->forced_redraw,
                'redraw_reason'    => $draw->forced_redraw_reason ?: null,
            ];
        }

        return rest_ensure_response( $data );
    }

    /**
     * GET /competitions/{id}/draw - draw result.
     */
    public function get_draw( $request ) {
        $id = (int) $request['id'];
        $post = get_post( $id );

        if ( ! $post || 'txc_competition' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Competition not found.', [ 'status' => 404 ] );
        }

        $engine = new TXC_Draw_Engine();
        $draw = $engine->get_draw( $id );

        if ( ! $draw ) {
            return new WP_Error( 'no_draw', 'No draw found for this competition.', [ 'status' => 404 ] );
        }

        $comp = new TXC_Competition( $id );
        $winner = $comp->get_winner_display();

        $rolls = [];
        if ( ! empty( $draw->rolls ) ) {
            foreach ( $draw->rolls as $roll ) {
                $rolls[] = [
                    'roll_number' => $roll['roll_number'],
                    'ticket'      => $roll['ticket'],
                    'result'      => $roll['result'],
                    'message'     => $roll['message'] ?? null,
                ];
            }
        }

        return rest_ensure_response( [
            'competition_id'   => $id,
            'status'           => $draw->status,
            'draw_mode'        => $draw->draw_mode,
            'winning_ticket'   => $winner ? $winner['ticket_number'] : null,
            'winner_name'      => $winner ? $winner['display_name'] : null,
            'rolls'            => $rolls,
            'seed_hash'        => $draw->seed_hash,
            'started_at'       => $draw->started_at,
            'completed_at'     => $draw->completed_at,
            'forced_redraw'    => (bool) $draw->forced_redraw,
            'redraw_reason'    => $draw->forced_redraw_reason ?: null,
        ] );
    }

    /**
     * Format a competition for API response.
     */
    private function format_competition( $comp, $full = false ) {
        $data = [
            'id'                => $comp->get_id(),
            'title'             => $comp->get_title(),
            'slug'              => $comp->get_slug(),
            'url'               => get_permalink( $comp->get_id() ),
            'status'            => $comp->get_status(),
            'price'             => $comp->get_price(),
            'max_tickets'       => $comp->get_max_tickets(),
            'tickets_sold'      => $comp->get_tickets_sold(),
            'tickets_remaining' => $comp->get_tickets_remaining(),
            'max_per_user'      => $comp->get_max_per_user(),
            'draw_date'         => $comp->get_draw_date(),
            'draw_mode'         => $comp->get_draw_mode(),
            'prize_types'       => $comp->get_prize_types(),
            'prize_description' => $comp->get_prize_description(),
            'can_enter'         => $comp->can_enter(),
            'featured_image'    => get_the_post_thumbnail_url( $comp->get_id(), 'large' ) ?: null,
        ];

        if ( $full ) {
            $data['description'] = get_the_content( null, false, $comp->get_id() );
            $data['seo_description'] = $comp->get_seo_description();
            $data['must_sell_out'] = $comp->get_must_sell_out();

            $gallery = $comp->get_gallery_ids();
            $data['gallery'] = array_map( function ( $id ) {
                return wp_get_attachment_url( $id ) ?: null;
            }, $gallery );
        }

        return $data;
    }
}
