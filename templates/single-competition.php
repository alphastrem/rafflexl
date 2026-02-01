<?php
/**
 * Template: Single Competition
 */
get_header();

while ( have_posts() ) :
    the_post();
    $comp = new TXC_Competition( get_the_ID() );
    ?>
    <div class="txc-page-wrapper">
        <?php include TXC_PLUGIN_DIR . 'includes/public/views/single-competition-content.php'; ?>
    </div>
    <?php
endwhile;

get_footer();
