<?php
/**
 * v4 front page (design test) -- the design canvas's homepage, built from
 * live data, refined per the staging design review (19 Sep 2026):
 * "Quiet Discovery" -- fewer loud headings, fewer words in cards, better
 * photographs, one clear action per section.
 *
 * Overrides the parent's page.php for the front page only:
 *
 *   1. Hero: "How do you want to feel today?" with the mood chips
 *   2. The concierge: a sentence for Ask Oria (the ?q= door)
 *   3. Four ways in: a lead world and three supporting ones
 *   4. The Perth Reset: the latest journey, four stops, the rest counted
 *   5. Coming up: the next events, each with a feeling label
 *   6. Oria Field Notes: a photographic feature and three stories
 *   7. The map
 *   8. A few places worth knowing: three Best Of postcards
 *   9. Closing picture
 *
 * A section with nothing real to show is left out, never filled. Venue
 * photographs are the venue's own (featured image, else a cached Google
 * Places photo with its contributor credited); a venue never gets a stock
 * stand-in. Scene pictures are the theme's own, decoration, empty alt.
 *
 * @package Oria
 */

declare(strict_types=1);

use function Oria\Theme\tname;

get_header();

$oria_v4img = static fn( string $file ): string => get_stylesheet_directory_uri() . '/assets/img/' . $file;

// The city the directory is scoped to, and how many places it holds.
$oria_city  = function_exists( '\Oria\Core\Cities\current' ) ? \Oria\Core\Cities\current() : null;
$oria_cname = $oria_city && function_exists( '\Oria\Core\Cities\name' ) ? \Oria\Core\Cities\name( $oria_city ) : __( 'Perth', 'oria' );
$oria_total = count(
	get_posts(
		array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	)
);

// A listing's suburb, for the small line under a name.
$oria_suburb = static function ( int $id ): string {
	foreach ( \Oria\Theme\oria_terms_of( $id, 'area' ) as $t ) {
		if ( $t->parent ) {
			return tname( $t );
		}
	}
	return '';
};
$oria_from = static function ( int $id ): string {
	$p = get_field( 'price_from', $id );
	/* translators: %s: starting price */
	return is_numeric( $p ) && (float) $p > 0 ? sprintf( __( 'from $%s', 'oria' ), number_format_i18n( (float) $p ) ) : '';
};

/*
 * A venue's own photograph: its featured image, else the first cached
 * Google Places photo with the contributor Google requires us to credit.
 * Cache only -- the front page never waits on a live Places request -- and
 * never the parent's scene fallback: a named venue is not illustrated with
 * somewhere else.
 *
 * @return array{url:string, credit:string, credit_url:string}
 */
$oria_photo = static function ( int $id, int $width = 900 ): array {
	$out = array( 'url' => '', 'credit' => '', 'credit_url' => '' );
	$own = get_the_post_thumbnail_url( $id, 'large' );
	if ( $own ) {
		$out['url'] = (string) $own;
		return $out;
	}
	if ( function_exists( '\Oria\Core\Places\data_for' ) && function_exists( '\Oria\Core\Places\build' ) ) {
		$cache = \Oria\Core\Places\data_for( $id, false );
		if ( $cache ) {
			$set = \Oria\Core\Places\build( $cache, $width );
			if ( ! empty( $set['urls'][0] ) ) {
				$out['url']        = (string) $set['urls'][0];
				$out['credit']     = (string) ( $set['attributions'][0]['name'] ?? '' );
				$out['credit_url'] = (string) ( $set['attributions'][0]['uri'] ?? '' );
			}
		}
	}
	return $out;
};

// The first sentence of a piece of copy, capped at $max words.
$oria_sentence = static function ( string $text, int $max = 40 ): string {
	$text = trim( wp_strip_all_tags( $text ) );
	if ( preg_match( '/^.+?[.!?](?=\s|$)/u', $text, $m ) ) {
		$text = $m[0];
	}
	return wp_trim_words( $text, $max, '…' );
};

/*
 * The moods. Each chip swaps the hero picture and pre-writes a sentence in
 * the concierge; nothing is filtered until the visitor sends it. Phrased
 * as a place and a time, never a symptom (see page-ask.php). Only Calm is
 * requested with the page; the others load once the page is idle.
 */
