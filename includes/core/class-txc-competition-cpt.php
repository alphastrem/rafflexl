<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Competition_CPT {

    public function register_post_type() {
        $labels = [
            'name'               => 'Competitions',
            'singular_name'      => 'Competition',
            'add_new'            => 'Add New Competition',
            'add_new_item'       => 'Add New Competition',
            'edit_item'          => 'Edit Competition',
            'new_item'           => 'New Competition',
            'view_item'          => 'View Competition',
            'search_items'       => 'Search Competitions',
            'not_found'          => 'No competitions found',
            'not_found_in_trash' => 'No competitions found in Trash',
            'menu_name'          => 'Competitions',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Added to custom admin menu
            'query_var'           => true,
            'rewrite'             => [ 'slug' => 'competition', 'with_front' => false ],
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_icon'           => 'dashicons-tickets-alt',
            'supports'            => [ 'title', 'editor', 'thumbnail' ],
            'show_in_rest'        => false,
        ];

        register_post_type( 'txc_competition', $args );
    }

    public function single_template( $template ) {
        global $post;
        if ( $post && 'txc_competition' === $post->post_type ) {
            $custom = TXC_PLUGIN_DIR . 'templates/single-competition.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }

    public function archive_template( $template ) {
        if ( is_post_type_archive( 'txc_competition' ) ) {
            $custom = TXC_PLUGIN_DIR . 'templates/archive-competition.php';
            if ( file_exists( $custom ) ) {
                return $custom;
            }
        }
        return $template;
    }
}
