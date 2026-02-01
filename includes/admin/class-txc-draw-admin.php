<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Draw_Admin {

    /**
     * AJAX: trigger a manual draw.
     */
    public function handle_manual_draw() {
        check_ajax_referer( 'txc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'txc_manage_draws' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        if ( ! $competition_id ) {
            wp_send_json_error( [ 'message' => 'Invalid competition.' ] );
        }

        $engine = new TXC_Draw_Engine();
        $result = $engine->execute( $competition_id, 'manual' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: force a redraw with reason.
     */
    public function handle_force_redraw() {
        check_ajax_referer( 'txc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'txc_manage_draws' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );

        if ( ! $competition_id ) {
            wp_send_json_error( [ 'message' => 'Invalid competition.' ] );
        }

        if ( empty( trim( $reason ) ) ) {
            wp_send_json_error( [ 'message' => 'A public reason for the redraw is required.' ] );
        }

        $engine = new TXC_Draw_Engine();
        $result = $engine->force_redraw( $competition_id, $reason );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * Render draws management page.
     */
    public function render_page() {
        if ( ! current_user_can( 'txc_manage_draws' ) ) {
            wp_die( 'Unauthorized' );
        }

        // Get competitions ready for draw or already drawn
        $competitions = get_posts( [
            'post_type'      => 'txc_competition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_txc_status',
                    'value'   => [ 'live', 'sold_out', 'drawing', 'drawn', 'failed' ],
                    'compare' => 'IN',
                ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => '_txc_draw_date',
            'order'    => 'ASC',
        ] );

        $engine = new TXC_Draw_Engine();
        $can_delete = current_user_can( 'txc_delete_competitions' );
        ?>
        <div class="wrap">
            <h1>Competition Draws</h1>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Competition</th>
                        <th>Status</th>
                        <th>Draw Date (GMT)</th>
                        <th>Mode</th>
                        <th>Tickets Sold</th>
                        <th>Winner</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $competitions as $post ) :
                        $comp = new TXC_Competition( $post->ID );
                        $status = $comp->get_status();
                        $draw = $engine->get_draw( $post->ID );
                        $winner = $comp->get_winner_display();
                    ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $comp->get_title() ); ?></a></strong>
                                <br /><small>#<?php echo esc_html( $post->ID ); ?></small>
                            </td>
                            <td>
                                <?php
                                $badge_colors = [
                                    'live'     => '#00a32a',
                                    'sold_out' => '#2271b1',
                                    'drawing'  => '#dba617',
                                    'drawn'    => '#135e96',
                                    'failed'   => '#d63638',
                                ];
                                $color = $badge_colors[ $status ] ?? '#666';
                                echo '<span style="color:' . esc_attr( $color ) . ';font-weight:bold;">' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) ) . '</span>';
                                ?>
                            </td>
                            <td><?php echo esc_html( $comp->get_draw_date() ? gmdate( 'd M Y H:i', strtotime( $comp->get_draw_date() ) ) : '—' ); ?></td>
                            <td><?php echo esc_html( ucfirst( $comp->get_draw_mode() ) ); ?></td>
                            <td><?php echo esc_html( $comp->get_tickets_sold() . ' / ' . $comp->get_max_tickets() ); ?></td>
                            <td>
                                <?php if ( $winner ) : ?>
                                    Ticket #<?php echo esc_html( $winner['ticket_number'] ); ?> — <?php echo esc_html( $winner['display_name'] ); ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( in_array( $status, [ 'live', 'sold_out' ], true ) && $comp->can_draw() ) : ?>
                                    <button type="button" class="button txc-draw-btn" data-competition="<?php echo esc_attr( $post->ID ); ?>">
                                        Draw Now
                                    </button>
                                <?php endif; ?>

                                <?php if ( 'drawn' === $status ) : ?>
                                    <button type="button" class="button txc-redraw-btn" data-competition="<?php echo esc_attr( $post->ID ); ?>">
                                        Force Redraw
                                    </button>
                                <?php endif; ?>

                                <?php if ( 'failed' === $status ) : ?>
                                    <button type="button" class="button button-primary txc-draw-btn" data-competition="<?php echo esc_attr( $post->ID ); ?>">
                                        Retry Draw
                                    </button>
                                <?php endif; ?>

                                <?php if ( $draw ) : ?>
                                    <button type="button" class="button txc-view-draw-btn" data-draw='<?php echo esc_attr( wp_json_encode( [
                                        'rolls'     => $draw->rolls,
                                        'seed_hash' => $draw->seed_hash,
                                        'status'    => $draw->status,
                                        'forced_redraw_reason' => $draw->forced_redraw_reason,
                                    ] ) ); ?>'>
                                        View Draw
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ( empty( $competitions ) ) : ?>
                        <tr><td colspan="7">No competitions available for drawing.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Redraw reason modal -->
        <div id="txc-redraw-modal" style="display:none;">
            <div class="txc-modal-overlay">
                <div class="txc-modal-content">
                    <h3>Force Redraw</h3>
                    <p>Enter a public reason for this redraw. This will be displayed alongside the new result.</p>
                    <textarea id="txc-redraw-reason" rows="3" style="width:100%;"></textarea>
                    <p>
                        <button type="button" class="button button-primary" id="txc-redraw-confirm">Confirm Redraw</button>
                        <button type="button" class="button" id="txc-redraw-cancel">Cancel</button>
                    </p>
                </div>
            </div>
        </div>

        <!-- Draw detail modal -->
        <div id="txc-draw-detail-modal" style="display:none;">
            <div class="txc-modal-overlay">
                <div class="txc-modal-content">
                    <h3>Draw Details</h3>
                    <div id="txc-draw-detail-content"></div>
                    <p><button type="button" class="button" id="txc-draw-detail-close">Close</button></p>
                </div>
            </div>
        </div>
        <?php
    }
}
