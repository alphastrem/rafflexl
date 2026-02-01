<?php
/**
 * Template: Competition Archive
 */
get_header();
?>
<div class="txc-page-wrapper">
    <h1 class="txc-page-title">Competitions</h1>
    <?php echo do_shortcode( '[txc_competitions]' ); ?>
</div>
<?php
get_footer();
