<?php
/**
 * Collection: polite HTTP fetching and structured-data extraction.
 *
 * The workhorse is schema.org JSON-LD — Eventbrite, Humanitix and most
 * studio sites embed an Event object in every event page. A watchlist URL
 * can be a single event page, a listing/organiser page (event links are
 * discovered and followed, a few per run), or an .ics calendar feed.
 *
 * robots.txt is checked before every fetch and disallowed paths skipped.
 */

declare(strict_types=1);

namespace Oria\Ingest\Fetch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UA        = 'OriaHavenBot/0.1 (+https://oriahaven.com.au; wellness events directory; contact: hello@oriahaven.com.au)';
// Event links followed per listing page per run. A Humanitix "this week"
// browse page returns about a dozen, and six would have quietly halved it.
const MAX_FOLLOW = 12;

/** @var array<string, array<string>> per-host robots Disallow cache for this run */
function robots_disallows( string $host_url ): array {
	static $cache = array();
	$host = wp_parse_url( $host_url, PHP_URL_HOST ) ?: '';
	if ( isset( $cache[ $host ] ) ) {
		return $cache[ $host ];
	}
	$scheme = wp_parse_url( $host_url, PHP_URL_SCHEME ) ?: 'https';
	$res    = wp_remote_get( "{$scheme}://{$host}/robots.txt", array( 'timeout' => 10, 'user-agent' => UA ) );
	$rules  = array();
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$applies = false;
		foreach ( preg_split( '/\r?\n/', wp_remote_retrieve_body( $res ) ) as $line ) {
			$line = trim( preg_replace( '/#.*/', '', $line ) ?? '' );
			if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $m ) ) {
				$agent   = trim( $m[1] );
				$applies = ( '*' === $agent || false !== stripos( UA, $agent ) );
			} elseif ( $applies && preg_match( '/^disallow:\s*(\S*)$/i', $line, $m ) && '' !== $m[1] ) {
				$rules[] = $m[1];
			}
		}
	}
	$cache[ $host ] = $rules;
	return $rules;
}

function allowed( string $url ): bool {
	$path = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
	foreach ( robots_disallows( $url ) as $rule ) {
		$quoted = str_replace( '\*', '.*', preg_quote( $rule, '#' ) );
		if ( preg_match( '#^' . $quoted . '#', $path ) ) {
			return false;
		}
	}
	return true;
}

/** One polite GET. Returns body or null. */
function get( string $url ): ?string {
	if ( ! allowed( $url ) ) {
		return null;
	}
	$res = wp_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'user-agent'  => UA,
			'redirection' => 3,
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	return (string) wp_remote_retrieve_body( $res );
}

/**
 * Every schema.org Event found in a page's JSON-LD blocks, flattened to
 * the pipeline's candidate shape. Handles @graph wrappers and arrays.
 *
 * @return array<int, array<string, string>>
 */
