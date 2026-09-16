<?php
/**
 * A 301 map.
 *
 * There was no general redirect machinery here — one hard-coded case in
 * seo.php for the old /events/ archive, and nothing else. That was fine
 * while nothing moved. Two migrations now need it: five duplicate
 * specialty pages folding into their practice pages, and eighty-six area
 * URLs gaining a city segment.
 *
 * Deliberately an option rather than a table. The map is small, it is read
 * on every front-end request, and WordPress already caches options in
 * memory — a custom table would cost a query to save nothing. If this ever
 * passes a few thousand entries that calculation changes.
 *
 * Entries are added by migrations at the moment they move something, which
 * is the only moment the old URL is still knowable. A migration that
 * deletes a term and forgets the redirect cannot be repaired afterwards,
 * because the term link is gone.
 */

declare(strict_types=1);

namespace Oria\Core\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'oria_redirects';

function bootstrap(): void {
	// Priority 1: ahead of redirect_canonical and ahead of the 404 handler,
	// so a mapped URL never renders a template first.
	add_action( 'template_redirect', __NAMESPACE__ . '\maybe_redirect', 1 );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );

	/*
	 * Never let WordPress guess a permalink for a 404.
	 *
	 * redirect_guess_404_permalink() matches an unknown path against post
	 * slugs by prefix, and attachments are posts. In production that turned
	 * /perth/acupuncture/ into a 301 to Acupuncture.jpg -- a category page
	 * answering with a photograph. It does the same wherever a rewrite rule
	 * has not been flushed yet, which is exactly when the site can least
	 * afford to invent destinations.
	 *
	 * A 404 is a true answer. A confident redirect to the wrong thing is
	 * not, and it is the version search engines index.
	 */
	add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
}

/**
 * Normalise a path for storage and lookup: leading slash, trailing slash,
 * no host, no query. Both halves of every pair go through this, so a map
 * written with a full URL and looked up with a bare path still matches.
 */
function normalise( string $path ): string {
	$path = (string) wp_parse_url( $path, PHP_URL_PATH );
	$path = '/' . trim( $path, '/' );
	return '/' === $path ? '/' : $path . '/';
}

/** @return array<string, string> */
function all(): array {
	$map = get_option( OPTION, array() );
	return is_array( $map ) ? $map : array();
}

/**
 * Record one move.
 *
 * Refuses a self-redirect, which is the loop this whole file could
 * otherwise cause, and rewrites any existing entry that pointed at the old
 * URL so a two-step migration does not leave a chain. Chains cost a hop
 * each and Google gives up after a handful.
 */
function add( string $from, string $to ): bool {
	$from = normalise( $from );
	$to   = normalise( $to );

	if ( $from === $to ) {
		return false;
	}

	$map = all();

	// A → B already recorded, now B → C: repoint A straight at C.
	foreach ( $map as $old => $target ) {
		if ( normalise( $target ) === $from ) {
			$map[ $old ] = $to;
		}
	}

	$map[ $from ] = $to;
	update_option( OPTION, $map, false );

	return true;
}

function remove( string $from ): void {
	$map = all();
	unset( $map[ normalise( $from ) ] );
	update_option( OPTION, $map, false );
}

/**
 * Serve the 301.
 *
 * The query string is carried across. A campaign parameter on an old URL
 * is the one case where the thing arriving at the old address has something
 * worth keeping.
 */