$oria_moods = array(
	// id, chip (the visitor's words), what would help, image base, widths, the sentence for Ask Oria
	array( 'calm', __( 'I need to switch off', 'oria' ), __( 'Calm', 'oria' ), 'mood-meditation', array( 960, 1920 ), __( 'Somewhere calm to switch off', 'oria' ) ),
	array( 'recovery', __( 'My body feels tight', 'oria' ), __( 'Recovery', 'oria' ), 'mood-restored', array( 960, 1400 ), __( 'A massage, sauna or stretch session', 'oria' ) ),
	array( 'energy', __( 'I need more energy', 'oria' ), __( 'Energy', 'oria' ), 'mood-energised', array( 960, 1400 ), __( 'A class that gets me moving', 'oria' ) ),
	array( 'connection', __( 'I want to meet people', 'oria' ), __( 'Connection', 'oria' ), 'mood-connected', array( 960, 1920 ), __( 'A group class or workshop to meet people at', 'oria' ) ),
	array( 'escape', __( 'Something new this weekend', 'oria' ), __( 'Somewhere new', 'oria' ), 'mood-away', array( 960, 1280 ), __( 'Something new to try', 'oria' ) ),
);
// Where: the city's own regions, real areas only.
$oria_regions = function_exists( '\Oria\Core\Taxonomies\regions' ) ? \Oria\Core\Taxonomies\regions() : array();
$oria_regions = is_wp_error( $oria_regions ) ? array() : $oria_regions;
if ( $oria_city && function_exists( '\Oria\Core\Cities\for_area' ) ) {
	$oria_regions = array_values(
		array_filter(
			$oria_regions,
			static function ( $rt ) use ( $oria_city ): bool {
				$rc = \Oria\Core\Cities\for_area( $rt );
				return ! is_array( $rc ) || ( $rc['slug'] ?? '' ) === ( $oria_city['slug'] ?? '' );
			}
		)
	);
}
$oria_whens = array(
	'today'   => __( 'Today', 'oria' ),
	'weekend' => __( 'This weekend', 'oria' ),
	'week'    => __( 'This week', 'oria' ),
	'any'     => __( 'Any time', 'oria' ),
);
/*
 * The three dropdowns in the concierge bar. Each option is
 * [value, label, sub-line]. Rendered as a real <select> (the page without
 * scripting) plus a styled listbox that v4-home.js switches on.
 */
