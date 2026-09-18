<?php
/**
 * Import one researched Trend to Try from data/trends/{slug}.json, as a DRAFT.
 *
 * Usage (from the WordPress root):
 *   php wp-content/plugins/oria-core/tools/import-trend.php floating-saunas            # report only
 *   php wp-content/plugins/oria-core/tools/import-trend.php floating-saunas --apply    # write the draft
 *
 * - Creates the trend as a draft, or updates it while it is still a draft
 *   or pending. A published trend is never touched: edit that in the admin.
 * - Related listings, guides, trends and categories are named by slug in
 *   the file and looked up here, so the same file works on any copy of the
 *   site. Anything not found is reported and left out, never guessed.
 * - The Reel address is validated and checked for duplicates, but the
 *   three checks that need a person who has watched it -- embedding
 *   confirmed, permission, status Working -- are always left unset. The
 *   publishing checklist then holds the page back until an editor does
 *   them.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\Trends;

$args  = array_slice( $argv ?? array(), 1 );
$apply = in_array( '--apply', $args, true );
// --into=ID: write into an existing DRAFT trend (one an editor already
// started for this topic) instead of creating a second one.
$into = 0;
foreach ( $args as $a ) {
	if ( str_starts_with( $a, '--into=' ) ) {
		$into = (int) substr( $a, 7 );
	}
}
$slug = sanitize_title( (string) ( array_values( array_filter( $args, static fn( $a ) => ! str_starts_with( $a, '--' ) ) )[0] ?? '' ) );
$file  = dirname( __DIR__ ) . '/data/trends/' . $slug . '.json';
if ( '' === $slug || ! is_readable( $file ) ) {
	exit( "Usage: import-trend.php <slug> [--apply]   (reads data/trends/<slug>.json)\n" );
}
$d = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $d ) || ( $d['slug'] ?? '' ) !== $slug ) {
	exit( "The file is not valid JSON, or its slug does not match.\n" );
}

echo "\n" . ( $apply ? "APPLY -- writing the draft.\n" : "DRY RUN -- nothing is written. Add --apply to write.\n" );
echo str_repeat( '=', 72 ) . "\n";

$warn = array();
$find = static function ( string $s, string $type ) use ( &$warn ): int {
	$p = get_page_by_path( $s, OBJECT, $type );
	if ( ! $p ) {
		$warn[] = "not found, left out: {$type} '{$s}'";
		return 0;
	}
	return (int) $p->ID;
};

// ---------------------------------------------------------------- existing
$existing = get_page_by_path( $slug, OBJECT, Trends\POST_TYPE );
if ( $into ) {
	$target = get_post( $into );
	if ( ! $target || Trends\POST_TYPE !== $target->post_type ) {
		exit( "--into={$into} is not a trend.\n" );
	}
	if ( $existing && (int) $existing->ID !== $into ) {
		exit( "Another trend (#{$existing->ID}) already uses the slug '{$slug}'.\n" );
	}
	$existing = $target;
}
if ( $existing && ! in_array( $existing->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
	exit( "'{$slug}' is already {$existing->post_status}. Published trends are edited in the admin, not overwritten from a file.\n" );
}

// ---------------------------------------------------------------- the Reel
$reel = Trends\normalise_reel_url( (string) ( $d['reel_url'] ?? '' ) );
if ( ( $d['reel_url'] ?? '' ) && ! $reel ) {
	exit( "The reel_url is not an Instagram Reel or post address.\n" );
}
if ( $reel ) {
	$holder = Trends\holder_of( $reel['code'], $existing ? (int) $existing->ID : 0 );
	if ( $holder ) {
		exit( 'That Reel is already on trend #' . $holder . ' (' . get_the_title( $holder ) . ', ' . get_post_status( $holder ) . "). If that draft was started for this topic, re-run with --into={$holder}.\n" );
	}
}

// ---------------------------------------------------------------- relations
$cats = array();
foreach ( (array) ( $d['related_practices'] ?? array() ) as $s ) {
	$t = get_term_by( 'slug', (string) $s, 'practice' );
	if ( $t instanceof WP_Term ) {
		$cats[] = (int) $t->term_id;
	} else {
		$warn[] = "not found, left out: category '{$s}'";
	}
}
$listings = array_values( array_filter( array_map( static fn( $s ) => $find( (string) $s, 'listing' ), (array) ( $d['related_listings'] ?? array() ) ) ) );
$guides   = array();
foreach ( (array) ( $d['related_guides'] ?? array() ) as $type => $slugs ) {
	foreach ( (array) $slugs as $s ) {
		$guides[] = $find( (string) $s, 'post' === $type ? 'post' : (string) $type );
	}
}
$guides = array_values( array_filter( $guides ) );
$self   = $existing ? (int) $existing->ID : 0;
$trends = array_values( array_filter( array_map( static fn( $s ) => $find( (string) $s, Trends\POST_TYPE ), (array) ( $d['related_trends'] ?? array() ) ), static fn( int $t ): bool => $t > 0 && $t !== $self ) );

$cta     = (array) ( $d['primary_cta'] ?? array() );
$cta_url = '';
if ( ! empty( $cta['listing'] ) ) {
	$lp = get_page_by_path( (string) $cta['listing'], OBJECT, 'listing' );
	if ( $lp && 'publish' === $lp->post_status ) {
		$cta_url = (string) get_permalink( $lp );
	}
}
if ( '' === $cta_url && ! empty( $cta['fallback_url'] ) ) {
	$cta_url = home_url( (string) $cta['fallback_url'] );
	$warn[]  = 'call to action points at ' . $cta_url . ' until the listing is imported';
}

printf( "%s  %s\n      %s\n", $existing ? 'UPDATE draft #' . $existing->ID : 'NEW draft', $slug, $d['title'] );
printf( "      categories %d, listings %d, guides %d, related trends %d\n", count( $cats ), count( $listings ), count( $guides ), count( $trends ) );
printf( "      CTA: %s -> %s\n", (string) ( $cta['label'] ?? '' ), $cta_url );
foreach ( $warn as $w ) {
	echo "      note: {$w}\n";
}
if ( ! $apply ) {
	echo "\nNothing written.\n\n";
	exit( 0 );
}

// ---------------------------------------------------------------- write
// A byline, and an author for the Article schema: the trend's own author if
// it has one, otherwise the first administrator. A draft made from the
// command line otherwise belongs to nobody.
$author = $existing && (int) $existing->post_author ? (int) $existing->post_author : (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID' ) )[0] ?? 0 );
$id     = wp_insert_post(
	array(
		'ID'           => $existing ? (int) $existing->ID : 0,
		'post_author'  => $author,
		'post_type'    => Trends\POST_TYPE,
		'post_status'  => 'draft',
		'post_title'   => (string) $d['title'],
		'post_name'    => $slug,
		'post_excerpt' => (string) ( $d['excerpt'] ?? '' ),
	),
	true
);
if ( is_wp_error( $id ) ) {
	exit( 'ERROR: ' . $id->get_error_message() . "\n" );
}

$plain = array(
	'short_answer', 'what_it_is', 'why_trending', 'what_to_expect', 'reel_context', 'possible_benefits',
	'limitations_uncertainties', 'safety_considerations', 'who_may_need_professional_advice', 'oria_verdict',
	'trend_goals', 'beginner_suitability', 'trend_status', 'typical_duration', 'typical_perth_price_from',
	'typical_perth_price_to', 'price_checked', 'price_notes', 'evidence_position', 'evidence_reviewed_date',
	'editorial_review_due', 'creator_name', 'creator_handle', 'creator_profile_url', 'creator_type',
	'creator_location', 'attribution_text', 'reel_headline', 'reel_reason', 'reel_fallback_text',
	'is_sponsored', 'contains_affiliate_links', 'editorial_stage', 'editor_todo',
);
foreach ( $plain as $f ) {
	if ( array_key_exists( $f, $d ) ) {
		update_field( $f, $d[ $f ], $id );
	}
}
update_field( 'beginner_tips', array_map( static fn( $x ) => array( 'tip' => (string) $x ), (array) ( $d['beginner_tips'] ?? array() ) ), $id );
update_field( 'questions_to_ask_provider', array_map( static fn( $x ) => array( 'question' => (string) $x ), (array) ( $d['questions_to_ask_provider'] ?? array() ) ), $id );
update_field( 'reel_takeaways', array_map( static fn( $x ) => array( 'text' => (string) $x ), (array) ( $d['reel_takeaways'] ?? array() ) ), $id );
update_field( 'sources', array_values( (array) ( $d['sources'] ?? array() ) ), $id );
update_field( 'related_practices', $cats, $id );
update_field( 'related_listings', $listings, $id );
update_field( 'related_guides', $guides, $id );
update_field( 'related_trends', $trends, $id );
update_field( 'primary_cta_label', (string) ( $cta['label'] ?? '' ), $id );
update_field( 'primary_cta_url', $cta_url, $id );
update_field( 'compare_label', (string) ( $d['compare']['label'] ?? '' ), $id );
update_field( 'compare_url', ! empty( $d['compare']['url'] ) ? home_url( (string) $d['compare']['url'] ) : '', $id );

// The Reel address, and deliberately NOT the checks that need a person.
update_field( 'reel_url', $reel ? $reel['url'] : '', $id );
update_field( 'reel_status', 'unchecked', $id );
update_field( 'embed_allowed', 0, $id );
update_field( 'permission_status', '', $id );

printf( "      wrote #%d (draft)\n\nStill needed before it can be published:\n", $id );
foreach ( Trends\missing( (int) $id ) as $m ) {
	echo "  - {$m}\n";
}
echo "\nEdit it: " . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n\n";
