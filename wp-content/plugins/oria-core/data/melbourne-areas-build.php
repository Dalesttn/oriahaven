<?php
/** Build Melbourne's area tree. Dry run unless --apply is passed. */
$apply = in_array( 'apply', (array) $args, true );
$d = json_decode( file_get_contents( __DIR__ . '/melb-tree.json' ), true );

$made = 0; $skipped = 0; $clash = 0;

function ensure( string $slug, string $name, int $parent, bool $apply, int &$made, int &$skipped, int &$clash ): int {
	$existing = get_term_by( 'slug', $slug, 'area' );
	if ( $existing ) {
		// Only safe if it is already where we want it.
		if ( (int) $existing->parent === $parent ) { $skipped++; printf( "  = %-22s exists, correct parent\n", $slug ); return (int) $existing->term_id; }
		$clash++; printf( "  ! %-22s EXISTS ELSEWHERE -- not touched\n", $slug ); return 0;
	}
	printf( "  %s %-22s %-28s parent=%d\n", $apply ? '+' : '.', $slug, $name, $parent );
	if ( ! $apply ) { $made++; return -1; }
	$r = wp_insert_term( $name, 'area', array( 'slug' => $slug, 'parent' => $parent ) );
	if ( is_wp_error( $r ) ) { printf( "    FAILED: %s\n", $r->get_error_message() ); return 0; }
	$made++; return (int) $r['term_id'];
}

printf( "%s Melbourne area tree\n%s\n", $apply ? 'CREATING' : 'DRY RUN --', str_repeat( '-', 62 ) );
$city_id = ensure( $d['city']['slug'], $d['city']['name'], 0, $apply, $made, $skipped, $clash );
foreach ( $d['regions'] as $r ) {
	$rid = ensure( $r['slug'], $r['name'], max( 0, $city_id ), $apply, $made, $skipped, $clash );
	foreach ( $r['suburbs'] as $s ) {
		ensure( $s[0], $s[1], max( 0, $rid ), $apply, $made, $skipped, $clash );
	}
}
printf( "\n  %s: %d  already correct: %d  conflicts: %d\n", $apply ? 'created' : 'would create', $made, $skipped, $clash );
if ( ! $apply ) { echo "  nothing written. re-run with 'apply' to create.\n"; }
