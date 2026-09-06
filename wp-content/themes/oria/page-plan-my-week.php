<?php
/**
 * /plan-my-week/ — a seven-day plan built from what someone wants, not from
 * what they say is wrong with them. A working prototype.
 *
 * WHY IT ASKS ABOUT PREFERENCES AND NOT SYMPTOMS.
 *
 * The brief this came from imagined a free-text box: "I've been really
 * stressed lately, sleeping badly, my shoulders are tight" -> a week of
 * modalities, each with a reason like "gentle movement can help you unwind"
 * and headings like "Stress reduction · Sleep · Muscle tension".
 *
 * That output cannot ship, and the reason is written in this codebase
 * already. writing style/schema.json names the exact trap: "An article
 * organised problem -> modality makes the claim through its architecture
 * even when every sentence is careful. Organise by what someone wants to
 * do." compare.json says the same thing about attributes: every one
 * describes the room, never the outcome. A visitor types symptoms, the site
 * answers with treatments and reasons -- that is a therapeutic claim
 * whatever the wording, and "can help" is a claim with a hedge in front of
 * it rather than no claim.
 *
 * So the question is turned around. Nobody is asked what is wrong. They are
 * asked what they want the week to be like -- how much effort, how much
 * company, how much to spend, whether to include the spiritual end of the
 * directory, whether they are new to it. Those are preferences, they are
 * answerable from data the directory already holds, and none of them is a
 * health disclosure.
 *
 * Every "why" line under a day describes the room: how long, how many
 * people, how quiet, what it costs. Not one says a session helps, eases,
 * reduces or improves anything, because not one of them could.
 *
 * WHAT THE DATA WILL AND WILL NOT SUPPORT. Price bands are on 76% of
 * listings, so the plan talks in bands. It does not quote figures at all --
 * see the note by the payload for why price_from cannot be trusted here.
 * 25 listings are tagged beginner friendly, so that preference narrows hard
 * and the summary line says so rather than quietly returning three results.
 */

declare(strict_types=1);

get_header();

/*
 * The shape of a week. Each day names a wellness goal the directory already
 * carries, and a line about what that kind of session is like. The line is
 * about the room, and it is written here rather than generated, so nothing
 * can drift into a claim.
 */
const ORIA_WEEK = array(
	array( 'day' => 'Monday',    'goals' => array( 'Move', 'Relax' ),           'note' => 'Start with something structured — a class with a set time is easier to actually turn up to than an open-ended plan.' ),
	array( 'day' => 'Tuesday',   'goals' => array( 'Explore' ),                 'note' => 'Somewhere to go rather than something to book — the outdoor and exploring end of the directory.' ),
	array( 'day' => 'Wednesday', 'goals' => array( 'Hands-on care' ),           'note' => 'A table, a practitioner and an hour where you are not required to do anything.' ),
	array( 'day' => 'Thursday',  'goals' => array( 'Wind down', 'Stillness' ),  'note' => 'Quiet rooms, usually lying down, most of them an hour.' ),
	array( 'day' => 'Friday',    'goals' => array( 'Reset', 'Recharge' ),       'note' => 'Something with a beginning and an end, to close the working week on.' ),
	array( 'day' => 'Saturday',  'goals' => array( 'Connect', 'Go deeper' ),    'note' => 'A weekend slot is long enough for something with other people in it.' ),
	array( 'day' => 'Sunday',    'goals' => array(),                            'note' => 'Nothing booked. A week with seven appointments in it is not a plan, it is a second job.' ),
);

/* ---------------------------------------------------------------- data -- */

$oria_city  = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
$oria_cname = function_exists( '\Oria\Core\Cities\name' ) ? \Oria\Core\Cities\name( $oria_city ) : __( 'Perth', 'oria' );

$oria_goals_all = function_exists( '\Oria\Core\GoodFor\labels' ) ? \Oria\Core\GoodFor\labels() : array();
$oria_palette   = array();
foreach ( $oria_goals_all as $oria_g ) {
	$oria_palette[ (string) $oria_g['label'] ] = (string) $oria_g['color'];
}

