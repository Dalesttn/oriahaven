<?php
/**
 * Seed the first three Best Of guides, as drafts, with their copy in place.
 *
 * The picks are left for an editor: which studios make a shortlist is a
 * judgement, not a query. What this does do is print the candidates for each
 * guide -- the listings whose categories and audience tags fit -- so the
 * editor starts from a list of twelve, not four hundred.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply creates the drafts.
 *   A guide whose slug already exists is left alone, so re-running is safe.
 *
 * OPTIONS
 *   --apply          create the three guides (as drafts)
 *   --publish        publish them instead of drafting (with --apply)
 *   --sample-picks   also add the top candidates as picks with placeholder
 *                    reasons. FOR PREVIEW SITES ONLY: the reasons are not
 *                    editorial and must not go live.
 *
 * USAGE (from the WordPress root)
 *     php wp-content/plugins/oria-core/tools/seed-best-of.php
 *     php wp-content/plugins/oria-core/tools/seed-best-of.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! function_exists( 'update_field' ) ) {
	fwrite( STDERR, "ACF is not available.\n" );
	exit( 1 );
}

$argv    = $argv ?? array();
$apply   = in_array( '--apply', $argv, true );
$publish = in_array( '--publish', $argv, true );
$sample  = in_array( '--sample-picks', $argv, true );

/* ------------------------------------------------------------- the guides */

$guides = array(
	array(
		'slug'     => 'yoga-for-beginners-perth',
		'title'    => 'Best yoga for beginners in Perth',
		'excerpt'  => 'Welcoming Perth studios with beginner classes, patient teachers and an easy first booking.',
		'intro'    => "Starting yoga can feel intimidating when you don't know which class to choose or what to expect. These Perth studios were shortlisted for beginner-specific classes, supportive teaching and a clear, easy way to get started.",
		'category' => 'beginners',
		'practice' => 'yoga',
		'featured' => true,
		'award'    => 'best_for_beginners',
		'method'   => "We looked for studios offering dedicated beginner or foundations classes, teachers who explain the basics, mats and props provided, a clear class description on the studio's own website, and an introductory offer or drop-in option so a first visit is low-risk.",
		'faq'      => array(
			array( 'question' => 'What should I wear to my first yoga class?', 'answer' => 'Anything you can move and stretch in comfortably. Most studios provide mats; check the listing or bring your own if you prefer.' ),
			array( 'question' => 'Do I need to be flexible to start yoga?', 'answer' => 'No. Beginner classes are built for people who are not, and teachers offer variations for every pose.' ),
			array( 'question' => 'How early should I arrive?', 'answer' => 'Ten minutes before a first class gives you time to sign in, meet the teacher and mention anything they should know.' ),
		),
		'links'    => array(
			array( 'label' => 'Beginner-friendly yoga, by suburb', 'url' => home_url( '/explore/perth/yoga/beginners/' ) ),
			array( 'label' => 'Compare experiences', 'url' => home_url( '/compare/' ) ),
		),
		'find'     => array( 'practice' => 'yoga', 'audience' => 'beginners' ),
	),
	array(
		'slug'     => 'saunas-perth',
		'title'    => 'Best saunas in Perth',
		'excerpt'  => 'Standout sauna and recovery rooms across Perth, from infrared studios to traditional saunas with an ice bath alongside.',
		'intro'    => "Looking for a sauna in Perth? These are the standout sauna and recovery rooms across the city, from infrared studios to traditional saunas and contrast-therapy venues, with what each one offers and what a session costs.",
		'category' => 'relax',
		'practice' => 'spa',
		'featured' => true,
		'award'    => 'best_sauna',
		'method'   => "We considered the type of sauna, whether sessions are private or shared, session length, whether an ice bath or cold plunge is available, showers and towels, price, and how clearly the venue publishes its hours and booking.",
		'faq'      => array(
			array( 'question' => 'What is the difference between an infrared and a traditional sauna?', 'answer' => 'A traditional sauna heats the air, usually to 70–90°C. An infrared sauna heats you directly at a lower air temperature, around 45–60°C, so it feels gentler for the same session length.' ),
			array( 'question' => 'How long is a typical sauna session?', 'answer' => 'Most Perth venues book sessions of 30 to 60 minutes. Contrast sessions alternate a few minutes of cold with time in the heat.' ),
			array( 'question' => 'Do I need to book?', 'answer' => 'Nearly always, and private rooms especially. Each listing links to the venue’s own booking page.' ),
		),
		'links'    => array(
			array( 'label' => 'Ice baths and cold plunges in Perth', 'url' => home_url( '/explore/perth/spa/ice-bath/' ) ),
			array( 'label' => 'Compare experiences', 'url' => home_url( '/compare/' ) ),
		),
		'find'     => array( 'service' => array( 'traditional-sauna', 'infrared-sauna' ) ),
	),
	array(
		'slug'     => 'pilates-for-beginners-perth',
		'title'    => 'Best Pilates for beginners in Perth',
		'excerpt'  => 'Perth studios with beginner Pilates classes, reformer inductions and instructors who explain the basics.',
		'intro'    => "New to Pilates? These Perth studios offer a friendly way to start, with beginner classes, reformer inductions and small groups for people still learning the basics, so you don't need to know the terminology before you walk in.",
		'category' => 'beginners',
		'practice' => 'fitness',
		'featured' => true,
		'award'    => 'best_for_beginners',
		'method'   => "We looked for a dedicated beginner or foundations class, a reformer induction for first-timers, small group sizes, clear level descriptions on the studio's own timetable, equipment supplied, and an introductory offer.",
		'faq'      => array(
			array( 'question' => 'Should I start with mat or reformer Pilates?', 'answer' => 'Either works. Mat classes need nothing but you; reformer classes use a sprung machine and most studios run a short induction first. Beginners often find the reformer’s support helpful.' ),
			array( 'question' => 'What is a reformer induction?', 'answer' => 'A short first session, sometimes one-to-one, that shows you how the machine works and its safety points before you join a group class.' ),
		),
		'links'    => array(
			array( 'label' => 'Reformer Pilates in Perth', 'url' => home_url( '/explore/perth/fitness/reformer/' ) ),
			array( 'label' => 'Beginner-friendly yoga in Perth', 'url' => home_url( '/best/yoga-for-beginners-perth/' ) ),
		),
		'find'     => array( 'specialty' => array( 'reformer-pilates', 'pilates' ), 'audience' => 'beginners' ),
	),
);