$oria_now  = (int) current_time( 'timestamp' );
$oria_dow  = (int) gmdate( 'N', $oria_now ); // 1 Mon .. 7 Sun
$oria_sat  = 6 === $oria_dow ? $oria_now : ( 7 === $oria_dow ? $oria_now - DAY_IN_SECONDS : $oria_now + ( 6 - $oria_dow ) * DAY_IN_SECONDS );
$oria_sun  = 7 === $oria_dow ? $oria_now : $oria_sat + DAY_IN_SECONDS;
$oria_dd_when = array(
	array( 'today', __( 'Today', 'oria' ), gmdate( 'l j M', $oria_now ) ),
	/* translators: 1: Saturday's date, 2: Sunday's date */
	array( 'this weekend', __( 'This weekend', 'oria' ), 7 === $oria_dow ? gmdate( 'l j M', $oria_now ) : sprintf( __( 'Sat %1$s – Sun %2$s', 'oria' ), gmdate( 'j', $oria_sat ), gmdate( 'j M', $oria_sun ) ) ),
	/* translators: %s: Sunday's date */
	array( 'this week', __( 'This week', 'oria' ), sprintf( __( 'Now until Sun %s', 'oria' ), gmdate( 'j M', $oria_sun ) ) ),
	array( 'any time', __( 'Any time', 'oria' ), __( 'No rush', 'oria' ) ),
);
// "Anywhere in Perth" counts Perth only, not every city in the directory.
$oria_city_total = $oria_total;
if ( $oria_city && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
	$oria_city_total = count(
		\Oria\Core\Cities\filter_ids(
			get_posts( array( 'post_type' => 'listing', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ),
			$oria_city
		)
	);
}
$oria_dd_where = array(
	/* translators: %s: city name */
	array( '', sprintf( __( 'Anywhere in %s', 'oria' ), $oria_cname ), sprintf( _n( '%s place', '%s places', $oria_city_total, 'oria' ), number_format_i18n( $oria_city_total ) ) ),
);
foreach ( $oria_regions as $oria_r ) {
	$oria_rn = count(
		get_posts(
			array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'area', 'field' => 'term_id', 'terms' => $oria_r->term_id, 'include_children' => true ) ),
			)
		)
	);
	if ( $oria_rn ) {
		/* translators: %s: number of places */
		$oria_dd_where[] = array( tname( $oria_r ), tname( $oria_r ), sprintf( _n( '%s place', '%s places', $oria_rn, 'oria' ), number_format_i18n( $oria_rn ) ) );
	}
}
$oria_dd = static function ( string $id, string $label, array $options, int $selected, string $kind ): void {
	$cur = $options[ $selected ];
	?>
	<div class="xh-bar__field xh-dd" data-xh-dd="<?php echo esc_attr( $kind ); ?>">
		<span class="xh-bar__label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $label ); ?></span>
		<button type="button" class="xh-dd__btn" id="<?php echo esc_attr( $id ); ?>-btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="<?php echo esc_attr( $id ); ?>-label <?php echo esc_attr( $id ); ?>-btn" hidden>
			<span class="xh-dd__value"><?php echo esc_html( $cur[1] ); ?></span>
			<svg class="xh-dd__chev" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
		</button>
		<ul class="xh-dd__list" id="<?php echo esc_attr( $id ); ?>-list" role="listbox" tabindex="-1" aria-labelledby="<?php echo esc_attr( $id ); ?>-label" hidden>
			<?php foreach ( $options as $i => $o ) : ?>
				<li class="xh-dd__opt" role="option" id="<?php echo esc_attr( $id . '-o' . $i ); ?>" data-value="<?php echo esc_attr( $o[0] ); ?>" aria-selected="<?php echo $i === $selected ? 'true' : 'false'; ?>">
					<span class="xh-dd__name"><?php echo esc_html( $o[1] ); ?></span>
					<?php if ( '' !== (string) $o[2] ) : ?>
						<span class="xh-dd__sub"><?php echo esc_html( $o[2] ); ?></span>
					<?php endif; ?>
					<svg class="xh-dd__tick" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 7.5 2.5 2.5L11 4.5"/></svg>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php // Without scripting: the plain select, which the script hides. ?>
		<select class="xh-bar__select xh-dd__native" aria-labelledby="<?php echo esc_attr( $id ); ?>-label" data-xh-<?php echo esc_attr( $kind ); ?>>
			<?php foreach ( $options as $i => $o ) : ?>
				<option value="<?php echo esc_attr( $o[0] ); ?>"<?php selected( $i, $selected ); ?>><?php echo esc_html( $o[1] ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
};
$oria_srcset = static fn( string $base, array $w ): string => sprintf( '%s %dw, %s %dw', esc_url( get_stylesheet_directory_uri() . "/assets/img/{$base}-{$w[0]}.webp" ), $w[0], esc_url( get_stylesheet_directory_uri() . "/assets/img/{$base}-{$w[1]}.webp" ), $w[1] );

// Four ways in: a line each, and at most two practices named. Counts
// belong on the results pages, not in the first emotional choice.
$oria_worlds = array(
	array( __( 'Slow down', 'oria' ), __( 'Quiet places for busy minds.', 'oria' ), 'world-slow-720.webp', array( 'mind', 'energy' ) ),
	array( __( 'Feel restored', 'oria' ), __( 'Warm rooms and unhurried hands.', 'oria' ), 'world-restored-720.webp', array( 'bodywork', 'spa' ) ),
	array( __( 'Come alive', 'oria' ), __( 'Classes and places that get you moving.', 'oria' ), 'world-alive-720.webp', array( 'fitness', 'yoga' ) ),
	array( __( 'Find your people', 'oria' ), __( 'Groups, workshops and good company.', 'oria' ), 'world-people-720.webp', array( 'community', 'creative' ) ),
);
$oria_world_links = static function ( array $slugs ) use ( $oria_city ): array {
	$out = array();
	foreach ( $slugs as $slug ) {
		$t = get_term_by( 'slug', $slug, 'practice' );
		if ( ! $t instanceof WP_Term || ! function_exists( '\Oria\Core\Intents\listings_in' ) ) {
			continue;
		}
		$ids = \Oria\Core\Intents\listings_in( $t );
		if ( $oria_city && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
			$ids = \Oria\Core\Cities\filter_ids( $ids, $oria_city );
		}
		if ( $ids ) {
			$out[] = array( tname( $t ), \Oria\Core\PracticesIndex\category_url( $t ) );
		}
	}
	return $out;
};

// The Perth Reset: the newest journey, its first four stops, the rest counted.
$oria_journey = function_exists( '\Oria\Core\Journeys\split' ) ? \Oria\Core\Journeys\split()['feature'] : null;
$oria_stops   = array();
$oria_more    = 0;
if ( $oria_journey instanceof WP_Post ) {
	foreach ( (array) ( get_field( 'journey', $oria_journey->ID ) ?: array() ) as $oria_row ) {
		$oria_lid  = (int) ( is_object( $oria_row['listing'] ?? null ) ? $oria_row['listing']->ID : ( $oria_row['listing'] ?? 0 ) );
		$oria_live = $oria_lid && 'publish' === get_post_status( $oria_lid );
		$oria_stops[] = array(
			'time'  => trim( (string) ( $oria_row['time'] ?? '' ) ),
			'label' => trim( (string) ( $oria_row['label'] ?? '' ) ),
			'name'  => $oria_live ? \Oria\Theme\ptitle( get_post( $oria_lid ) ) : '',
			'url'   => $oria_live ? (string) get_permalink( $oria_lid ) : '',
			'where' => $oria_lid ? $oria_suburb( $oria_lid ) : '',
			'photo' => $oria_live ? $oria_photo( $oria_lid, 1000 ) : array( 'url' => '' ),
		);
	}
	$oria_stops = array_values( array_filter( $oria_stops, static fn( array $s ): bool => '' !== $s['label'] || '' !== $s['name'] ) );
	$oria_more  = max( 0, count( $oria_stops ) - 4 );
	$oria_stops = array_slice( $oria_stops, 0, 4 );
}
// The picture beside the stops: the first stop with a photograph of its own.
$oria_reset_pic = null;
foreach ( $oria_stops as $oria_si => $oria_s ) {
	if ( '' !== $oria_s['photo']['url'] ) {
		$oria_reset_pic = $oria_si;
		break;
	}
}
// A short introduction: whole sentences, up to about 70 words.
$oria_intro = '';
if ( $oria_journey instanceof WP_Post ) {
	$oria_src = has_excerpt( $oria_journey ) ? get_the_excerpt( $oria_journey ) : wp_strip_all_tags( (string) $oria_journey->post_content );
	foreach ( (array) preg_split( '/(?<=[.!?])\s+/u', trim( (string) $oria_src ) ) as $oria_sent ) {
		if ( '' !== $oria_intro && str_word_count( $oria_intro . ' ' . $oria_sent ) > 70 ) {
			break;
		}
		$oria_intro = trim( $oria_intro . ' ' . $oria_sent );
	}
}

// Coming up: the next four events.
$oria_events = get_posts(
	array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'meta_key'       => 'event_start',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => 'event_start', 'value' => current_time( 'Y-m-d H:i:s' ), 'compare' => '>=', 'type' => 'DATETIME' ),
		),
	)
);
// event_start is naive local time: parse and print it without an offset
// (the same convention as the parent's starting-soon section).
$oria_when = static function ( WP_Post $ev, bool $long = false ): string {
	$ts = strtotime( (string) get_field( 'event_start', $ev->ID ) );
	return $ts ? gmdate( $long ? 'l j F · g.ia' : 'D j M · g.ia', $ts ) : '';
};
$oria_ev_where = static function ( WP_Post $ev ): string {
	foreach ( wp_get_post_terms( $ev->ID, 'area' ) as $t ) {
		if ( $t->parent ) {
			return tname( $t );
		}
	}
	return (string) get_field( 'venue', $ev->ID );
};
/*
 * One feeling word per event, read from its practice: the style first
 * (a yin class is quiet even though yoga moves), then its top category.
 * No practice, no label -- never a guess.
 */
