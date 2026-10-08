<?php
// Shalom Healing: description from Marisa Budd's own email (8 Oct 2026),
// and Reiki confirmed as the primary practice. Run with wp eval-file.
$p = get_page_by_path( 'shalom-healing-margaret-river', OBJECT, 'listing' );
if ( ! $p ) {
	exit( "listing not found\n" );
}
$text = 'Marisa Budd is a Reiki Master in Margaret River, and Reiki is the heart of Shalom Healing: traditional Reiki, Reiki with intuitive healing, and Reiki classes for people who want to learn it themselves. Marisa is also a counsellor, and runs group sound healing sessions at different times through the year. There is no shopfront; appointments are arranged directly.';
wp_update_post( array( 'ID' => $p->ID, 'post_excerpt' => $text ) );
update_post_meta( $p->ID, 'primary_practice', 'energy' );
if ( function_exists( '\Oria\Core\Audit\note' ) ) {
	\Oria\Core\Audit\note( $p->ID, 'Description and primary practice (Reiki) updated from the owner\'s email of 8 Oct 2026.' );
}
do_action( 'litespeed_purge_post', $p->ID );
echo "updated {$p->ID}: " . get_post_field( 'post_excerpt', $p->ID ) . "\nprimary: " . get_post_meta( $p->ID, 'primary_practice', true ) . "\n";