$oria_rows = array();

$oria_posts = get_posts(
	array( 'post_type' => 'listing', 'post_status' => 'publish', 'posts_per_page' => -1 )
);

/*
 * Asked once, not once per listing. filter_ids( array( $one_id ), $city )
 * inside the loop was a taxonomy query each time round: 408 queries and 1.6
 * seconds, against 1 query and 0.02 seconds for the whole set at once.
 */
$oria_in_city = null;
if ( $oria_city && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
	$oria_in_city = array_flip(
		\Oria\Core\Cities\filter_ids( wp_list_pluck( $oria_posts, 'ID' ), $oria_city )
	);
}

foreach ( $oria_posts as $oria_p ) {

	$oria_id = (int) $oria_p->ID;

	if ( null !== $oria_in_city ) {
		if ( ! isset( $oria_in_city[ $oria_id ] ) ) {
			continue;
		}
	}

	$oria_goals = array();
	if ( function_exists( '\Oria\Core\GoodFor\for_listing' ) ) {
		foreach ( \Oria\Core\GoodFor\for_listing( $oria_id ) as $oria_w ) {
			$oria_goals[] = is_array( $oria_w ) ? (string) ( $oria_w['label'] ?? '' ) : (string) $oria_w;
		}
	}
	if ( ! $oria_goals ) {
		continue; // nothing to place it in a week with
	}

	// Quiet and social, where the registry can name this kind of session.
	$oria_quiet = null;
	$oria_soc   = null;
	$oria_int   = null;
	if ( function_exists( '\Oria\Core\Dna\bars' ) ) {
		foreach ( \Oria\Core\Dna\bars( $oria_id ) as $oria_b ) {
			$oria_k = strtolower( (string) ( $oria_b['label'] ?? '' ) );
			if ( str_contains( $oria_k, 'quiet' ) )     { $oria_quiet = (int) $oria_b['score']; }
			if ( str_contains( $oria_k, 'social' ) )    { $oria_soc   = (int) $oria_b['score']; }
			if ( str_contains( $oria_k, 'intensity' ) ) { $oria_int   = (int) $oria_b['score']; }
		}
	}

	// oria_terms_of(): the cached drop-in. wp_get_post_terms() always queries.
	$oria_cats = \Oria\Theme\oria_terms_of( $oria_id, 'practice' );
	$oria_cats = is_wp_error( $oria_cats ) ? array() : $oria_cats;
	$oria_cat  = $oria_cats ? \Oria\Theme\tname( $oria_cats[0] ) : '';

	// The one preference that is about content rather than feel.
	$oria_spirit = \Oria\Core\Dna\spiritual( $oria_id );

	$oria_areas  = \Oria\Theme\oria_terms_of( $oria_id, 'area' );
	$oria_areas  = is_wp_error( $oria_areas ) ? array() : $oria_areas;
	$oria_suburb = '';
	foreach ( $oria_areas as $oria_a ) {
		if ( $oria_a->parent ) {
			$oria_suburb = \Oria\Theme\tname( $oria_a );
			break;
		}
	}

	/*
	 * price_from is deliberately NOT used here. It is set on 63 listings and
	 * runs from $8 to $1666, because for some it is a session and for others
	 * a course or a retreat total. A week planner that offers "Friday, from
	 * $1495" is not being precise, it is being wrong. The band is the honest
	 * unit: coarse, but it means the same thing on every listing.
	 */

	/*
	 * Decoded, because JSON has no entities. get_the_title() returns
	 * "Bodyscape Yoga &#038; Wellness Spa" and nothing downstream will ever
	 * turn that back into an ampersand -- the same fault the schema graph
	 * had, in a different pipe.
	 */
	$oria_dec = static fn( string $v ): string => html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$oria_rows[] = array(
		't'  => $oria_dec( (string) get_the_title( $oria_id ) ),
		'u'  => get_permalink( $oria_id ),
		'g'  => array_values( array_filter( $oria_goals ) ),
		'c'  => $oria_dec( $oria_cat ),
		'sb' => $oria_dec( $oria_suburb ),
		'pb' => (string) get_field( 'price_band', $oria_id ),
		'q'  => $oria_quiet,
		's'  => $oria_soc,
		'i'  => $oria_int,
		'sp' => $oria_spirit ? 1 : 0,
		'b'  => has_term( 'beginners', 'audience', $oria_id ) ? 1 : 0,
		'km' => function_exists( '\Oria\Core\Geo\km_from_cbd' )
			? ( null === \Oria\Core\Geo\km_from_cbd( $oria_id ) ? null : round( (float) \Oria\Core\Geo\km_from_cbd( $oria_id ), 1 ) )
			: null,
	);
}
?>