$oria_feel = static function ( WP_Post $ev ): string {
	$quiet = array( 'yin-yoga', 'yin', 'restorative-yoga', 'yoga-nidra', 'meditation', 'sound', 'sound-healing', 'breathwork', 'mindfulness' );
	$move  = array( 'dance', 'dance-movement', 'ecstatic-dance' );
	$tops  = array(
		'mind' => __( 'Quiet', 'oria' ), 'energy' => __( 'Quiet', 'oria' ), 'spa' => __( 'Quiet', 'oria' ), 'bodywork' => __( 'Quiet', 'oria' ), 'natural' => __( 'Quiet', 'oria' ),
		'fitness' => __( 'Move', 'oria' ), 'yoga' => __( 'Move', 'oria' ),
		'community' => __( 'Social', 'oria' ), 'creative' => __( 'Social', 'oria' ), 'family' => __( 'Social', 'oria' ),
		'experiences' => __( 'Explore', 'oria' ),
	);
	foreach ( wp_get_post_terms( $ev->ID, 'practice' ) as $t ) {
		if ( in_array( $t->slug, $quiet, true ) ) {
			return __( 'Quiet', 'oria' );
		}
		if ( in_array( $t->slug, $move, true ) ) {
			return __( 'Move', 'oria' );
		}
		$top = $t;
		while ( $top instanceof WP_Term && $top->parent ) {
			$top = get_term( $top->parent, 'practice' );
		}
		if ( $top instanceof WP_Term && isset( $tops[ $top->slug ] ) ) {
			return $tops[ $top->slug ];
		}
	}
	return '';
};

// Field Notes: the latest four pieces of writing.
$oria_notes = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'no_found_rows'  => true,
	)
);
// A small content label: the post's category, unless it is the catch-all.
$oria_note_label = static function ( WP_Post $p ): string {
	foreach ( get_the_category( $p->ID ) as $c ) {
		if ( 'uncategorized' !== $c->slug && 'uncategorised' !== $c->slug ) {
			return $c->name;
		}
	}
	return '';
};

/*
 * A few places worth knowing: the first pick with a reason from each Best
 * Of guide, so the places are different and all edited. Three postcards,
 * each needing the venue's own photograph; a fourth pick becomes a line.
 */
$oria_cards = array();
if ( function_exists( '\Oria\Core\BestOf\guides' ) ) {
	$oria_seen = array();
	foreach ( \Oria\Core\BestOf\guides() as $oria_g ) {
		foreach ( \Oria\Core\BestOf\entries( (int) $oria_g->ID ) as $oria_e ) {
			if ( '' === $oria_e['reason'] || isset( $oria_seen[ $oria_e['listing'] ] ) ) {
				continue;
			}
			$oria_seen[ $oria_e['listing'] ] = true;
			$oria_cards[] = $oria_e + array( 'guide' => $oria_g, 'photo' => $oria_photo( (int) $oria_e['listing'] ) );
			break;
		}
		if ( count( $oria_cards ) >= 8 ) {
			break;
		}
	}
	// Photographed picks make the postcards; the next one is the extra line.
	$oria_with  = array_values( array_filter( $oria_cards, static fn( array $c ): bool => '' !== $c['photo']['url'] ) );
	$oria_extra = array_values( array_filter( $oria_cards, static fn( array $c ): bool => ! in_array( $c, array_slice( $oria_with, 0, 3 ), true ) ) );
	$oria_cards = array_slice( $oria_with, 0, 3 );
	$oria_extra = $oria_extra[0] ?? null;
}

// The display name: the part before a tagline separator, so a long
// "Name — what we do" never runs to five lines. The full name stays on
// the listing page and in its structured data.
$oria_short = static function ( string $name ): string {
	$cut = preg_split( '/\s+(?:—|–|\||:)\s+|\s+\(/u', $name );
	return trim( (string) ( $cut[0] ?? $name ) );
};
?>

<div class="xh">

