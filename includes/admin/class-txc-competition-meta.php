<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Competition_Meta {

    public function add_meta_boxes() {
        add_meta_box( 'txc-competition-details', 'Competition Details', [ $this, 'render_details_box' ], 'txc_competition', 'normal', 'high' );
        add_meta_box( 'txc-competition-pricing', 'Pricing & Tickets', [ $this, 'render_pricing_box' ], 'txc_competition', 'normal', 'high' );
        add_meta_box( 'txc-competition-draw', 'Draw Settings', [ $this, 'render_draw_box' ], 'txc_competition', 'normal', 'high' );
        add_meta_box( 'txc-competition-gallery', 'Competition Gallery', [ $this, 'render_gallery_box' ], 'txc_competition', 'side', 'default' );
        add_meta_box( 'txc-competition-status', 'Competition Status', [ $this, 'render_status_box' ], 'txc_competition', 'side', 'high' );

        if ( txc_addon_enabled( 'instantwins' ) ) {
            add_meta_box( 'txc-competition-instantwins', 'Instant Wins', [ $this, 'render_instant_wins_box' ], 'txc_competition', 'normal', 'default' );
        }

        if ( txc_addon_enabled( 'youtube' ) ) {
            add_meta_box( 'txc-competition-youtube', 'YouTube Draw Link', [ $this, 'render_youtube_box' ], 'txc_competition', 'normal', 'default' );
        }
    }

    public function render_details_box( $post ) {
        wp_nonce_field( 'txc_save_competition', 'txc_competition_nonce' );
        $seo  = get_post_meta( $post->ID, '_txc_seo_description', true );
        $types = get_post_meta( $post->ID, '_txc_prize_type', true );
        $types = is_array( $types ) ? $types : [];
        $desc  = get_post_meta( $post->ID, '_txc_prize_description', true );
        $all_types = [ 'credit' => 'Site Credit', 'coupon' => 'Coupon', 'physical' => 'Physical Prize', 'cash' => 'Cash' ];
        ?>
        <table class="form-table txc-meta-table">
            <tr>
                <th><label for="txc_seo_description">SEO Description</label></th>
                <td>
                    <textarea id="txc_seo_description" name="txc_seo_description" rows="2" class="large-text"><?php echo esc_textarea( $seo ); ?></textarea>
                    <p class="description">Brief description for search engines (max 160 characters).</p>
                </td>
            </tr>
            <tr>
                <th>Prize Type</th>
                <td>
                    <?php foreach ( $all_types as $key => $label ) : ?>
                        <label style="display:inline-block;margin-right:15px;">
                            <input type="checkbox" name="txc_prize_type[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $types, true ) ); ?> />
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th><label for="txc_prize_description">Prize Description</label></th>
                <td>
                    <textarea id="txc_prize_description" name="txc_prize_description" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
                    <p class="description">Public description of the prize(s) for this competition.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_pricing_box( $post ) {
        $price    = get_post_meta( $post->ID, '_txc_price', true );
        $max      = get_post_meta( $post->ID, '_txc_max_tickets', true );
        $per_user = get_post_meta( $post->ID, '_txc_max_per_user', true );
        $sold     = get_post_meta( $post->ID, '_txc_tickets_sold', true ) ?: 0;
        ?>
        <table class="form-table txc-meta-table">
            <tr>
                <th><label for="txc_price">Price Per Ticket (&pound;)</label></th>
                <td><input type="number" id="txc_price" name="txc_price" value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0.01" class="small-text" /></td>
            </tr>
            <tr>
                <th><label for="txc_max_tickets">Max Tickets Total</label></th>
                <td><input type="number" id="txc_max_tickets" name="txc_max_tickets" value="<?php echo esc_attr( $max ); ?>" min="1" class="small-text" /></td>
            </tr>
            <tr>
                <th><label for="txc_max_per_user">Max Tickets Per User (Total)</label></th>
                <td>
                    <input type="number" id="txc_max_per_user" name="txc_max_per_user" value="<?php echo esc_attr( $per_user ); ?>" min="1" class="small-text" />
                    <p class="description">Absolute maximum tickets a single user can hold for this competition across all purchases.</p>
                </td>
            </tr>
            <tr>
                <th>Tickets Sold</th>
                <td><strong><?php echo esc_html( $sold ); ?></strong> / <?php echo esc_html( $max ?: '—' ); ?></td>
            </tr>
        </table>
        <?php
    }

    public function render_draw_box( $post ) {
        $draw_date  = get_post_meta( $post->ID, '_txc_draw_date', true );
        $draw_mode  = get_post_meta( $post->ID, '_txc_draw_mode', true ) ?: 'manual';
        $must_sell  = get_post_meta( $post->ID, '_txc_must_sell_out', true );
        $status     = get_post_meta( $post->ID, '_txc_status', true );

        $can_switch = true;
        if ( ! empty( $draw_date ) ) {
            $diff = strtotime( $draw_date . ' +0000' ) - time();
            if ( $diff < ( 15 * 60 ) ) {
                $can_switch = false;
            }
        }
        ?>
        <table class="form-table txc-meta-table">
            <tr>
                <th><label for="txc_draw_date">Draw Date &amp; Time (<?php echo esc_html( wp_timezone_string() ); ?>)</label></th>
                <td>
                    <input type="datetime-local" id="txc_draw_date" name="txc_draw_date" value="<?php echo esc_attr( $draw_date ? get_date_from_gmt( $draw_date, 'Y-m-d\TH:i' ) : '' ); ?>" />
                    <?php if ( $draw_date ) :
                        $stop_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $draw_date . ' +0000' ) - 300 );
                        $stop_local = get_date_from_gmt( $stop_gmt, 'Y-m-d H:i' );
                    ?>
                        <p class="description">Sales stop automatically at <strong><?php echo esc_html( $stop_local ); ?> <?php echo esc_html( wp_timezone_string() ); ?></strong> (5 minutes before draw).</p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Draw Mode</th>
                <td>
                    <label style="margin-right:15px;">
                        <input type="radio" name="txc_draw_mode" value="manual" <?php checked( $draw_mode, 'manual' ); ?> <?php disabled( ! $can_switch ); ?> />
                        Manual (owner presses draw button)
                    </label>
                    <label>
                        <input type="radio" name="txc_draw_mode" value="auto" <?php checked( $draw_mode, 'auto' ); ?> <?php disabled( ! $can_switch ); ?> />
                        Auto (runs at draw time)
                    </label>
                    <?php if ( ! $can_switch ) : ?>
                        <p class="description" style="color:#d63638;">Cannot change draw mode within 15 minutes of draw time.</p>
                    <?php else : ?>
                        <p class="description">Manual mode has an auto fallback: if the draw button is not pressed by draw time, it runs automatically.</p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Must Sell Out to Draw</th>
                <td>
                    <label>
                        <input type="checkbox" name="txc_must_sell_out" value="yes" <?php checked( $must_sell, 'yes' ); ?> />
                        Yes, all tickets must be sold before the draw can proceed
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_gallery_box( $post ) {
        $ids = get_post_meta( $post->ID, '_txc_gallery_ids', true );
        $ids = is_array( $ids ) ? $ids : [];
        ?>
        <div id="txc-gallery-container">
            <div id="txc-gallery-images">
                <?php foreach ( $ids as $id ) :
                    $img = wp_get_attachment_image( $id, 'thumbnail' );
                    if ( $img ) :
                ?>
                    <div class="txc-gallery-item" data-id="<?php echo esc_attr( $id ); ?>">
                        <?php echo $img; ?>
                        <button type="button" class="txc-gallery-remove">&times;</button>
                    </div>
                <?php
                    endif;
                endforeach; ?>
            </div>
            <input type="hidden" id="txc_gallery_ids" name="txc_gallery_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" />
            <button type="button" class="button" id="txc-gallery-add">Add Images</button>
        </div>
        <?php
    }

    public function render_status_box( $post ) {
        $status = get_post_meta( $post->ID, '_txc_status', true ) ?: 'draft';
        $statuses = [
            'draft'     => 'Draft',
            'live'      => 'Live',
            'paused'    => 'Paused',
            'sold_out'  => 'Sold Out',
            'drawing'   => 'Drawing',
            'drawn'     => 'Drawn',
            'cancelled' => 'Cancelled',
            'failed'    => 'Failed',
        ];
        ?>
        <p>
            <label for="txc_status"><strong>Competition Status:</strong></label><br />
            <select id="txc_status" name="txc_status" style="width:100%;margin-top:5px;">
                <?php foreach ( $statuses as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    public function render_instant_wins_box( $post ) {
        $count      = get_post_meta( $post->ID, '_txc_instant_wins_count', true ) ?: 0;
        $status     = get_post_meta( $post->ID, '_txc_status', true ) ?: 'draft';
        $max_tickets = (int) get_post_meta( $post->ID, '_txc_max_tickets', true );
        $entries    = TXC_Instant_Wins::get_admin_entries( $post->ID );
        $can_pick   = in_array( $status, [ 'draft', 'paused' ], true );
        ?>
        <table class="form-table txc-meta-table">
            <tr>
                <th><label for="txc_instant_wins_count">Number of Instant Wins</label></th>
                <td>
                    <input type="number" id="txc_instant_wins_count" name="txc_instant_wins_count" value="<?php echo esc_attr( $count ); ?>" min="0" <?php if ( $max_tickets > 0 ) echo 'max="' . esc_attr( $max_tickets ) . '"'; ?> class="small-text" />
                    <?php if ( $max_tickets > 0 ) : ?>
                        <span class="description">out of <?php echo esc_html( $max_tickets ); ?> total tickets</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ( $max_tickets > 0 ) : ?>
            <?php if ( $can_pick ) : ?>
                <p>
                    <button type="button" class="button button-primary" id="txc-pick-instant-wins" data-competition="<?php echo esc_attr( $post->ID ); ?>">
                        <?php echo empty( $entries ) ? 'Pick Random Numbers' : 'Re-pick Random Numbers'; ?>
                    </button>
                    <span id="txc-iw-status" style="margin-left:10px;"></span>
                </p>
                <?php if ( ! empty( $entries ) ) : ?>
                    <p class="description" style="color:#dba617;">Re-picking will replace all unclaimed instant win numbers. Claimed entries are preserved.</p>
                <?php endif; ?>
            <?php else : ?>
                <p class="description">Instant win numbers cannot be changed while the competition is <strong><?php echo esc_html( $status ); ?></strong>.</p>
            <?php endif; ?>
        <?php else : ?>
            <p class="description" style="color:#d63638;">Save the competition with Max Tickets Total set before picking instant win numbers.</p>
        <?php endif; ?>

        <div id="txc-iw-entries">
            <?php if ( ! empty( $entries ) ) : ?>
                <?php TXC_Instant_Wins::render_admin_entries_table( $entries, $can_pick ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_youtube_box( $post ) {
        $url = get_post_meta( $post->ID, '_txc_youtube_url', true );
        ?>
        <table class="form-table txc-meta-table">
            <tr>
                <th><label for="txc_youtube_url">YouTube Draw Link</label></th>
                <td>
                    <input type="url" id="txc_youtube_url" name="txc_youtube_url" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://youtube.com/watch?v=..." />
                    <p class="description">Link to the live draw video on YouTube. Displays as a "Watch Draw" button on the competition page.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['txc_competition_nonce'] ) || ! wp_verify_nonce( $_POST['txc_competition_nonce'], 'txc_save_competition' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Details
        update_post_meta( $post_id, '_txc_seo_description', sanitize_textarea_field( $_POST['txc_seo_description'] ?? '' ) );
        $prize_types = isset( $_POST['txc_prize_type'] ) ? array_map( 'sanitize_text_field', (array) $_POST['txc_prize_type'] ) : [];
        update_post_meta( $post_id, '_txc_prize_type', $prize_types );
        update_post_meta( $post_id, '_txc_prize_description', sanitize_textarea_field( $_POST['txc_prize_description'] ?? '' ) );

        // Pricing
        update_post_meta( $post_id, '_txc_price', floatval( $_POST['txc_price'] ?? 0 ) );
        update_post_meta( $post_id, '_txc_max_tickets', absint( $_POST['txc_max_tickets'] ?? 0 ) );
        update_post_meta( $post_id, '_txc_max_per_user', absint( $_POST['txc_max_per_user'] ?? 1 ) );

        // Draw
        $draw_date = sanitize_text_field( $_POST['txc_draw_date'] ?? '' );
        if ( ! empty( $draw_date ) ) {
            $local = str_replace( 'T', ' ', $draw_date );
            if ( ! preg_match( '/:\d{2}$/', $local ) ) {
                $local .= ':00';
            }
            $draw_date = get_gmt_from_date( $local, 'Y-m-d H:i:s' );
        }
        update_post_meta( $post_id, '_txc_draw_date', $draw_date );
        update_post_meta( $post_id, '_txc_draw_mode', sanitize_text_field( $_POST['txc_draw_mode'] ?? 'manual' ) );
        update_post_meta( $post_id, '_txc_must_sell_out', sanitize_text_field( $_POST['txc_must_sell_out'] ?? 'no' ) );

        // Status
        $status = sanitize_text_field( $_POST['txc_status'] ?? 'draft' );
        update_post_meta( $post_id, '_txc_status', $status );

        // Gallery
        $gallery_ids = sanitize_text_field( $_POST['txc_gallery_ids'] ?? '' );
        $gallery_ids = array_filter( array_map( 'absint', explode( ',', $gallery_ids ) ) );
        update_post_meta( $post_id, '_txc_gallery_ids', $gallery_ids );

        // Instant wins
        if ( isset( $_POST['txc_instant_wins_count'] ) ) {
            update_post_meta( $post_id, '_txc_instant_wins_count', absint( $_POST['txc_instant_wins_count'] ) );
        }

        // Instant win prize config (editable fields from the entries table)
        if ( ! empty( $_POST['txc_iw'] ) && is_array( $_POST['txc_iw'] ) ) {
            global $wpdb;
            $iw_table = $wpdb->prefix . 'txc_instant_win_map';

            foreach ( $_POST['txc_iw'] as $entry_id => $prize_data ) {
                $wpdb->update(
                    $iw_table,
                    [
                        'prize_type'  => sanitize_text_field( $prize_data['prize_type'] ?? 'credit' ),
                        'prize_value' => floatval( $prize_data['prize_value'] ?? 0 ),
                        'prize_label' => sanitize_text_field( $prize_data['prize_label'] ?? '' ),
                    ],
                    [ 'id' => absint( $entry_id ) ]
                );
            }
        }

        // YouTube
        if ( isset( $_POST['txc_youtube_url'] ) ) {
            update_post_meta( $post_id, '_txc_youtube_url', esc_url_raw( $_POST['txc_youtube_url'] ) );
        }

        // Auto-create or update WC product when going live
        if ( 'live' === $status ) {
            $this->sync_wc_product( $post_id );
        }
    }

    private function sync_wc_product( $post_id ) {
        $product_id = (int) get_post_meta( $post_id, '_txc_wc_product_id', true );
        $comp = new TXC_Competition( $post_id );

        if ( $product_id && get_post( $product_id ) ) {
            $product = wc_get_product( $product_id );
        } else {
            $product = new WC_Product_Simple();
        }

        $product->set_name( $comp->get_title() . ' — Competition Entry' );
        $product->set_regular_price( (string) $comp->get_price() );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( false );
        $product->set_status( 'publish' );
        $product->set_manage_stock( false );
        $saved_id = $product->save();

        if ( $saved_id && $saved_id !== $product_id ) {
            update_post_meta( $post_id, '_txc_wc_product_id', $saved_id );
        }
    }
}