/* ------------------------------------------------------------- candidates */

/**
 * Listings that fit a guide, best-evidenced first: those with a photo and
 * more reviews rise. The audience tag narrows when it leaves enough.
 *
 * @return list<int>
 */
function oria_bo_candidates( array $find, int $n = 8 ): array {
	$tax = array( 'relation' => 'AND' );
	foreach ( array( 'practice', 'specialty', 'service' ) as $t ) {
		if ( ! empty( $find[ $t ] ) ) {
			$tax[] = array( 'taxonomy' => $t, 'field' => 'slug', 'terms' => (array) $find[ $t ] );
		}
	}
	$base = array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'tax_query'      => $tax,
	);
	$ids = get_posts( $base );
	if ( ! empty( $find['audience'] ) ) {
		$narrow            = $base;
		$narrow['tax_query'][] = array( 'taxonomy' => 'audience', 'field' => 'slug', 'terms' => (array) $find['audience'] );
		$tagged = get_posts( $narrow );
		if ( count( $tagged ) >= 4 ) {
			$ids = $tagged;
		}
	}
	usort(
		$ids,
		static function ( $a, $b ): int {
			$sa = ( has_post_thumbnail( $a ) ? 1000 : 0 ) + (int) \Oria\Theme\effective_rating( (int) $a, false )['count'];
			$sb = ( has_post_thumbnail( $b ) ? 1000 : 0 ) + (int) \Oria\Theme\effective_rating( (int) $b, false )['count'];
			return $sb <=> $sa;
		}
	);
	return array_slice( array_map( 'intval', $ids ), 0, $n );
}

/** Up to three service names, for a sample pick's highlights. */
function oria_bo_highlights( int $listing ): string {
	$names = array();
	foreach ( wp_get_post_terms( $listing, 'service' ) as $t ) {
		$names[] = \Oria\Theme\tname( $t );
	}
	return implode( ', ', array_slice( $names, 0, 3 ) );
}

/* ------------------------------------------------------------------- run */

foreach ( $guides as $g ) {
	echo "{$g['title']}\n  /best/{$g['slug']}/\n";

	$existing = get_page_by_path( $g['slug'], OBJECT, 'best_of' );
	if ( $existing instanceof WP_Post ) {
		printf( "  exists already (post %d, %s) — left alone\n", $existing->ID, $existing->post_status );
	}

	$cands = oria_bo_candidates( $g['find'] );
	printf( "  %d candidate%s:\n", count( $cands ), 1 === count( $cands ) ? '' : 's' );
	foreach ( $cands as $i => $id ) {
		$r = \Oria\Theme\effective_rating( $id, false );
		printf(
			"    %d. %-44s %-22s %s%s\n",
			$i + 1,
			mb_substr( get_the_title( $id ), 0, 44 ),
			mb_substr( \Oria\Core\BestOf\suburb( $id ), 0, 22 ),
			$r['rating'] > 0 ? sprintf( '%.1f (%d)', $r['rating'], $r['count'] ) : 'no rating',
			has_post_thumbnail( $id ) ? '' : '  [no photo]'
		);
	}

	if ( ! $apply || $existing instanceof WP_Post ) {
		echo "\n";
		continue;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'best_of',
			'post_status'  => $publish ? 'publish' : 'draft',
			'post_title'   => $g['title'],
			'post_name'    => $g['slug'],
			'post_excerpt' => $g['excerpt'],
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		printf( "  FAILED: %s\n\n", $post_id->get_error_message() );
		continue;
	}

	update_field( 'guide_intro', $g['intro'], $post_id );
	update_field( 'guide_category', $g['category'], $post_id );
	update_field( 'featured_guide', $g['featured'] ? 1 : 0, $post_id );
	update_field( 'methodology', $g['method'], $post_id );
	update_field( 'guide_faq', $g['faq'], $post_id );
	update_field( 'guide_links', $g['links'], $post_id );
	$term = get_term_by( 'slug', $g['practice'], 'practice' );
	if ( $term instanceof WP_Term ) {
		update_field( 'guide_practice', $term->term_id, $post_id );
	}

	if ( $sample ) {
		$rows = array();
		foreach ( array_slice( $cands, 0, 4 ) as $id ) {
			$rows[] = array(
				'listing'    => $id,
				'award'      => $g['award'],
				'reason'     => 'Sample pick for preview. Replace this with the editorial reason before publishing.',
				'best_for'   => '',
				'highlights' => oria_bo_highlights( $id ),
				'lead_badge' => 0,
			);
		}
		update_field( 'best_of_entries', $rows, $post_id );
		printf( "  added %d SAMPLE picks — preview only\n", count( $rows ) );
	}

	printf( "  created post %d as %s: %s\n\n", $post_id, $publish ? 'published' : 'draft', get_permalink( $post_id ) );
}

if ( ! $apply ) {
	echo "Dry run: nothing written. Re-run with --apply to create the drafts.\n";
} else {
	echo "Done. Open Best Of in wp-admin to add the picks, then publish.\n";
}