<!-- 1. Hero -->
<section class="xh-hero" data-xh-hero aria-labelledby="xh-hero-title">
	<div class="xh-hero__pics" aria-hidden="true">
		<?php foreach ( $oria_moods as $oria_i => $oria_m ) : ?>
			<?php if ( 0 === $oria_i ) : ?>
				<img class="xh-hero__pic is-on" data-feel-pic="<?php echo esc_attr( $oria_m[0] ); ?>"
					src="<?php echo esc_url( $oria_v4img( "{$oria_m[3]}-{$oria_m[4][1]}.webp" ) ); ?>"
					srcset="<?php echo $oria_srcset( $oria_m[3], $oria_m[4] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>"
					sizes="100vw" width="1920" height="1280" alt="" fetchpriority="high" decoding="async">
			<?php else : ?>
				<?php // No src: loaded after the page has painted, or on the first tap (v4-home.js). ?>
				<img class="xh-hero__pic" data-feel-pic="<?php echo esc_attr( $oria_m[0] ); ?>"
					data-src="<?php echo esc_url( $oria_v4img( "{$oria_m[3]}-{$oria_m[4][1]}.webp" ) ); ?>"
					data-srcset="<?php echo $oria_srcset( $oria_m[3], $oria_m[4] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>"
					sizes="100vw" alt="" decoding="async">
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<div class="wrap xh-hero__copy on-deep">
		<p class="xh-hero__eyebrow">
			<?php
			/* translators: %s: city name */
			printf( esc_html__( '%s’s living wellness guide', 'oria' ), esc_html( $oria_cname ) );
			?>
		</p>
		<h1 class="display xh-hero__title" id="xh-hero-title"><?php esc_html_e( 'How do you want to feel today?', 'oria' ); ?></h1>
		<p class="lede xh-hero__lede">
			<?php
			/* translators: %s: city name */
			printf( esc_html__( 'Find somewhere in %s to move, recover, connect or simply stop for a while.', 'oria' ), esc_html( $oria_cname ) );
			?>
		</p>
		<div class="xh-actions xh-hero__actions">
			<a class="btn xh-btn-white" href="#concierge"><?php esc_html_e( 'Find my reset', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<a class="btn xh-btn-outline" href="<?php echo esc_url( home_url( '/wellness-map/' ) ); ?>"><?php esc_html_e( 'Explore nearby', 'oria' ); ?></a>
		</div>
	</div>

	<!-- 2. The concierge -->
	<div class="wrap xh-concierge-wrap">
		<form class="xh-concierge" id="concierge" action="<?php echo esc_url( home_url( '/ask/' ) ); ?>" method="get" data-xh-concierge>
			<div class="xh-concierge__top">
				<h2 class="xh-concierge__title"><?php esc_html_e( 'What would make today feel better?', 'oria' ); ?></h2>
				<a class="xh-link" href="<?php echo esc_url( home_url( '/ask/' ) ); ?>"><?php esc_html_e( 'Or tell Oria in your own words', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
			</div>
			<div class="xh-feel" role="group" aria-label="<?php esc_attr_e( 'How you feel', 'oria' ); ?>">
				<?php foreach ( $oria_moods as $oria_i => $oria_m ) : ?>
					<button type="button" class="xh-feel__chip" data-feel="<?php echo esc_attr( $oria_m[0] ); ?>" data-feel-help="<?php echo esc_attr( $oria_m[2] ); ?>" data-feel-say="<?php echo esc_attr( $oria_m[5] ); ?>" aria-pressed="<?php echo 0 === $oria_i ? 'true' : 'false'; ?>">
						<?php echo esc_html( $oria_m[1] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="xh-bar">
				<?php
				$oria_dd( 'xh-help', __( 'What would help?', 'oria' ), array_map( static fn( array $m ): array => array( $m[0], $m[2], $m[1] ), $oria_moods ), 0, 'help' );
				$oria_dd( 'xh-where', __( 'Where are you?', 'oria' ), $oria_dd_where, 0, 'where' );
				$oria_dd( 'xh-when', __( 'When?', 'oria' ), $oria_dd_when, 1, 'when' );
				?>
				<?php
				// The sentence Ask Oria receives. Written here for the page without
				// scripting; v4-home.js rewrites it from the choices on submit.
				?>
				<input type="hidden" name="q" value="<?php echo esc_attr( $oria_moods[0][5] . ' ' . strtolower( $oria_whens['weekend'] ) ); ?>" data-xh-q>
				<button class="btn xh-bar__go" type="submit"><?php esc_html_e( 'Find my reset', 'oria' ); ?></button>
			</div>
		</form>
	</div>
</section>

<!-- 3. Four ways in -->
<section class="wrap xh-sec xh-worlds" aria-labelledby="xh-worlds-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro"><?php esc_html_e( 'Four ways in', 'oria' ); ?></p>
			<h2 class="h1" id="xh-worlds-title"><?php esc_html_e( 'Start with how you feel, not what it’s called.', 'oria' ); ?></h2>
		</div>
		<div class="xh-worlds__nav" aria-hidden="false">
			<button type="button" class="xh-roundbtn" data-scroll-prev aria-controls="xh-worlds-track" aria-label="<?php esc_attr_e( 'Previous', 'oria' ); ?>">&larr;</button>
			<button type="button" class="xh-roundbtn" data-scroll-next aria-controls="xh-worlds-track" aria-label="<?php esc_attr_e( 'Next', 'oria' ); ?>">&rarr;</button>
		</div>
	</header>
	<div class="xh-worlds__grid" id="xh-worlds-track">
		<?php
		foreach ( $oria_worlds as $oria_w ) :
			$oria_links = $oria_world_links( $oria_w[3] );
			if ( ! $oria_links ) {
				continue;
			}
			?>
			<article class="xh-world">
				<img class="xh-world__pic" src="<?php echo esc_url( $oria_v4img( $oria_w[2] ) ); ?>" alt="" loading="lazy" decoding="async" width="720" height="960">
				<div class="xh-world__body on-deep">
					<h3 class="h2 xh-world__title"><?php echo esc_html( $oria_w[0] ); ?></h3>
					<p class="xh-world__line"><?php echo esc_html( $oria_w[1] ); ?></p>
					<p class="xh-world__links">
						<?php foreach ( $oria_links as $oria_li => $oria_l ) : ?>
							<?php echo $oria_li ? '<span aria-hidden="true">·</span>' : ''; ?>
							<a href="<?php echo esc_url( $oria_l[1] ); ?>"><?php echo esc_html( $oria_l[0] ); ?></a>
						<?php endforeach; ?>
					</p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( $oria_journey instanceof WP_Post && count( $oria_stops ) >= 2 ) : ?>
<!-- 4. The Perth Reset: a real journey -->
<section class="wrap xh-sec" aria-labelledby="xh-reset-title">
	<div class="xh-reset on-deep" data-xh-reset>
		<div class="xh-reset__text">
			<p class="micro">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'The %s Reset', 'oria' ), esc_html( $oria_cname ) );
				?>
			</p>
			<h2 class="h1" id="xh-reset-title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_journey ) ); ?></h2>
			<?php if ( '' !== $oria_intro ) : ?>
				<p class="xh-reset__lede"><?php echo esc_html( $oria_intro ); ?></p>
			<?php endif; ?>
			<?php if ( null !== $oria_reset_pic ) : ?>
				<?php $oria_rp = $oria_stops[ $oria_reset_pic ]['photo']; ?>
				<figure class="xh-reset__pic">
					<img src="<?php echo esc_url( $oria_rp['url'] ); ?>" alt="" loading="lazy" decoding="async" data-reset-img>
					<figcaption class="xh-credit" data-reset-credit><?php echo '' !== $oria_rp['credit'] ? esc_html( sprintf( /* translators: %s: photographer */ __( 'Photo via Google — %s', 'oria' ), $oria_rp['credit'] ) ) : ''; ?></figcaption>
				</figure>
			<?php endif; ?>
		</div>
		<div class="xh-reset__route">
			<ol class="xh-stops">
				<?php foreach ( $oria_stops as $oria_si => $oria_s ) : ?>
					<li class="xh-stop<?php echo $oria_si === $oria_reset_pic ? ' is-active' : ''; ?>"
						<?php if ( '' !== $oria_s['photo']['url'] ) : ?>
							data-stop-img="<?php echo esc_url( $oria_s['photo']['url'] ); ?>"
							data-stop-credit="<?php echo esc_attr( '' !== $oria_s['photo']['credit'] ? sprintf( /* translators: %s: photographer */ __( 'Photo via Google — %s', 'oria' ), $oria_s['photo']['credit'] ) : '' ); ?>"
						<?php endif; ?>>
						<span class="xh-stop__time"><?php echo esc_html( $oria_s['time'] ); ?></span>
						<div>
							<?php if ( '' !== $oria_s['label'] ) : ?>
								<p class="micro xh-stop__label"><?php echo esc_html( $oria_s['label'] ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $oria_s['name'] ) : ?>
								<h3 class="xh-stop__name"><a href="<?php echo esc_url( $oria_s['url'] ); ?>"><?php echo esc_html( $oria_s['name'] ); ?></a></h3>
							<?php endif; ?>
							<?php if ( '' !== $oria_s['where'] ) : ?>
								<p class="xh-stop__where"><?php echo esc_html( $oria_s['where'] ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ( $oria_more ) : ?>
				<p class="xh-stops__more">
					<?php
					/* translators: %d: number of further stops */
					printf( esc_html( _n( '+ %d more stop', '+ %d more stops', $oria_more, 'oria' ) ), (int) $oria_more );
					?>
				</p>
			<?php endif; ?>
			<div class="xh-actions">
				<a class="btn btn--light" href="<?php echo esc_url( get_permalink( $oria_journey ) ); ?>"><?php esc_html_e( 'See the whole day', 'oria' ); ?></a>
				<a class="xh-link xh-link--light" href="<?php echo esc_url( home_url( '/journeys/' ) ); ?>"><?php esc_html_e( 'More journeys', 'oria' ); ?></a>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_events ) : ?>
<!-- 5. Coming up -->
<section class="wrap xh-sec" aria-labelledby="xh-week-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro">
				<?php
				/* translators: %s: city name */
				printf( esc_html__( 'Coming up in %s', 'oria' ), esc_html( $oria_cname ) );
				?>
			</p>
			<h2 class="h1" id="xh-week-title"><?php esc_html_e( 'Chosen by how it feels, not by the clock.', 'oria' ); ?></h2>
		</div>
		<a class="xh-link" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( 'Everything coming up', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</header>
	<div class="xh-week">
		<?php
		foreach ( $oria_events as $oria_i => $oria_ev ) :
			$oria_lead  = 0 === $oria_i;
			$oria_evimg = get_the_post_thumbnail_url( $oria_ev, $oria_lead ? 'large' : 'medium' );
			$oria_ef    = $oria_feel( $oria_ev );
			$oria_ew    = $oria_ev_where( $oria_ev );
			?>
			<a class="xh-ev<?php echo $oria_lead ? ' xh-ev--lead on-deep' : ''; ?>" href="<?php echo esc_url( get_permalink( $oria_ev ) ); ?>">
				<?php if ( $oria_evimg ) : ?>
					<span class="xh-ev__media"><img class="xh-ev__pic" src="<?php echo esc_url( $oria_evimg ); ?>" alt="" loading="lazy" decoding="async"></span>
				<?php elseif ( ! $oria_lead ) : ?>
					<span class="xh-ev__media xh-ev__media--field" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="xh-ev__body">
					<?php if ( '' !== $oria_ef ) : ?>
						<span class="xh-ev__feel"><?php echo esc_html( $oria_ef ); ?></span>
					<?php endif; ?>
					<span class="xh-ev__name"><?php echo esc_html( \Oria\Theme\ptitle( $oria_ev ) ); ?></span>
					<span class="xh-ev__when"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_when( $oria_ev ), $oria_ew ) ) ) ); ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_notes ) : ?>
