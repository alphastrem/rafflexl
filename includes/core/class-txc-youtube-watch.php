<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_YouTube_Watch {

    /**
     * Extract YouTube video ID from URL.
     */
    public static function extract_video_id( $url ) {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) {
                return $matches[1];
            }
        }

        if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $url ) ) {
            return $url;
        }

        return '';
    }
}