function events_from_html( string $html, string $page_url ): array {
	$out = array();
	if ( ! preg_match_all( '#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#si', $html, $m ) ) {
		return $out;
	}
	foreach ( $m[1] as $block ) {
		$data = json_decode( html_entity_decode( trim( $block ), ENT_QUOTES | ENT_HTML5 ), true );
		if ( ! is_array( $data ) ) {
			continue;
		}
		foreach ( flatten_nodes( $data ) as $node ) {
			/*
			 * Not every event says "Event". schema.org has two dozen
			 * subtypes and the platforms use them: Eventbrite files a
			 * festival as Festival, which contains none of the letters
			 * this test was looking for, so the Perth Festival of Healing
			 * was skipped on every pass.
			 */
			$type = (array) ( $node['@type'] ?? array() );
			if ( ! array_filter( $type, static fn( $t ) => is_string( $t ) && is_event_type( $t ) ) ) {
				continue;
			}
			$loc   = $node['location'] ?? array();
			$addr  = is_array( $loc ) ? ( $loc['address'] ?? array() ) : array();
			$offer = $node['offers'] ?? array();
			if ( isset( $offer[0] ) ) {
				$offer = $offer[0];
			}
			$org = $node['organizer'] ?? array();
			if ( isset( $org[0] ) ) {
				$org = $org[0];
			}
			$out[] = array(
				'title'      => sanitize_text_field( (string) ( $node['name'] ?? '' ) ),
				'description'=> sanitize_textarea_field( wp_strip_all_tags( (string) ( $node['description'] ?? '' ) ) ),
				'start'      => sanitize_text_field( (string) ( $node['startDate'] ?? '' ) ),
				'end'        => sanitize_text_field( (string) ( $node['endDate'] ?? '' ) ),
				'venue'      => sanitize_text_field( (string) ( is_array( $loc ) ? ( $loc['name'] ?? '' ) : $loc ) ),
				'suburb'     => sanitize_text_field( (string) ( is_array( $addr ) ? ( $addr['addressLocality'] ?? '' ) : '' ) ),
				'region'     => sanitize_text_field( (string) ( is_array( $addr ) ? ( $addr['addressRegion'] ?? '' ) : '' ) ),
				/*
				 * Eventbrite and Humanitix both publish AggregateOffer, which
				 * carries lowPrice/highPrice and no `price` at all — so asking
				 * only for `price` returned empty on every event ever imported.
				 * A plain Offer still wins where one is given.
				 */
				'price'      => sanitize_text_field( (string) ( is_array( $offer ) ? ( $offer['price'] ?? $offer['lowPrice'] ?? '' ) : '' ) ),
				'price_high' => sanitize_text_field( (string) ( is_array( $offer ) ? ( $offer['highPrice'] ?? '' ) : '' ) ),
				'currency'   => sanitize_text_field( (string) ( is_array( $offer ) ? ( $offer['priceCurrency'] ?? '' ) : '' ) ),
				'organiser'  => sanitize_text_field( (string) ( is_array( $org ) ? ( $org['name'] ?? '' ) : $org ) ),
				/*
				 * Five more facts the platforms already publish for
				 * exactly this purpose, and which the page had no way to
				 * say: where precisely, whether it is still happening,
				 * whether there are tickets left, what the tiers cost,
				 * and who to read about next.
				 */
				'street'     => sanitize_text_field( (string) ( is_array( $addr ) ? ( $addr['streetAddress'] ?? '' ) : '' ) ),
				'postcode'   => sanitize_text_field( (string) ( is_array( $addr ) ? ( $addr['postalCode'] ?? '' ) : '' ) ),
				'status'     => sanitize_text_field( basename( (string) ( $node['eventStatus'] ?? '' ) ) ),
				'availability' => sanitize_text_field( basename( (string) ( is_array( $offer ) ? ( $offer['availability'] ?? '' ) : '' ) ) ),
				'tiers'      => tiers_from( $node['offers'] ?? array() ),
				'organiser_url' => esc_url_raw( (string) ( is_array( $org ) ? ( $org['url'] ?? '' ) : '' ) ),
				'url'        => esc_url_raw( (string) ( $node['url'] ?? $page_url ) ),
				'image'      => esc_url_raw( is_array( $node['image'] ?? null ) ? (string) ( $node['image'][0] ?? '' ) : (string) ( $node['image'] ?? '' ) ),
				'source_url' => esc_url_raw( $page_url ),
			);
		}
	}
	return $out;
}

/**
 * Is this schema.org type an Event?
 *
 * The subtypes are a closed list on schema.org, so it is written out
 * rather than guessed at: matching on a substring would let "EventVenue"
 * and "SaleEvent" through, and matching only "Event" lets nothing but
 * the base type through.
 */