function maybe_redirect(): void {
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( '' === $uri ) {
		return;
	}

	$map  = all();
	if ( ! $map ) {
		return;
	}

	$path = normalise( $uri );
	$to   = $map[ $path ] ?? '';

	/*
	 * Nothing recorded for this address.
	 *
	 * Almost always that is correct and we should keep out of the way. The
	 * exception is an address that is about to fail anyway: the migration
	 * recorded /practices/{category}/ice-bath/ but never /perth/ice-bath/,
	 * and with no rule of ours WordPress resolved that one as the attached
	 * photograph and Yoast sent it to the JPEG. A page about ice baths in
	 * Perth answered with an image, and kept its place in the index doing
	 * it. So: only when the request would 404 or has landed on an
	 * attachment, and only when the last segment genuinely names a facet.
	 */
	if ( '' === $to ) {
		if ( ! is_404() && ! is_attachment() ) {
			return;
		}
		$to = facet_target( $path );
		if ( '' === $to ) {
			return;
		}
	}

	/*
	 * Follow a recorded chain to its end rather than serving the first hop.
	 * add() repoints existing entries, but two separate migrations can still
	 * leave A -> B -> C, and /practice/yoga/pregnancy/ was exactly that.
	 * Each hop is a crawl Google may not spend.
	 */
	$hops = 0;
	while ( $hops < 3 ) {
		$next = $map[ normalise( $to ) ] ?? '';
		if ( '' === $next || normalise( $next ) === normalise( $to ) ) {
			break;
		}
		$to = $next;
		$hops++;
	}

	if ( $path === normalise( $to ) ) {
		return;
	}

	// A service that is another name for a specialty lands on the specialty.
	$to = twin_target( $path, $to );

	/*
	 * The migration wrote the bare city hub wherever it could not place a
	 * facet, and it could not place any whose address is a short alias --
	 * "pregnancy" is how pregnancy-yoga is addressed, "aqua" how
	 * aqua-fitness is. /practices/yoga/pregnancy/ still drew 203
	 * impressions a fortnight after the move, every one of them landing on
	 * a page about everything in Perth. The facet's own page exists; send
	 * people there.
	 */
	if ( is_city_hub( $to ) ) {
		$alt = facet_target( $path );
		if ( '' !== $alt ) {
			$to = $alt;
		}
	}

	// A 301 into a 404 is worse than the 404 alone; land on the parent.
	$to = survivable( $to );

	$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	$dest  = home_url( $to ) . ( '' !== $query ? '?' . $query : '' );

	wp_safe_redirect( $dest, 301 );
	exit;
}

/** Whether a destination is a bare city hub -- /explore/perth/ and nothing more. */
function is_city_hub( string $to ): bool {
	if ( ! function_exists( '\Oria\Core\Cities\get' ) ) {
		return false;
	}
	$seg = explode( '/', trim( $to, '/' ) );
	return 2 === count( $seg ) && 'explore' === $seg[0] && null !== \Oria\Core\Cities\get( $seg[1] );
}

/**
 * The specialty an old URL segment names, or '' when it names none.
 *
 * Three ways a segment can be one: it is the specialty's own slug; it is a
 * service that is another name for a specialty (ice-bath, cold-plunge); or
 * it is the shorter address the specialty answers on, which is what
 * PracticesIndex\specialty_slug() hands out and what the four aliases in
 * specialty-homes.json record. The migration knew only the first.
 */
function specialty_for( string $seg ): string {
	if ( '' === $seg || ! function_exists( '\Oria\Core\PracticesIndex\specialty_home' ) ) {
		return '';
	}

	$twin = function_exists( '\Oria\Core\PracticesIndex\specialty_twin' )
		? \Oria\Core\PracticesIndex\specialty_twin( $seg )
		: $seg;
	if ( '' !== \Oria\Core\PracticesIndex\specialty_home( $twin ) ) {
		return $twin;
	}

	if ( ! function_exists( '\Oria\Core\PracticesIndex\specialty_homes' )
		|| ! function_exists( '\Oria\Core\PracticesIndex\specialty_slug' ) ) {
		return '';
	}
	foreach ( array_keys( \Oria\Core\PracticesIndex\specialty_homes() ) as $spec ) {
		if ( \Oria\Core\PracticesIndex\specialty_slug( (string) $spec ) === $seg ) {
			return (string) $spec;
		}
	}

	return '';
}

