<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Shortcodes {

    public function register_shortcodes() {
        add_shortcode( 'txc_competitions', [ $this, 'competitions_grid' ] );
        add_shortcode( 'txc_competition', [ $this, 'single_competition' ] );
        add_shortcode( 'txc_winners', [ $this, 'winners_history' ] );
        add_shortcode( 'txc_my_competitions', [ $this, 'my_competitions' ] );
    }

    /**
     * [txc_competitions] - Grid of active competitions.
     */
    public function competitions_grid( $atts ) {
        $atts = shortcode_atts( [
            'status' => 'active',
            'limit'  => -1,
        ], $atts );

        if ( 'drawn' === $atts['status'] ) {
            $competitions = TXC_Competition::get_drawn();
        } else {
            $competitions = TXC_Competition::get_active();
        }

        if ( $atts['limit'] > 0 ) {
            $competitions = array_slice( $competitions, 0, (int) $atts['limit'] );
        }

        ob_start();
        ?>
        <div class="txc-competitions-grid">
            <?php if ( empty( $competitions ) ) : ?>
                <p class="txc-no-competitions">No competitions currently available.</p>
            <?php endif; ?>

            <?php foreach ( $competitions as $comp ) :
                $permalink = get_permalink( $comp->get_id() );
                $thumb = get_the_post_thumbnail_url( $comp->get_id(), 'medium' );
                $time = $comp->get_time_until_draw();
                $status = $comp->get_status();
            ?>
                <div class="txc-competition-card">
                    <a href="<?php echo esc_url( $permalink ); ?>" class="txc-card-link">
                        <div class="txc-card-image">
                            <?php if ( $thumb ) : ?>
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $comp->get_title() ); ?>" />
                            <?php else : ?>
                                <div class="txc-card-placeholder"></div>
                            <?php endif; ?>
                            <span class="txc-card-badge txc-badge-<?php echo esc_attr( $status ); ?>">
                                <?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?>
                            </span>
                        </div>
                        <div class="txc-card-body">
                            <h3 class="txc-card-title"><?php echo esc_html( $comp->get_title() ); ?></h3>
                            <div class="txc-card-price">&pound;<?php echo esc_html( number_format( $comp->get_price(), 2 ) ); ?></div>
                            <div class="txc-card-tickets">
                                <?php echo esc_html( $comp->get_tickets_sold() ); ?> / <?php echo esc_html( $comp->get_max_tickets() ); ?> sold
                            </div>
                            <?php if ( $time && ! $time['expired'] ) : ?>
                                <div class="txc-card-countdown" x-data="txcCountdown('<?php echo esc_attr( str_replace( ' ', 'T', $comp->get_draw_date() ) . 'Z' ); ?>')" x-init="start()">
                                    <span x-text="display"></span>
                                </div>
                            <?php elseif ( 'drawn' === $status ) : ?>
                                <div class="txc-card-drawn">Draw Complete</div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [txc_competition id="X"] - Single competition embed.
     */
    public function single_competition( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts );
        $id = absint( $atts['id'] );

        if ( ! $id ) {
            return '<p>Competition not found.</p>';
        }

        $comp = new TXC_Competition( $id );
        if ( ! get_post( $id ) || 'txc_competition' !== get_post_type( $id ) ) {
            return '<p>Competition not found.</p>';
        }

        ob_start();
        include TXC_PLUGIN_DIR . 'includes/public/views/single-competition-content.php';
        return ob_get_clean();
    }

    /**
     * [txc_winners] - Winners history.
     */
    public function winners_history( $atts ) {
        $competitions = TXC_Competition::get_drawn();
        $engine = new TXC_Draw_Engine();

        ob_start();
        ?>
        <div class="txc-winners-list">
            <h2 class="txc-winners-heading">Previous Winners</h2>

            <?php if ( empty( $competitions ) ) : ?>
                <p>No winners to display yet.</p>
            <?php endif; ?>

            <?php foreach ( $competitions as $comp ) :
                $winner = $comp->get_winner_display();
                $draw = $engine->get_draw( $comp->get_id() );
                if ( ! $winner || ! $draw ) continue;
            ?>
                <div class="txc-winner-card">
                    <div class="txc-winner-comp">
                        <h3><?php echo esc_html( $comp->get_title() ); ?></h3>
                        <span class="txc-winner-prize"><?php echo esc_html( $comp->get_prize_description() ); ?></span>
                    </div>
                    <div class="txc-winner-details">
                        <div class="txc-winner-ticket">
                            <strong>Winning Ticket:</strong> #<?php echo esc_html( $winner['ticket_number'] ); ?>
                        </div>
                        <div class="txc-winner-name">
                            <strong>Winner:</strong> <?php echo esc_html( $winner['display_name'] ); ?>
                        </div>
                        <div class="txc-winner-meta">
                            <span><strong>Date:</strong> <?php echo esc_html( gmdate( 'd M Y, H:i', strtotime( $draw->completed_at ) ) ); ?> GMT</span>
                            <span><strong>Competition ID:</strong> <?php echo esc_html( $comp->get_id() ); ?></span>
                            <span><strong>Tickets Sold:</strong> <?php echo esc_html( $comp->get_tickets_sold() ); ?></span>
                        </div>

                        <?php if ( $draw->forced_redraw && $draw->forced_redraw_reason ) : ?>
                            <div class="txc-winner-redraw-notice">
                                <strong>Redraw Notice:</strong> <?php echo esc_html( $draw->forced_redraw_reason ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $draw->rolls ) ) :
                            $rejected = array_filter( $draw->rolls, function( $r ) { return 'rejected' === $r['result']; } );
                            if ( ! empty( $rejected ) ) :
                        ?>
                            <div class="txc-winner-rejected">
                                <strong>Rejected Rolls:</strong>
                                <?php foreach ( $rejected as $r ) : ?>
                                    <span class="txc-rejected-roll">#<?php echo esc_html( $r['ticket'] ); ?> (not sold)</span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endif; ?>

                        <div class="txc-winner-hash">
                            <strong>Verification Hash:</strong> <code><?php echo esc_html( $draw->seed_hash ); ?></code>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [txc_my_competitions] - User's competitions.
     */
    public function my_competitions( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your competitions.</p>';
        }

        $public = new TXC_Public();
        ob_start();
        $public->account_competitions_page();
        return ob_get_clean();
    }
}