function is_event_type( string $type ): bool {
	$type = trim( str_replace( 'https://schema.org/', '', $type ) );
	if ( 'Event' === $type ) {
		return true;
	}
	return in_array(
		$type,
		array(
			'BusinessEvent',
			'ChildrensEvent',
			'ComedyEvent',
			'CourseInstance',
			'DanceEvent',
			'DeliveryEvent',
			'EducationEvent',
			'ExhibitionEvent',
			'Festival',
			'FoodEvent',
			'Hackathon',
			'LiteraryEvent',
			'MusicEvent',
			'PublicationEvent',
			'SaleEvent',
			'ScreeningEvent',
			'SocialEvent',
			'SportsEvent',
			'TheaterEvent',
			'VisualArtsEvent',
		),
		true
	);
}

/**
 * The named ticket tiers, deduplicated, cheapest first.
 *
 * "Pay what you can", "Concession $33", "General $55" is a far more
 * useful thing to tell somebody than "from $0" -- and it is what the
 * organiser published, not an inference about it. An AggregateOffer
 * carries no name and is skipped; two tiers with the same name and price
 * are one tier said twice.
 *
 * @return list<array{name: string, price: float}>
 */
function tiers_from( $offers ): array {
	if ( ! is_array( $offers ) ) {
		return array();
	}
	if ( isset( $offers['@type'] ) ) {
		$offers = array( $offers );
	}

	/*
	 * Grouped by name, not by name and price. Organisers publish the same
	 * tier twice -- one Humanitix event has a "Concession" at $0 and a
	 * "Concession " at $33 -- and listing both makes our page look wrong
	 * rather than theirs. One line, and a range where the prices differ.
	 */
	$by_name = array();
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$name = trim( (string) ( $offer['name'] ?? '' ) );
		if ( '' === $name || ! isset( $offer['price'] ) ) {
			continue; // an AggregateOffer, or a tier with nothing to say
		}
		$key = strtolower( $name );
		if ( ! isset( $by_name[ $key ] ) ) {
			$by_name[ $key ] = array( 'name' => sanitize_text_field( $name ), 'price' => (float) $offer['price'], 'high' => (float) $offer['price'] );
		}
		$by_name[ $key ]['price'] = min( $by_name[ $key ]['price'], (float) $offer['price'] );
		$by_name[ $key ]['high']  = max( $by_name[ $key ]['high'], (float) $offer['price'] );
	}

	$out = array_values( $by_name );
	usort( $out, static fn( array $a, array $b ): int => $a['price'] <=> $b['price'] );
	return array_slice( $out, 0, 6 );
}

/** Walk any JSON-LD shape ( @graph, arrays, nested ) yielding assoc nodes. */
function flatten_nodes( array $data ): array {
	$nodes = array();
	$stack = array( $data );
	while ( $stack ) {
		$cur = array_pop( $stack );
		if ( isset( $cur['@type'] ) ) {
			$nodes[] = $cur;
		}
		foreach ( $cur as $v ) {
			if ( is_array( $v ) ) {
				$stack[] = $v;
			}
		}
	}
	return $nodes;
}

/**
 * Candidate event-page links on a listing/organiser page: same-host links
 * whose path looks like an event detail page.
 *
 * @return array<string>
 */
function event_links( string $html, string $page_url ): array {
	$host = wp_parse_url( $page_url, PHP_URL_HOST );
	if ( ! $host || ! preg_match_all( '#href=["\']([^"\'\s>]+)["\']#i', $html, $m ) ) {
		return array();
	}
	$links = array();
	foreach ( array_unique( $m[1] ) as $href ) {
		$abs = \WP_Http::make_absolute_url( html_entity_decode( $href ), $page_url );
		if ( wp_parse_url( $abs, PHP_URL_HOST ) !== $host ) {
			continue;
		}
		$path = (string) ( wp_parse_url( $abs, PHP_URL_PATH ) ?: '' );
		if ( preg_match( '#/(e|event|events|host)/[^/]+#i', $path ) && ! preg_match( '#/(events?)/?$#i', $path ) ) {
			$links[ clean_link( strtok( $abs, '#' ) ) ] = true;
		}
	}
	return array_slice( array_keys( $links ), 0, MAX_FOLLOW );
}