/**
 * The canonical /explore/ address for an old path whose last segment names
 * a facet, or '' when it names none.
 *
 * The city is the one the old address named, else the default. The category
 * is the facet's home, read rather than guessed, and verified to still
 * exist -- see PracticesIndex\specialty_home_term().
 */
function facet_target( string $path ): string {
	if ( ! function_exists( '\Oria\Core\PracticesIndex\specialty_home_term' )
		|| ! function_exists( '\Oria\Core\PracticesIndex\specialty_slug' )
		|| ! function_exists( '\Oria\Core\Cities\get' ) ) {
		return '';
	}

	$seg = explode( '/', trim( $path, '/' ) );
	if ( count( $seg ) < 2 ) {
		return '';
	}

	$spec = specialty_for( (string) end( $seg ) );
	if ( '' === $spec ) {
		return '';
	}
	$home = \Oria\Core\PracticesIndex\specialty_home_term( $spec );
	if ( ! $home instanceof \WP_Term ) {
		return '';
	}

	$city = null !== \Oria\Core\Cities\get( $seg[0] )
		? $seg[0]
		: (string) ( \Oria\Core\Cities\default_city()['slug'] ?? 'perth' );

	return '/explore/' . $city . '/' . $home->slug . '/' . \Oria\Core\PracticesIndex\specialty_slug( $spec ) . '/';
}

/**
 * The specialty page for an old address whose last segment is a service
 * that is another name for a specialty.
 *
 * "ice-bath" is a service term; "cold-plunge" is the specialty it means, and
 * PracticesIndex\specialty_twin() says so. The migration built the stored
 * map by pattern and had no home category for a service, so every
 * /practices/{category}/ice-bath/ and /{city}/ice-bath/ was written down as
 * a redirect to the bare city hub -- 25 impressions a day, in August, for
 * "ice bath perth" searches, landing on a page about everything.
 *
 * Corrected here at redirect time rather than by rewriting the map, so the
 * stored option is untouched and the fix travels with the code. Only an
 * old address ending in a twinned service is changed; everything else
 * passes straight through.
 */
function twin_target( string $from, string $to ): string {
	if ( ! function_exists( '\Oria\Core\PracticesIndex\specialty_twin' )
		|| ! function_exists( '\Oria\Core\PracticesIndex\specialty_home_term' )
		|| ! function_exists( '\Oria\Core\Cities\get' ) ) {
		return $to;
	}
	$seg  = explode( '/', trim( $from, '/' ) );
	$tail = (string) end( $seg );
	if ( count( $seg ) < 2 || '' === $tail ) {
		return $to;
	}
	$twin = \Oria\Core\PracticesIndex\specialty_twin( $tail );
	if ( $twin === $tail ) {
		return $to;
	}
	$home = \Oria\Core\PracticesIndex\specialty_home_term( $twin );
	if ( ! $home instanceof \WP_Term ) {
		return $to;
	}

	// The city: the one the old address named, else the one the map chose,
	// else the default.
	$city = '';
	if ( \Oria\Core\Cities\get( $seg[0] ) ) {
		$city = $seg[0];
	} else {
		$dest = explode( '/', trim( $to, '/' ) );
		if ( 'explore' === ( $dest[0] ?? '' ) && ! empty( $dest[1] ) && \Oria\Core\Cities\get( $dest[1] ) ) {
			$city = $dest[1];
		}
	}
	if ( '' === $city ) {
		$city = (string) ( \Oria\Core\Cities\default_city()['slug'] ?? 'perth' );
	}

	return '/explore/' . $city . '/' . $home->slug . '/' . $twin . '/';
}

