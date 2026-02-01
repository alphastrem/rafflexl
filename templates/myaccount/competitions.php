<?php
/**
 * Template: My Account - My Competitions
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="txc-my-competitions">
    <h2>My Competitions</h2>

    <?php if ( $credit > 0 ) : ?>
        <div class="txc-credit-balance">
            <strong>Store Credit Balance:</strong> &pound;<?php echo esc_html( number_format( $credit, 2 ) ); ?>
        </div>
    <?php endif; ?>

    <?php if ( empty( $grouped ) ) : ?>
        <p>You haven't entered any competitions yet.</p>
    <?php endif; ?>

    <?php foreach ( $grouped as $cid => $data ) :
        $c = $data['competition'];
        $tickets = $data['tickets'];
        $c_status = $c->get_status();
        $c_winner = $c->get_winner_display();
    ?>
        <div class="txc-my-comp-card">
            <div class="txc-my-comp-header">
                <h3><a href="<?php echo esc_url( get_permalink( $cid ) ); ?>"><?php echo esc_html( $c->get_title() ); ?></a></h3>
                <span class="txc-status-badge txc-badge-<?php echo esc_attr( $c_status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $c_status ) ) ); ?></span>
            </div>

            <div class="txc-my-comp-details">
                <span><strong>Draw:</strong> <?php echo esc_html( $c->get_draw_date() ? gmdate( 'd M Y, H:i', strtotime( $c->get_draw_date() ) ) . ' GMT' : 'TBC' ); ?></span>
                <span><strong>Prize:</strong> <?php echo esc_html( $c->get_prize_description() ); ?></span>
            </div>

            <div class="txc-my-tickets">
                <strong>Your Tickets (<?php echo count( $tickets ); ?>):</strong>
                <div class="txc-ticket-numbers">
                    <?php foreach ( $tickets as $ticket ) : ?>
                        <span class="txc-ticket-num <?php echo $ticket->is_winner ? 'txc-ticket-winner' : ''; ?> <?php echo $ticket->is_instant_win ? 'txc-ticket-iw' : ''; ?>">
                            #<?php echo esc_html( $ticket->ticket_number ); ?>
                            <?php if ( $ticket->is_winner ) : ?><small>(WINNER)</small><?php endif; ?>
                            <?php if ( $ticket->is_instant_win ) : ?><small>(Instant Win: <?php echo esc_html( $ticket->instant_win_prize_label ); ?>)</small><?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ( 'drawn' === $c_status && $c_winner ) : ?>
                <div class="txc-my-comp-result">
                    <strong>Winner:</strong> Ticket #<?php echo esc_html( $c_winner['ticket_number'] ); ?> — <?php echo esc_html( $c_winner['display_name'] ); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ( ! empty( $instant_wins ) ) : ?>
        <h2>Instant Wins</h2>
        <table class="txc-iw-table">
            <thead>
                <tr>
                    <th>Competition</th>
                    <th>Ticket</th>
                    <th>Prize</th>
                    <th>Won</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $instant_wins as $iw ) : ?>
                    <tr>
                        <td><?php echo esc_html( $iw->competition_title ); ?></td>
                        <td>#<?php echo esc_html( $iw->ticket_number ); ?></td>
                        <td><?php echo esc_html( $iw->prize_label ); ?></td>
                        <td><?php echo esc_html( $iw->claimed_at ? gmdate( 'd M Y', strtotime( $iw->claimed_at ) ) : '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
