<?php
/**
 * Template: Tombstone page for deleted competitions.
 */
get_header();

$tombstones = get_option( 'txc_tombstones', [] );
$comp_id = get_query_var( 'txc_tombstone_id', 0 );
$data = $tombstones[ $comp_id ] ?? null;
?>
<div class="txc-page-wrapper">
    <div class="txc-tombstone">
        <h1>Competition Removed</h1>
        <?php if ( $data ) : ?>
            <p>The competition <strong><?php echo esc_html( $data['title'] ?? 'Unknown' ); ?></strong> has been removed by the site administrator.</p>
            <p class="txc-tombstone-date">Removed on <?php echo esc_html( gmdate( 'd M Y', $data['deleted_at'] ?? time() ) ); ?>.</p>
        <?php else : ?>
            <p>This competition is no longer available.</p>
        <?php endif; ?>
        <p><a href="<?php echo esc_url( get_post_type_archive_link( 'txc_competition' ) ); ?>">View all competitions</a></p>
    </div>
</div>
<?php
get_footer();