/**
 * The mapped destination, or its category parent when the destination is a
 * combination that no longer answers.
 *
 * The migration rewrote every old address into its new-format equivalent by
 * pattern, which is the only thing it could do -- but a category-by-suburb
 * page exists only while a listing sits in both, and facet_404() returns a
 * genuine 404 when none does. So /practice/retreats/east-victoria-park/ was
 * redirecting to /explore/perth/retreats/east-victoria-park/, which correctly
 * refuses to exist. Semrush found those by crawling the pre-migration URLs it
 * still had on file.
 *
 * A 301 into a 404 spends a crawl, strands the visitor and throws away
 * whatever the old URL had earned. The category page is the honest
 * destination: what the person asked for, minus a suburb holding nothing.
 *
 * Checked live rather than pruned out of the stored map, so the day a listing
 * opens in East Victoria Park the redirect lands on the suburb page again with
 * nothing to re-run. The cost is one term read and one query, and only on
 * requests that were already being redirected.
 */
function survivable( string $to ): string {
	$seg = explode( '/', trim( (string) wp_parse_url( $to, PHP_URL_PATH ), '/' ) );

	// Only the four-segment combination can empty out: explore/city/cat/tail.
	if ( 4 !== count( $seg ) || 'explore' !== $seg[0] ) {
		return $to;
	}
	if ( ! function_exists( '\Oria\Core\Cities\get' )
		|| ! function_exists( '\Oria\Core\PracticesIndex\resolve_facet' ) ) {
		return $to;
	}

	$city = \Oria\Core\Cities\get( $seg[1] );
	if ( ! $city ) {
		return $to;
	}

	$parent   = '/' . $seg[0] . '/' . $seg[1] . '/' . $seg[2] . '/';
	$practice = get_term_by( 'slug', $seg[2], \Oria\Core\Taxonomies\PRACTICE );
	if ( ! $practice instanceof \WP_Term ) {
		return $parent;
	}

	$facet = \Oria\Core\PracticesIndex\resolve_facet( $practice, $seg[3] );
	if ( null === $facet ) {
		return $parent;
	}

	// The same count facet_404() applies: listings in this combination, in
	// this city. Anything above zero still answers, indexable or not.
	$rows = \Oria\Core\PracticesIndex\facet_ids( $practice, $facet );
	if ( $rows && function_exists( '\Oria\Core\Cities\filter_ids' ) ) {
		$rows = \Oria\Core\Cities\filter_ids( $rows, $city );
	}

	return count( $rows ) > 0 ? $to : $parent;
}

/* ------------------------------------------------------------------ admin */

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'Redirects', 'oria' ),
		__( 'Redirects', 'oria' ),
		'manage_options',
		'oria-redirects',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$map = all();
	ksort( $map );

	echo '<div class="wrap"><h1>' . esc_html__( 'Redirects', 'oria' ) . '</h1>';
	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: %d: number of redirects. */
				_n( '%d permanent redirect, written by a migration.', '%d permanent redirects, written by migrations.', count( $map ), 'oria' ),
				count( $map )
			)
		)
	);

	if ( ! $map ) {
		echo '<p>' . esc_html__( 'Nothing has moved yet.', 'oria' ) . '</p></div>';
		return;
	}

	echo '<table class="widefat striped"><thead><tr>';
	printf( '<th>%s</th><th>%s</th><th>%s</th>', esc_html__( 'From', 'oria' ), esc_html__( 'To', 'oria' ), esc_html__( 'Target status', 'oria' ) );
	echo '</tr></thead><tbody>';

	foreach ( $map as $from => $to ) {
		// The check that matters: a 301 to a 404 is worse than the 404 it
		// replaced, because it looks deliberate.
		$exists = (bool) url_to_postid( home_url( $to ) ) || is_string( get_option( 'oria_redirects_skip_check' ) );
		printf(
			'<tr><td><code>%s</code></td><td><a href="%s"><code>%s</code></a></td><td>%s</td></tr>',
			esc_html( $from ),
			esc_url( home_url( $to ) ),
			esc_html( $to ),
			$exists ? '&mdash;' : '<span style="color:#996800">' . esc_html__( 'not a post — check it resolves', 'oria' ) . '</span>'
		);
	}

	echo '</tbody></table></div>';
}