<div class="heroband heroband--bare">
<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Plan my week', 'oria' ); ?></span>
	</nav>
	<div class="pagehead__copy">
		<h1 class="h1 pagehead__title"><?php printf( esc_html__( 'A week in %s, built around you', 'oria' ), esc_html( $oria_cname ) ); ?></h1>
		<p class="lede pagehead__lede"><?php esc_html_e( 'Tell us what you want the week to be like — how much effort, how much company, what you want to spend. We will put a week together from real places, and you can throw out any day you do not fancy.', 'oria' ); ?></p>
	</div>
</section>
</div>

<section class="wrap section section--top-flush planner" id="planner">

	<form class="planq" data-planq>
		<div class="planq__row">
			<span class="planq__label"><?php esc_html_e( 'How much effort', 'oria' ); ?></span>
			<div class="wmap__seg" role="group">
				<button class="segbtn is-on" type="button" data-effort="any"><?php esc_html_e( 'Mixed', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-effort="gentle"><?php esc_html_e( 'Gentle', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-effort="active"><?php esc_html_e( 'Active', 'oria' ); ?></button>
			</div>
		</div>

		<div class="planq__row">
			<span class="planq__label"><?php esc_html_e( 'Company', 'oria' ); ?></span>
			<div class="wmap__seg" role="group">
				<button class="segbtn is-on" type="button" data-social="any"><?php esc_html_e( 'Either', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-social="quiet"><?php esc_html_e( 'Keep it quiet', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-social="people"><?php esc_html_e( 'Around people', 'oria' ); ?></button>
			</div>
		</div>

		<div class="planq__row">
			<span class="planq__label"><?php esc_html_e( 'Budget', 'oria' ); ?></span>
			<div class="wmap__seg" role="group">
				<button class="segbtn is-on" type="button" data-budget="any"><?php esc_html_e( 'No limit', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-budget="mid"><?php esc_html_e( 'Keep it modest', 'oria' ); ?></button>
				<button class="segbtn" type="button" data-budget="free"><?php esc_html_e( 'Free only', 'oria' ); ?></button>
			</div>
		</div>

		<div class="planq__row planq__row--checks">
			<label class="check"><input type="checkbox" data-skip-spirit><span><?php esc_html_e( 'Skip the spiritual side', 'oria' ); ?></span></label>
			<label class="check"><input type="checkbox" data-new><span><?php esc_html_e( 'I am new to most of this', 'oria' ); ?></span></label>
		</div>

		<button class="btn btn--dark planq__go" type="button" data-plan-go><?php esc_html_e( 'Build my week', 'oria' ); ?></button>
	</form>

	<p class="planner__note micro" data-plan-summary aria-live="polite"></p>

	<ol class="week" data-week></ol>

	<p class="planner__foot micro">
		<?php esc_html_e( 'This is a suggestion for how to spend a week, not advice. It is built from what places say about themselves — how long a session runs, how many people are in the room, what it costs. Nothing here is a recommendation for a health concern; if you have one, that is a conversation for your GP or a registered practitioner.', 'oria' ); ?>
	</p>

	<script type="application/json" data-plan-rows><?php echo wp_json_encode( $oria_rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
	<script type="application/json" data-plan-week><?php echo wp_json_encode( ORIA_WEEK, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
	<script type="application/json" data-plan-palette><?php echo wp_json_encode( $oria_palette, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
</section>

<?php
get_footer();