/**
 * A discovered event URL without the referrer's tracking on it.
 *
 * Meetup hands back every link stamped with recId, searchId and eventOrigin
 * identifying the search that found it. They are not part of the address —
 * they expire, they differ between two searches that return the same event,
 * and stored as booking_url they send a visitor through somebody else's
 * analytics. Only known tracking keys are dropped: some sources genuinely
 * need a query string to reach the event, so nothing else is touched.
 */
function clean_link( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
		return $url;
	}
	parse_str( (string) $parts['query'], $q );
	foreach ( array_keys( $q ) as $k ) {
		$key = strtolower( (string) $k );
		if ( 'rec' === substr( $key, 0, 3 ) || 'utm_' === substr( $key, 0, 4 )
			|| in_array( $key, array( 'searchid', 'eventorigin', 'fbclid', 'gclid', 'ref', 'aff' ), true ) ) {
			unset( $q[ $k ] );
		}
	}
	$base = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' )
		. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
		. ( $parts['path'] ?? '' );
	return $q ? $base . '?' . http_build_query( $q ) : $base;
}

/**
 * Event URLs out of a Next.js data island.
 *
 * Humanitix browse pages — /au/events/au--wa--perth/healthandwellness--thisweek
 * and the like — draw their cards client-side. The served HTML carries the
 * navigation and nothing else, so event_links() above finds none of them and
 * the page looks empty. Every event is present in __NEXT_DATA__ though, each
 * naming its own host and slug.
 *
 * Only the URLs are taken from it. Each event page is then fetched and read
 * through events_from_html() like any other source, so the candidate shape,
 * the robots check and the image handling all stay in one place. The browse
 * page is treated as a table of contents, never as the record itself.
 *
 * Note the events live on events.humanitix.com while the browse page is on
 * humanitix.com, so these deliberately cross hosts — which is why they are
 * gathered here rather than inside event_links()'s same-host filter. get()
 * still checks the new host's robots.txt before fetching.
 *
 * @return array<int, string>
 */
function next_data_links( string $html, string $page_url ): array {
	if ( ! preg_match( '#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#si', $html, $m ) ) {
		return array();
	}

	$data = json_decode( trim( $m[1] ), true );
	$rows = $data['props']['pageProps']['events'] ?? null;
	if ( ! is_array( $rows ) ) {
		return array();
	}

	$links = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$slug = trim( (string) ( $row['slug'] ?? '' ) );
		if ( '' === $slug ) {
			continue;
		}
		$host = trim( (string) ( $row['hostname'] ?? '' ) );
		$base = '' !== $host
			? rtrim( $host, '/' )
			: 'https://' . (string) wp_parse_url( $page_url, PHP_URL_HOST );

		$abs = esc_url_raw( $base . '/' . ltrim( $slug, '/' ) );
		if ( '' !== $abs ) {
			$links[ $abs ] = true;
		}
	}
	return array_slice( array_keys( $links ), 0, MAX_FOLLOW );
}

/**
 * Minimal .ics parsing: VEVENT blocks to candidate shape.
 *
 * @return array<int, array<string, string>>
 */
