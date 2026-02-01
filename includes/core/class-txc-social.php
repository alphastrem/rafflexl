<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Social {

    /**
     * Get configured social media links.
     */
    public static function get_links() {
        $links = [];

        $platforms = [
            'facebook'  => [ 'option' => 'txc_social_facebook', 'icon' => 'facebook', 'label' => 'Facebook' ],
            'twitter'   => [ 'option' => 'txc_social_twitter', 'icon' => 'twitter', 'label' => 'X (Twitter)' ],
            'instagram' => [ 'option' => 'txc_social_instagram', 'icon' => 'instagram', 'label' => 'Instagram' ],
            'tiktok'    => [ 'option' => 'txc_social_tiktok', 'icon' => 'tiktok', 'label' => 'TikTok' ],
        ];

        foreach ( $platforms as $key => $data ) {
            $url = get_option( $data['option'], '' );
            if ( ! empty( $url ) ) {
                $links[ $key ] = [
                    'url'   => $url,
                    'icon'  => $data['icon'],
                    'label' => $data['label'],
                ];
            }
        }

        return $links;
    }

    /**
     * Render social links HTML.
     */
    public static function render_links( $class = 'txc-social-links' ) {
        $links = self::get_links();
        if ( empty( $links ) ) {
            return '';
        }

        $html = '<div class="' . esc_attr( $class ) . '">';
        foreach ( $links as $key => $link ) {
            $html .= sprintf(
                '<a href="%s" class="txc-social-link txc-social-%s" target="_blank" rel="noopener noreferrer" title="%s">%s</a>',
                esc_url( $link['url'] ),
                esc_attr( $key ),
                esc_attr( $link['label'] ),
                esc_html( $link['label'] )
            );
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render share buttons for a competition.
     */
    public static function render_share_buttons( $competition_id ) {
        $comp = new TXC_Competition( $competition_id );
        $url = get_permalink( $competition_id );
        $title = $comp->get_title();
        $encoded_url = rawurlencode( $url );
        $encoded_title = rawurlencode( $title );

        $html = '<div class="txc-share-buttons">';
        $html .= '<span class="txc-share-label">Share:</span> ';

        // Facebook
        $html .= sprintf(
            '<a href="https://www.facebook.com/sharer/sharer.php?u=%s" target="_blank" rel="noopener noreferrer" class="txc-share-btn txc-share-facebook" title="Share on Facebook">Facebook</a> ',
            $encoded_url
        );

        // Twitter/X
        $html .= sprintf(
            '<a href="https://twitter.com/intent/tweet?url=%s&text=%s" target="_blank" rel="noopener noreferrer" class="txc-share-btn txc-share-twitter" title="Share on X">X</a> ',
            $encoded_url,
            $encoded_title
        );

        // WhatsApp
        $html .= sprintf(
            '<a href="https://wa.me/?text=%s%%20%s" target="_blank" rel="noopener noreferrer" class="txc-share-btn txc-share-whatsapp" title="Share on WhatsApp">WhatsApp</a>',
            $encoded_title,
            $encoded_url
        );

        $html .= '</div>';

        return $html;
    }
}
