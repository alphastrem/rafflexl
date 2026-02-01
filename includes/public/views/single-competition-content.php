<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status = $comp->get_status();
$can_enter = $comp->can_enter();
$is_drawn = 'drawn' === $status;
$gallery = $comp->get_gallery_ids();
$youtube_url = $comp->get_youtube_url();
$user_tickets = is_user_logged_in() ? $comp->get_user_tickets( get_current_user_id() ) : [];
$engine = new TXC_Draw_Engine();
$draw = $engine->get_draw( $comp->get_id() );
$winner = $comp->get_winner_display();
?>

<?php
$effective_max = $comp->get_max_per_user();
if ( is_user_logged_in() && $effective_max > 0 ) {
    $effective_max = max( 0, $effective_max - $comp->get_user_ticket_count( get_current_user_id() ) );
}
?>
<div class="txc-single-competition" x-data="txcCompetition(<?php echo esc_attr( wp_json_encode( [
    'id'            => $comp->get_id(),
    'maxPerUser'    => $effective_max,
    'remaining'     => $comp->get_tickets_remaining(),
    'canEnter'      => $can_enter,
    'isDrawn'       => $is_drawn,
    'drawDate'      => $comp->get_draw_date(),
    'isLoggedIn'    => is_user_logged_in(),
] ) ); ?>)">

    <!-- Gallery -->
    <?php if ( ! empty( $gallery ) || has_post_thumbnail( $comp->get_id() ) ) : ?>
        <div class="txc-gallery">
            <?php if ( has_post_thumbnail( $comp->get_id() ) ) : ?>
                <div class="txc-gallery-main">
                    <?php echo get_the_post_thumbnail( $comp->get_id(), 'large' ); ?>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $gallery ) ) : ?>
                <div class="txc-gallery-thumbs">
                    <?php foreach ( $gallery as $img_id ) : ?>
                        <div class="txc-gallery-thumb"><?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Competition Info -->
    <div class="txc-comp-info">
        <h1 class="txc-comp-title"><?php echo esc_html( $comp->get_title() ); ?></h1>

        <div class="txc-comp-prize">
            <h3>Prize</h3>
            <p><?php echo esc_html( $comp->get_prize_description() ); ?></p>
            <div class="txc-prize-types">
                <?php foreach ( $comp->get_prize_types() as $type ) : ?>
                    <span class="txc-prize-badge"><?php echo esc_html( ucfirst( $type ) ); ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="txc-comp-stats">
            <div class="txc-stat">
                <span class="txc-stat-label">Price</span>
                <span class="txc-stat-value">&pound;<?php echo esc_html( number_format( $comp->get_price(), 2 ) ); ?></span>
            </div>
            <div class="txc-stat">
                <span class="txc-stat-label">Tickets</span>
                <span class="txc-stat-value" x-text="remaining + ' / <?php echo esc_attr( $comp->get_max_tickets() ); ?> remaining'">
                    <?php echo esc_html( $comp->get_tickets_remaining() . ' / ' . $comp->get_max_tickets() ); ?> remaining
                </span>
            </div>
            <div class="txc-stat">
                <span class="txc-stat-label">Max Per User</span>
                <span class="txc-stat-value"><?php echo esc_html( $comp->get_max_per_user() ); ?></span>
            </div>
        </div>

        <?php if ( is_user_logged_in() && $comp->get_max_per_user() > 0 ) :
            $user_owned = $comp->get_user_ticket_count( get_current_user_id() );
            $user_remaining = max( 0, $comp->get_max_per_user() - $user_owned );
        ?>
            <div class="txc-user-allowance">
                You have <strong><?php echo esc_html( $user_owned ); ?></strong> ticket(s).
                <?php if ( $user_remaining > 0 ) : ?>
                    You can buy up to <strong><?php echo esc_html( $user_remaining ); ?></strong> more.
                <?php else : ?>
                    You have reached the maximum for this competition.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Countdown -->
        <?php if ( $comp->get_draw_date() && ! $is_drawn ) : ?>
            <div class="txc-countdown" x-data="txcCountdown('<?php echo esc_attr( str_replace( ' ', 'T', $comp->get_draw_date() ) . 'Z' ); ?>')" x-init="start()">
                <h3>Draw In</h3>
                <div class="txc-countdown-display">
                    <div class="txc-cd-unit"><span x-text="days" class="txc-cd-num">0</span><span class="txc-cd-label">Days</span></div>
                    <div class="txc-cd-unit"><span x-text="hours" class="txc-cd-num">0</span><span class="txc-cd-label">Hours</span></div>
                    <div class="txc-cd-unit"><span x-text="minutes" class="txc-cd-num">0</span><span class="txc-cd-label">Mins</span></div>
                    <div class="txc-cd-unit"><span x-text="seconds" class="txc-cd-num">0</span><span class="txc-cd-label">Secs</span></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Watch Draw Link -->
        <?php if ( ! empty( $youtube_url ) ) : ?>
            <div class="txc-watch-draw">
                <a href="<?php echo esc_url( $youtube_url ); ?>" target="_blank" rel="noopener noreferrer" class="txc-btn txc-btn-watch-draw">
                    Watch Draw on YouTube
                </a>
            </div>
        <?php endif; ?>

        <!-- Entry Form -->
        <?php if ( $can_enter ) : ?>
            <div class="txc-entry-form">
                <?php if ( ! is_user_logged_in() ) : ?>
                    <p><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="txc-btn txc-btn-primary">Log In to Enter</a></p>
                <?php else : ?>
                    <div class="txc-quantity-select">
                        <label for="txc-qty">Number of tickets:</label>
                        <div class="txc-qty-slider" x-show="maxPerUser > 1">
                            <input type="range" min="1" max="<?php echo esc_attr( $effective_max ); ?>" value="1" x-model.number="quantity" class="txc-range-slider" />
                            <div class="txc-qty-slider-labels">
                                <span>1</span>
                                <span x-text="maxPerUser"><?php echo esc_html( $effective_max ); ?></span>
                            </div>
                        </div>
                        <div class="txc-qty-controls">
                            <button type="button" class="txc-qty-btn" @click="quantity = Math.max(1, quantity - 1)">-</button>
                            <input type="number" id="txc-qty" x-model.number="quantity" min="1" max="<?php echo esc_attr( $effective_max ); ?>" value="1" class="txc-qty-input" />
                            <button type="button" class="txc-qty-btn" @click="quantity = Math.min(maxPerUser, quantity + 1)">+</button>
                        </div>
                        <div class="txc-total">Total: &pound;<span x-text="(quantity * <?php echo esc_attr( $comp->get_price() ); ?>).toFixed(2)"><?php echo esc_html( number_format( $comp->get_price(), 2 ) ); ?></span></div>
                    </div>
                    <button type="button" class="txc-btn txc-btn-primary txc-enter-btn" @click="enterCompetition()" :disabled="loading" x-text="loading ? 'Processing...' : 'Enter Now'">
                        Enter Now
                    </button>
                    <p class="txc-entry-message" x-show="message" x-text="message" :class="{'txc-success': success, 'txc-error': !success}"></p>
                <?php endif; ?>
            </div>
        <?php elseif ( 'sold_out' === $status ) : ?>
            <div class="txc-sold-out-banner">SOLD OUT</div>
        <?php endif; ?>

        <!-- Your Tickets -->
        <?php if ( ! empty( $user_tickets ) ) : ?>
            <div class="txc-your-tickets">
                <h3>Your Tickets</h3>
                <div class="txc-ticket-numbers">
                    <?php foreach ( $user_tickets as $ticket ) : ?>
                        <span class="txc-ticket-num <?php echo $ticket->is_winner ? 'txc-ticket-winner' : ''; ?> <?php echo $ticket->is_instant_win ? 'txc-ticket-iw' : ''; ?>">
                            #<?php echo esc_html( $ticket->ticket_number ); ?>
                            <?php if ( $ticket->is_winner ) echo ' &#x1f3c6;'; ?>
                            <?php if ( $ticket->is_instant_win ) echo ' &#x2b50;'; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Instant Wins -->
        <?php
        if ( txc_addon_enabled( 'instantwins' ) && $comp->get_instant_wins_count() > 0 ) :
            $instant_wins = TXC_Instant_Wins::get_competition_wins( $comp->get_id() );
            if ( ! empty( $instant_wins ) ) :
        ?>
            <div class="txc-instant-wins-display">
                <h3>Instant Win Prizes</h3>
                <table class="txc-iw-public-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Prize</th>
                            <th>Status</th>
                            <th>Winner</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $instant_wins as $iw ) : ?>
                            <tr class="<?php echo $iw['claimed'] ? 'txc-iw-claimed' : 'txc-iw-unclaimed'; ?>">
                                <td class="txc-iw-ticket">#<?php echo esc_html( $iw['ticket_number'] ); ?></td>
                                <td class="txc-iw-prize"><?php echo esc_html( $iw['prize_label'] ); ?></td>
                                <td class="txc-iw-status">
                                    <?php if ( $iw['claimed'] ) : ?>
                                        <span class="txc-iw-badge-claimed">Claimed</span>
                                    <?php else : ?>
                                        <span class="txc-iw-badge-unclaimed">Unclaimed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="txc-iw-winner">
                                    <?php echo $iw['claimed'] ? esc_html( $iw['winner_name'] ) : '&mdash;'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php
            endif;
        endif;
        ?>

        <!-- Draw Result -->
        <?php if ( $is_drawn && $winner && $draw ) : ?>
            <div class="txc-draw-result">
                <h3>Draw Result</h3>
                <div class="txc-winner-display">
                    <div class="txc-winner-ticket-num">Winning Ticket: <strong>#<?php echo esc_html( $winner['ticket_number'] ); ?></strong></div>
                    <div class="txc-winner-name-display">Winner: <strong><?php echo esc_html( $winner['display_name'] ); ?></strong></div>
                    <div class="txc-winner-date">Drawn: <?php echo esc_html( gmdate( 'd M Y, H:i', strtotime( $draw->completed_at ) ) ); ?> GMT</div>
                    <div class="txc-winner-prize-display">Prize: <?php echo esc_html( $comp->get_prize_description() ); ?></div>
                </div>

                <?php if ( $draw->forced_redraw && $draw->forced_redraw_reason ) : ?>
                    <div class="txc-redraw-notice">
                        <strong>Redraw Notice:</strong> <?php echo esc_html( $draw->forced_redraw_reason ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $draw->rolls ) ) :
                    $rejected = array_filter( $draw->rolls, function( $r ) { return 'rejected' === $r['result']; } );
                    if ( ! empty( $rejected ) ) :
                ?>
                    <div class="txc-rejected-rolls">
                        <h4>Rejected Rolls</h4>
                        <?php foreach ( $rejected as $r ) : ?>
                            <span class="txc-rejected-roll">Roll <?php echo esc_html( $r['roll_number'] ); ?>: #<?php echo esc_html( $r['ticket'] ); ?> — <?php echo esc_html( $r['message'] ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; endif; ?>

                <div class="txc-verification-hash">
                    <strong>Verification Hash:</strong> <code><?php echo esc_html( $draw->seed_hash ); ?></code>
                </div>
            </div>
        <?php elseif ( 'failed' === $status ) : ?>
            <div class="txc-draw-failed">
                Draw failed, please standby for admin comms.
            </div>
        <?php endif; ?>

        <!-- Description -->
        <div class="txc-comp-description">
            <?php echo wp_kses_post( get_the_content( null, false, $comp->get_id() ) ); ?>
        </div>

        <!-- Social -->
        <div class="txc-comp-social">
            <?php echo TXC_Social::render_share_buttons( $comp->get_id() ); ?>
            <?php echo TXC_Social::render_links(); ?>
        </div>
    </div>