function events_from_ics( string $ics, string $feed_url ): array {
	$out = array();
	// Unfold continuation lines first (RFC 5545).
	$ics = preg_replace( '/\r?\n[ \t]/', '', $ics ) ?? $ics;
	if ( ! preg_match_all( '/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $m ) ) {
		return $out;
	}
	foreach ( $m[1] as $block ) {
		$get = static function ( string $prop ) use ( $block ): string {
			return preg_match( '/^' . $prop . '[^:]*:(.*)$/mi', $block, $mm )
				? trim( str_replace( array( '\\,', '\\n', '\\;' ), array( ',', ' ', ';' ), $mm[1] ) )
				: '';
		};
		$fmt = static function ( string $dt ): string {
			$ts = strtotime( $dt );
			return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
		};
		$out[] = array(
			'title'       => sanitize_text_field( $get( 'SUMMARY' ) ),
			'description' => sanitize_textarea_field( $get( 'DESCRIPTION' ) ),
			'start'       => $fmt( $get( 'DTSTART' ) ),
			'end'         => $fmt( $get( 'DTEND' ) ),
			'venue'       => sanitize_text_field( $get( 'LOCATION' ) ),
			'suburb'      => '',
			'region'      => '',
			'price'       => '',
			'price_high'  => '',
			'currency'    => '',
			'organiser'   => '',
			'url'         => esc_url_raw( $get( 'URL' ) ?: $feed_url ),
			'image'       => '',
			'source_url'  => esc_url_raw( $feed_url ),
		);
	}
	return $out;
}

/**
 * Everything a single watchlist URL yields this run.
 *
 * @return array<int, array<string, string>>
 */
function collect( string $url ): array {
	$body = get( $url );
	if ( null === $body ) {
		return array();
	}

	if ( str_ends_with( strtolower( strtok( $url, '?' ) ?: $url ), '.ics' ) || str_starts_with( ltrim( $body ), 'BEGIN:VCALENDAR' ) ) {
		return events_from_ics( $body, $url );
	}

	$events = events_from_html( $body, $url );

	/*
	 * A listing page with no events of its own: follow a few event links.
	 *
	 * The data island is asked first because it is exact — it lists the
	 * events themselves. Scraping hrefs is the guess, and on a Humanitix
	 * browse page it is a bad one: the matches are the same page under
	 * seven locale prefixes plus a handful of other cities, none of which
	 * carry an Event between them. Twelve fetches, nothing found, and
	 * whether the island was consulted at all came down to which variant
	 * of the page happened to be served.
	 *
	 * Pages without an island fall straight through to the href scan and
	 * behave exactly as they did before.
	 */
	if ( ! $events ) {
		$links = next_data_links( $body, $url );
		if ( ! $links ) {
			$links = event_links( $body, $url );
		}
		foreach ( $links as $link ) {
			$page = get( $link );
			if ( null !== $page ) {
				$events = array_merge( $events, events_from_html( $page, $link ) );
			}
		}
	}
	return next_occurrences( $events );
}

/**
 * One candidate per event, rather than one per occurrence.
 *
 * A recurring event publishes an Event node for every future occurrence.
 * A weekly class runs to thirty-odd on a single page, and eight real
 * Humanitix events came to seventy-seven nodes between them — enough to
 * spend the whole of MAX_CANDIDATES inside one source, so everything after
 * it was skipped and nothing appeared to import at all. Unchecked it would
 * also have put thirty-four copies of the same class in the directory.
 *
 * The soonest occurrence still to come is the one worth having; the rest
 * are the same event again. Keyed on URL and title together: occurrences
 * share both, while a page listing genuinely different events — a festival
 * programme, say — gives its entries different titles even where they all
 * fall back to the page's own URL.
 *
 * An event whose occurrences have all passed keeps its earliest node, so
 * the pipeline's own past-date check still rejects it for the stated
 * reason instead of it disappearing silently here.
 *
 * @param array<int, array<string, string>> $events
 * @return array<int, array<string, string>>
 */
function next_occurrences( array $events ): array {
	$now  = time();
	$best = array();

	foreach ( $events as $e ) {
		$key = ( $e['url'] ?? '' ) . '|' . ( $e['title'] ?? '' );
		$ts  = strtotime( (string) ( $e['start'] ?? '' ) );

		// Undated nodes cannot be compared, so each is kept as its own.
		if ( false === $ts ) {
			$best[ $key . '|' . count( $best ) ] = $e;
			continue;
		}
		if ( ! isset( $best[ $key ] ) ) {
			$best[ $key ] = $e;
			continue;
		}

		$cur       = (int) strtotime( (string) $best[ $key ]['start'] );
		$is_ahead  = $ts >= $now;
		$was_ahead = $cur >= $now;

		// Anything still to come beats anything past; between two of the
		// same kind the earlier one wins.
		if ( ( $is_ahead && ! $was_ahead ) || ( $is_ahead === $was_ahead && $ts < $cur ) ) {
			$best[ $key ] = $e;
		}
	}
	return array_values( $best );
}