<!-- 6. Oria Field Notes -->
<section class="wrap xh-sec" aria-labelledby="xh-notes-title">
	<header class="xh-sec__head xh-sec__head--rule">
		<h2 class="h1" id="xh-notes-title"><?php esc_html_e( 'Oria', 'oria' ); ?> <span class="edit"><?php esc_html_e( 'Field Notes', 'oria' ); ?></span></h2>
		<p class="xh-sec__aside"><?php esc_html_e( 'Local, first-hand and specific: the places worth the drive, and the questions a booking page never answers.', 'oria' ); ?></p>
	</header>
	<div class="xh-notes">
		<?php
		$oria_lead_note = array_shift( $oria_notes );
		$oria_ln_img    = get_the_post_thumbnail_url( $oria_lead_note, 'large' );
		$oria_ln_label  = $oria_note_label( $oria_lead_note );
		?>
		<a class="xh-note xh-note--lead<?php echo $oria_ln_img ? ' on-deep' : ''; ?>" href="<?php echo esc_url( get_permalink( $oria_lead_note ) ); ?>">
			<?php if ( $oria_ln_img ) : ?>
				<span class="xh-note__media"><img class="xh-note__pic" src="<?php echo esc_url( $oria_ln_img ); ?>" alt="" loading="lazy" decoding="async"></span>
			<?php endif; ?>
			<span class="xh-note__body">
				<?php if ( '' !== $oria_ln_label ) : ?>
					<span class="xh-note__label"><?php echo esc_html( $oria_ln_label ); ?></span>
				<?php endif; ?>
				<span class="xh-note__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_lead_note ) ); ?></span>
				<?php if ( has_excerpt( $oria_lead_note ) ) : ?>
					<span class="xh-note__lede"><?php echo esc_html( $oria_sentence( get_the_excerpt( $oria_lead_note ), 30 ) ); ?></span>
				<?php endif; ?>
			</span>
		</a>
		<div class="xh-notes__side">
			<?php
			foreach ( $oria_notes as $oria_ni => $oria_n ) :
				$oria_nimg = 0 === $oria_ni ? get_the_post_thumbnail_url( $oria_n, 'medium_large' ) : '';
				$oria_nl   = $oria_note_label( $oria_n );
				?>
				<a class="xh-note<?php echo $oria_nimg ? ' xh-note--pic' : ''; ?>" href="<?php echo esc_url( get_permalink( $oria_n ) ); ?>">
					<?php if ( $oria_nimg ) : ?>
						<span class="xh-note__thumb"><img src="<?php echo esc_url( $oria_nimg ); ?>" alt="" loading="lazy" decoding="async"></span>
					<?php endif; ?>
					<span class="xh-note__text">
						<?php if ( '' !== $oria_nl ) : ?>
							<span class="xh-note__label"><?php echo esc_html( $oria_nl ); ?></span>
						<?php endif; ?>
						<span class="xh-note__title"><?php echo esc_html( \Oria\Theme\ptitle( $oria_n ) ); ?></span>
						<span class="xh-note__date"><?php echo esc_html( get_the_date( 'j F Y', $oria_n ) ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
			<a class="xh-link" href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'All Field Notes', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
	</div>
</section>
<?php endif; ?>

<!-- 7. The map -->
<section class="wrap xh-sec" aria-labelledby="xh-map-title">
	<div class="xh-map on-deep">
		<div class="xh-map__text">
			<h2 class="h1" id="xh-map-title"><?php esc_html_e( 'Something nearby you haven’t tried yet.', 'oria' ); ?></h2>
			<?php if ( $oria_total ) : ?>
				<p>
					<?php
					/* translators: %s: number of listings */
					printf( esc_html__( '%s places on one map, each checked by hand.', 'oria' ), esc_html( number_format_i18n( $oria_total ) ) );
					?>
				</p>
			<?php endif; ?>
			<a class="btn btn--light" href="<?php echo esc_url( home_url( '/wellness-map/' ) ); ?>"><?php esc_html_e( 'Open the map', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
		</div>
		<div class="xh-map__art" aria-hidden="true">
			<img class="xh-map__pic" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/wellness-map-hero-1280.webp' ); ?>" alt="" loading="lazy" decoding="async" width="1280" height="720">
			<?php // Decoration only: a hint of places on the coast, not real positions. ?>
			<svg class="xh-map__route" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M18 78 C 30 62, 36 58, 46 52 S 64 36, 78 24" fill="none" stroke="currentColor" stroke-width=".35" stroke-dasharray="1.2 1.4" vector-effect="non-scaling-stroke"/></svg>
			<span class="xh-map__pin" style="--x:18%;--y:78%;--d:0s"></span>
			<span class="xh-map__pin" style="--x:46%;--y:52%;--d:.25s"></span>
			<span class="xh-map__pin" style="--x:78%;--y:24%;--d:.5s"></span>
		</div>
	</div>
</section>

<?php if ( count( $oria_cards ) >= 2 ) : ?>
<!-- 8. A few places worth knowing -->
<section class="wrap xh-sec" aria-labelledby="xh-cards-title">
	<header class="xh-sec__head">
		<div>
			<p class="micro"><span aria-hidden="true">&#10022;</span> <?php esc_html_e( 'From our Best Of guides', 'oria' ); ?></p>
			<h2 class="h1" id="xh-cards-title"><?php esc_html_e( 'A few places worth knowing', 'oria' ); ?></h2>
		</div>
		<a class="xh-link" href="<?php echo esc_url( function_exists( '\Oria\Core\BestOf\hub_url' ) ? \Oria\Core\BestOf\hub_url() : home_url( '/best/' ) ); ?>"><?php esc_html_e( 'Every Best Of guide', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
	</header>
	<div class="xh-cards">
		<?php
		foreach ( $oria_cards as $oria_i => $oria_c ) :
			$oria_lid  = (int) $oria_c['listing'];
			$oria_full = \Oria\Theme\ptitle( get_post( $oria_lid ) );
			$oria_meta = array_filter( array( $oria_suburb( $oria_lid ), $oria_from( $oria_lid ) ) );
			?>
			<article class="xh-card<?php echo 0 === $oria_i ? ' xh-card--lead' : ''; ?>">
				<div class="xh-card__media">
					<img src="<?php echo esc_url( $oria_c['photo']['url'] ); ?>" alt="" loading="lazy" decoding="async">
					<?php if ( '' !== $oria_c['photo']['credit'] ) : ?>
						<span class="xh-credit xh-credit--on">
							<?php
							/* translators: %s: photographer */
							echo esc_html( sprintf( __( 'Photo via Google — %s', 'oria' ), $oria_c['photo']['credit'] ) );
							?>
						</span>
					<?php endif; ?>
				</div>
				<div class="xh-card__body">
					<p class="xh-card__award"><span aria-hidden="true">&#10022;</span> <?php echo esc_html( $oria_c['label'] ); ?></p>
					<h3 class="xh-card__name"><a href="<?php echo esc_url( get_permalink( $oria_lid ) ); ?>" title="<?php echo esc_attr( $oria_full ); ?>"><?php echo esc_html( $oria_short( $oria_full ) ); ?></a></h3>
					<?php if ( $oria_meta ) : ?>
						<p class="xh-card__meta"><?php echo esc_html( implode( ' · ', $oria_meta ) ); ?></p>
					<?php endif; ?>
					<p class="xh-card__why"><?php echo esc_html( $oria_sentence( (string) $oria_c['reason'], 40 ) ); ?></p>
					<a class="xh-card__guide" href="<?php echo esc_url( get_permalink( $oria_c['guide'] ) ); ?>"><?php esc_html_e( 'Why we chose it', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<?php if ( $oria_extra ) : ?>
		<p class="xh-cards__more">
			<?php esc_html_e( 'Also shortlisted:', 'oria' ); ?>
			<a href="<?php echo esc_url( get_permalink( (int) $oria_extra['listing'] ) ); ?>"><?php echo esc_html( $oria_short( \Oria\Theme\ptitle( get_post( (int) $oria_extra['listing'] ) ) ) ); ?></a>
			(<?php echo esc_html( (string) $oria_extra['label'] ); ?>) &mdash;
			<?php esc_html_e( 'from', 'oria' ); ?>
			<a href="<?php echo esc_url( get_permalink( $oria_extra['guide'] ) ); ?>"><?php echo esc_html( \Oria\Theme\ptitle( $oria_extra['guide'] ) ); ?></a>
		</p>
	<?php endif; ?>
</section>
<?php endif; ?>

<!-- 9. Closing: a room being made ready -->
<section class="wrap xh-sec">
	<div class="xh-close on-deep">
		<img class="xh-close__pic" src="<?php echo esc_url( $oria_v4img( 'close-studio-1400.webp' ) ); ?>"
			srcset="<?php echo esc_url( $oria_v4img( 'close-studio-960.webp' ) ); ?> 960w, <?php echo esc_url( $oria_v4img( 'close-studio-1400.webp' ) ); ?> 1400w"
			sizes="(min-width: 80rem) 1320px, 100vw" alt="" loading="lazy" decoding="async" width="1400" height="1050">
		<div class="xh-close__text">
			<h2 class="display xh-close__title"><?php esc_html_e( 'Somewhere nearby, a room is already being prepared.', 'oria' ); ?></h2>
			<div class="xh-actions">
				<a class="btn btn--light" href="<?php echo esc_url( \Oria\Core\Explore\base_url( $oria_city ) ); ?>"><?php esc_html_e( 'Find something for today', 'oria' ); ?></a>
				<a class="btn xh-btn-glass" href="<?php echo esc_url( home_url( '/ask/' ) ); ?>"><?php esc_html_e( 'Ask Oria', 'oria' ); ?></a>
			</div>
		</div>
	</div>
</section>

</div>

<?php
get_footer();