</div>

<!-- Qualifying Question Modal -->
<div class="txc-modal-overlay" x-show="showQuestion" x-cloak @click.self="showQuestion = false">
    <div class="txc-modal" x-data="txcQualifying()" x-show="showQuestion">
        <div class="txc-modal-header">
            <h3>Qualifying Question</h3>
            <div class="txc-timer" x-show="timerActive">
                <span x-text="timeLeft" :class="{'txc-timer-warning': timeLeft <= 10}"></span>s
            </div>
        </div>
        <div class="txc-modal-body">
            <p class="txc-q-text" x-text="questionText"></p>
            <div class="txc-q-options">
                <template x-for="(label, key) in options" :key="key">
                    <button class="txc-q-option" :class="{'selected': selectedAnswer === key}" @click="selectAnswer(key)" :disabled="answered" x-text="key.toUpperCase() + ': ' + label"></button>
                </template>
            </div>
            <button class="txc-btn txc-btn-primary" @click="submitAnswer()" :disabled="!selectedAnswer || answered || loading" x-show="!answered">Submit Answer</button>
            <p class="txc-q-result" x-show="resultMessage" x-text="resultMessage" :class="{'txc-success': resultCorrect, 'txc-error': !resultCorrect}"></p>
            <p x-show="cooldownActive" class="txc-cooldown">Cooldown: <span x-text="cooldownDisplay"></span></p>
        </div>
    </div>
</div>
