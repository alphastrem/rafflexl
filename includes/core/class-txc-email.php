<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Email {

    /**
     * Send ticket allocation confirmation email.
     *
     * @param int   $order_id       WooCommerce order ID.
     * @param int   $user_id        WordPress user ID.
     * @param array $all_allocated   Keyed by competition_id => array of ticket objects.
     */
    public function send_ticket_confirmation( $order_id, $user_id, $all_allocated ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( '[%s] Your competition tickets — Order #%d', $site_name, $order_id );

        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <h2 style="color:#333;">Your Competition Tickets</h2>
            <p>Hi <?php echo esc_html( $user->first_name ?: $user->display_name ); ?>,</p>
            <p>Thank you for your purchase! Here are your ticket details:</p>

            <p><strong>Order:</strong> #<?php echo esc_html( $order_id ); ?><br />
            <strong>Date:</strong> <?php echo esc_html( $order->get_date_created()->date_i18n( 'd M Y, H:i' ) ); ?><br />
            <strong>Total:</strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>

            <?php foreach ( $all_allocated as $competition_id => $tickets ) :
                $comp = new TXC_Competition( $competition_id );
                $ticket_numbers = wp_list_pluck( $tickets, 'ticket_number' );
                sort( $ticket_numbers );
            ?>
                <div style="border:1px solid #ddd;border-radius:6px;padding:15px;margin:15px 0;background:#f9f9f9;">
                    <h3 style="margin:0 0 10px;color:#2271b1;"><?php echo esc_html( $comp->get_title() ); ?></h3>
                    <p style="margin:5px 0;"><strong>Prize:</strong> <?php echo esc_html( $comp->get_prize_description() ); ?></p>
                    <p style="margin:5px 0;"><strong>Draw Date:</strong> <?php echo esc_html( gmdate( 'd M Y, H:i', strtotime( $comp->get_draw_date() ) ) ); ?> GMT</p>
                    <p style="margin:5px 0;"><strong>Tickets (<?php echo count( $ticket_numbers ); ?>):</strong></p>
                    <p style="margin:5px 0;font-size:18px;font-weight:bold;color:#00a32a;">
                        <?php echo esc_html( implode( ', ', array_map( function( $n ) { return '#' . $n; }, $ticket_numbers ) ) ); ?>
                    </p>
                    <p style="margin:5px 0;"><strong>Price per ticket:</strong> &pound;<?php echo esc_html( number_format( $comp->get_price(), 2 ) ); ?></p>
                </div>
            <?php endforeach; ?>

            <p>You can view your tickets at any time from your <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'competitions' ) ); ?>">My Competitions</a> page.</p>
            <p>Good luck!</p>
            <p style="color:#888;font-size:12px;">This is an automated email from <?php echo esc_html( $site_name ); ?>.</p>
        </div>
        <?php
        $message = ob_get_clean();

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( $user->user_email, $subject, $message, $headers );
    }

    /**
     * Send instant win notification email.
     */
    public function send_instant_win_notification( $user_id, $competition_id, $ticket_number, $prize_label, $prize_value ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $comp = new TXC_Competition( $competition_id );
        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( '[%s] Instant Win! — %s', $site_name, $comp->get_title() );

        ob_start();
        ?>
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <h2 style="color:#00a32a;">Congratulations — Instant Win!</h2>
            <p>Hi <?php echo esc_html( $user->first_name ?: $user->display_name ); ?>,</p>
            <p>Your ticket <strong>#<?php echo esc_html( $ticket_number ); ?></strong> in <strong><?php echo esc_html( $comp->get_title() ); ?></strong> is an instant winner!</p>

            <div style="border:2px solid #00a32a;border-radius:8px;padding:20px;margin:15px 0;background:#f0fff0;text-align:center;">
                <p style="font-size:24px;font-weight:bold;margin:0;color:#00a32a;"><?php echo esc_html( $prize_label ); ?></p>
                <?php if ( $prize_value > 0 ) : ?>
                    <p style="font-size:18px;margin:5px 0;">Value: &pound;<?php echo esc_html( number_format( $prize_value, 2 ) ); ?></p>
                <?php endif; ?>
            </div>

            <p>Your prize has been applied to your account. You can check your balance and winnings in your <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'competitions' ) ); ?>">My Competitions</a> page.</p>
            <p>Your ticket is still in the main draw — good luck!</p>
            <p style="color:#888;font-size:12px;">This is an automated email from <?php echo esc_html( $site_name ); ?>.</p>
        </div>
        <?php
        $message = ob_get_clean();

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( $user->user_email, $subject, $message, $headers );
    }
}
