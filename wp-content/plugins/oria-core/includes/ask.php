<?php
/**
 * Ask — a sentence in, listings out.
 *
 * WHAT THIS IS, AND WHAT IT DELIBERATELY IS NOT.
 *
 * It is retrieval. Someone types "I've never done yoga and I'm nervous about
 * going alone", and this turns that into PREFERENCES -- first time, prefers
 * a small room, going by themselves -- shows them what it understood so they
 * can correct it, and hands back places that match, with the reviewers' own
 * words as evidence.
 *
 * It is not an adviser, and it never speaks as "I". writing style/schema.json
 * is unambiguous on the second point: "Institutional 'we', never a
 * personality. No first-person singular, ever." An assistant that says "I'd
 * avoid the large group classes" has crossed from describing rooms into
 * recommending them, and a nervous first-timer acts on that.
 *
 * THE PART THAT MATTERS MOST. A free-text box on a wellness site will be
 * typed into with "I have anxiety", "my back is wrecked", "I think I'm
 * depressed". The model is instructed to extract nothing from that -- no
 * goal, no category, no inference -- and to raise a flag instead, so the
 * page can say plainly that it cannot help with a health concern and point
 * at a GP. A health sentence must never come back as a shopping list.
 *
 * The extraction schema is closed. The model chooses only from values this
 * file defines; anything else it returns is dropped rather than trusted.
 */

declare(strict_types=1);

namespace Oria\Core\Ask;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

const ROUTE  = 'oria/v1';
const MODEL  = 'claude-haiku-4-5-20251001';
const MAX_Q  = 400;
const ASK_A_DAY = 3;   // model readings per address per day

function bootstrap(): void {
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
}

function routes(): void {
	register_rest_route(
		ROUTE,
		'/ask',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\handle',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q'     => array( 'type' => 'string' ),
				'prefs' => array( 'type' => 'object' ),
			),
		)
	);
}

/**
 * Every practice, specialty and service the directory actually uses, as
 * slug => name.
 *
 * This is what someone means when they type "a sauna". The first version of
 * this file had no field for it: the extractor read the word correctly and
 * then had nowhere to put it, so "where is the best place to try a sauna"
 * returned beginner-ranked yoga studios. The schema could hold how much
 * effort someone wanted and how much company, but not what they had come
 * for -- which is the first thing anybody types.
 *
 * hide_empty, so the extractor is never offered a term that would return an
 * empty page.
 *
 * @return array<string, string>
 */
function vocabulary(): array {
	static $vocab = null;
	if ( null !== $vocab ) {
		return $vocab;
	}
	$vocab = array();
	foreach ( array( 'practice', 'specialty', 'service' ) as $tax ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$vocab[ $t->slug ] = wp_specialchars_decode( $t->name, ENT_QUOTES );
		}
	}
	return $vocab;
}

/** The only values the extractor may return. Anything else is discarded. */
function schema(): array {
	$goals = array();
	if ( function_exists( '\Oria\Core\GoodFor\labels' ) ) {
		foreach ( \Oria\Core\GoodFor\labels() as $g ) {
			$goals[] = (string) $g['label'];
		}
	}
	return array(
		'kinds'   => array_keys( vocabulary() ),
		'goals'   => $goals,
		'effort'  => array( 'gentle', 'active', 'any' ),
		'social'  => array( 'quiet', 'people', 'any' ),
		'budget'  => array( 'free', 'mid', 'any' ),
		/* Kilometres from the centre someone is searching, or 0 for no limit.
		   "Near the city" used to be read correctly and then dropped on the
		   floor, because there was nowhere to put it: a search saying "close
		   to the city" came back with places seventeen kilometres out. */
		'max_km'  => array( 0, 2, 5, 10, 25 ),
	);
}

function handle( \WP_REST_Request $req ) {
	/* Corrected chips come back here instead of a sentence. The chips are
	   what the search actually runs on, so once someone has edited one there
	   is nothing left to re-read -- and re-reading would be worse than
	   pointless, because the extractor would overwrite the correction with
	   its own reading of the sentence again. */
	$prefs = $req->get_param( 'prefs' );
	if ( is_array( $prefs ) ) {
		$read       = clamp( $prefs );
		$read['by'] = 'chips';
		return rest_ensure_response(
			array(
				'understood' => $read,
				// Slugs are what the search runs on; these are what a person reads.
				'names'      => array_intersect_key( vocabulary(), array_flip( $read['kinds'] ) ),
				'matches'    => $read['health'] ? array() : retrieve( $read ),
			)
		);
	}

	$q = trim( (string) $req->get_param( 'q' ) );
	if ( '' === $q ) {
		return new \WP_Error( 'empty', 'Ask something first.', array( 'status' => 400 ) );
	}
	$q = mb_substr( $q, 0, MAX_Q );


	/*
	 * Three readings a day, per address.
	 *
	 * Counted here and not around the whole handler, because correcting a
	 * chip posts back to this same route and must stay free: with the count
	 * on every request a visitor would get three clicks and then a locked
	 * page, which would make the editable chips -- the part that keeps this
	 * a search box rather than an oracle -- unusable. Only a sentence that
	 * reaches the model is charged for.
	 *
	 * By address, so it is a cost ceiling and not an identity check. A
	 * shared office looks like one visitor and a phone on mobile data looks
	 * like a new one each time; both are acceptable for a prototype whose
	 * purpose is to stop a public box running up an API bill.
	 */
	/* Anyone who can change the site's settings is testing it, not using it,
	   and three readings does not survive one afternoon of that -- most of
	   the counter's daily allowance went on my own verification runs while
	   this was being built. Editors and members are still counted: the cap
	   exists because the endpoint is public and costs money per call, and
	   that is just as true for a logged-in visitor. */
	if ( current_user_can( 'manage_options' ) ) {
		return interpreted( $q );
	}

	$key  = 'oria_ask_' . gmdate( 'Ymd' ) . '_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$hits = (int) get_transient( $key );
	if ( $hits >= ASK_A_DAY ) {
		return new \WP_Error(
			'ask_limit',
			'That is three questions today — the chips above still work, and the whole directory is at /explore/.',
			array( 'status' => 429 )
		);
	}
	set_transient( $key, $hits + 1, DAY_IN_SECONDS );

	return interpreted( $q );
}

/** Read the sentence and answer it. The one path, so the admin bypass above
    cannot drift away from the metered one below it. */
function interpreted( string $q ) {
	$read = interpret( $q );

	return rest_ensure_response(
		array(
			'understood' => $read,
			'names'      => array_intersect_key( vocabulary(), array_flip( $read['kinds'] ) ),
			'matches'    => $read['health'] ? array() : retrieve( $read ),
		)
	);
}

/* ------------------------------------------------------------ reading -- */

/**
 * Turn a sentence into preferences. Falls back to a keyword read when the
 * key is absent or the call fails, so the page always answers something.
 */
function interpret( string $q ): array {
	$out = defined( 'ORIA_ANTHROPIC_KEY' ) && ORIA_ANTHROPIC_KEY ? ask_model( $q ) : null;
	if ( null === $out ) {
		$out = keywords( $q );
		$out['by'] = 'keywords';
	} else {
		$out['by'] = 'model';
	}
	return $out;
}

function blank(): array {
	return array(
		'kinds'    => array(),
		'goals'    => array(),
		'effort'   => 'any',
		'social'   => 'any',
		'budget'   => 'any',
		'max_km'   => 0,
		'beginner' => false,
		'skip_spirit' => false,
		'health'   => false,
		'note'     => '',
	);
}

function ask_model( string $q ): ?array {
	$s = schema();

	$system = "You convert one sentence from a visitor to a Perth wellness directory into search preferences.\n\n"
		. "Return ONLY a JSON object, no prose, with these keys:\n"
		. "  kinds: array of zero or more slugs naming WHAT they are after, chosen from the list at the end\n"
		. '  goals: array of zero or more of ' . wp_json_encode( $s['goals'] ) . "\n"
		. '  effort: one of ' . wp_json_encode( $s['effort'] ) . "\n"
		. '  social: one of ' . wp_json_encode( $s['social'] ) . "\n"
		. '  budget: one of ' . wp_json_encode( $s['budget'] ) . "\n"
		. '  max_km: one of ' . wp_json_encode( $s['max_km'] )
		. " -- how far they will travel from the centre of town. 0 means they did not say. "
		. "\"Walking distance\" and \"round the corner\" are 2; \"near the city\" and \"close by\" are 5; "
		. "\"not too far\" is 10; "
		. "\"anywhere in Perth\" is 25.\n"
		. "  beginner: true if they say they are new, inexperienced or nervous about starting\n"
		. "  skip_spirit: true only if they say they want to avoid spiritual, religious or new-age content\n"
		. "  health: true if the sentence mentions a symptom, injury, illness, mental-health condition or medication\n"
		. "  note: one short clause, in the visitor's own terms, naming what you took from the sentence\n\n"
		. "RULES, in order of importance:\n"
		. "1. If health is true, return every other field at its default (goals empty, all 'any', beginner false) "
		. "and set note to an empty string. Never map a symptom or condition to a goal, a category or a treatment. "
		. "This directory does not answer health questions and must not appear to.\n"
		. "2. Only use values from the lists above. Invent nothing.\n"
		. "3. Preferences only: how much effort, how much company, what to spend, how experienced, "
		. "whether to avoid the spiritual end. Never infer a person's state of mind or body.\n"
		. "4. Record what they ASKED FOR, never what you think would suit them. "
		. "\"Nervous about walking in on my own\" is beginner=true and social='any' -- they said "
		. "they are going alone, not that they want company. Deciding that a nervous person needs "
		. "a group is advice, and this is a search box. When in doubt the field is 'any'.\n"
		. "5. kinds is the most important field when they name a thing. \"A sauna\", \"massage\", "
		. "\"reformer pilates\" all belong there, and a misspelling still maps to the right slug -- "
		. "\"saua\" is a sauna. Pick every slug that genuinely fits (a plain \"sauna\" is both the "
		. "infrared and the traditional kind); pick none if they named no particular thing. Never "
		. "invent a slug that is not on the list.\n"
		. "6. Ignore \"best\", \"top\" and \"good\". This is a directory, not a ranking, and it does "
		. "not judge which places are better. Extract what they are looking for and drop the "
		. "superlative.\n"
		. "7. If the sentence says nothing useful, return the defaults.\n\n"
		. 'The kinds list: ' . implode( ', ', $s['kinds'] );

	$res = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 20,
			'headers' => array(
				'content-type'      => 'application/json',
				'x-api-key'         => ORIA_ANTHROPIC_KEY,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => MODEL,
					'max_tokens' => 400,
					'system'     => $system,
					'messages'   => array(
						array( 'role' => 'user', 'content' => $q ),
						// Prefilled so the reply starts inside the object.
						array( 'role' => 'assistant', 'content' => '{' ),
					),
				)
			),
		)
	);

	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$text = (string) ( $body['content'][0]['text'] ?? '' );
	$data = json_decode( '{' . $text, true );
	if ( ! is_array( $data ) ) {
		return null;
	}

	return clamp( $data );
}

/** Keep only what the schema allows; everything else is dropped. */
function clamp( array $d ): array {
	$s   = schema();
	$out = blank();

	$out['health'] = ! empty( $d['health'] );
	if ( $out['health'] ) {
		return $out;   // a health sentence yields nothing else, by rule
	}

	$vocab = vocabulary();
	foreach ( (array) ( $d['kinds'] ?? array() ) as $k ) {
		$k = (string) $k;
		if ( isset( $vocab[ $k ] ) && ! in_array( $k, $out['kinds'], true ) ) {
			$out['kinds'][] = $k;
		}
	}

	foreach ( (array) ( $d['goals'] ?? array() ) as $g ) {
		if ( in_array( (string) $g, $s['goals'], true ) ) {
			$out['goals'][] = (string) $g;
		}
	}
	foreach ( array( 'effort', 'social', 'budget' ) as $k ) {
		$v = (string) ( $d[ $k ] ?? 'any' );
		$out[ $k ] = in_array( $v, $s[ $k ], true ) ? $v : 'any';
	}
	$km = (int) ( $d['max_km'] ?? 0 );
	$out['max_km']      = in_array( $km, $s['max_km'], true ) ? $km : 0;
	$out['beginner']    = ! empty( $d['beginner'] );
	$out['skip_spirit'] = ! empty( $d['skip_spirit'] );
	$out['note']        = mb_substr( sanitize_text_field( (string) ( $d['note'] ?? '' ) ), 0, 120 );

	return $out;
}

/** The fallback. Deliberately dim: it is a safety net, not a second brain. */
function keywords( string $q ): array {
	$out = blank();
	$l   = strtolower( $q );   // not mb_: WP polyfills mb_substr/mb_strlen only

	/* Stems, so no closing \\b -- 'anxiet' is followed by 'y' and 'hurt' by
	   's', never a word boundary, so the first version of this line matched
	   nothing at all while looking exactly like it worked: "I have really bad
	   anxiety and my back hurts" came back as six places to book. This is the
	   safety net for when the model is unreachable, and it failing silently is
	   the worst outcome anywhere in this file. */
	if ( preg_match( '/\b(anxiet|depress|injur|pain|sore|hurt|ache|aching|diagnos|'
		. 'medicat|illness|disease|condition|symptom|trauma|insomnia|migraine|therapy for)/i', $l ) ) {
		$out['health'] = true;
		return $out;
	}

	if ( preg_match( '/\b(never|first time|new to|beginner|nervous|complete novice)\b/', $l ) ) { $out['beginner'] = true; }
	if ( preg_match( '/\b(alone|by myself|on my own|quiet|small)\b/', $l ) )                    { $out['social'] = 'quiet'; }
	if ( preg_match( '/\b(meet|people|group|social|community)\b/', $l ) )                       { $out['social'] = 'people'; }
	if ( preg_match( '/\b(gentle|slow|easy|low impact)\b/', $l ) )                              { $out['effort'] = 'gentle'; }
	if ( preg_match( '/\b(hard|intense|sweat|strong|active|fit)\b/', $l ) )                     { $out['effort'] = 'active'; }
	if ( preg_match( '/\b(free|no money|cheap|budget)\b/', $l ) )                               { $out['budget'] = 'free'; }
	if ( preg_match( '/\b(near|close|nearby|walking distance|round the corner|local)\b/', $l ) )  { $out['max_km'] = 5; }
	if ( preg_match( '/\b(not too far|reasonably close|within reason)\b/', $l ) )                { $out['max_km'] = 10; }
	if ( preg_match( '/\b(not spiritual|no spiritual|nothing spiritual|not religious|woo)\b/', $l ) ) { $out['skip_spirit'] = true; }

	foreach ( schema()['goals'] as $g ) {
		if ( false !== strpos( $l, strtolower( $g ) ) ) { $out['goals'][] = $g; }
	}

	/* Both directions: "sauna" should find infrared-sauna, and "cold plunge"
	   should find cold-plunge. No spelling correction here -- that is the
	   model's job, and this net only has to be useful, not clever. */
	foreach ( array_keys( vocabulary() ) as $slug ) {
		$words = str_replace( '-', ' ', $slug );
		if ( false !== strpos( $l, $words ) ) {
			$out['kinds'][] = $slug;
			continue;
		}
		foreach ( explode( ' ', $words ) as $w ) {
			if ( strlen( $w ) > 4 && false !== strpos( $l, $w ) ) {
				$out['kinds'][] = $slug;
				break;
			}
		}
	}
	return $out;
}

/* ---------------------------------------------------------- retrieval -- */

function retrieve( array $p, int $limit = 6 ): array {
	$ids = get_posts(
		array( 'post_type' => 'listing', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' )
	);

	if ( function_exists( '\Oria\Core\Cities\filter_ids' ) && function_exists( '\Oria\Core\Cities\current' ) ) {
		$ids = \Oria\Core\Cities\filter_ids( $ids, \Oria\Core\Cities\current() );
	}

	$rows = array();

	foreach ( $ids as $id ) {
		$id = (int) $id;

		/* All of them, not the top three. for_listing() defaults to a limit
		   of 3 because it feeds a card, and a listing that answers to
		   "Recharge" fifth would silently drop out of a search for it. */
		$goals = array();
		foreach ( \Oria\Core\GoodFor\for_listing( $id, 99 ) as $w ) {
			$goals[] = (string) $w['label'];
		}
		if ( $p['goals'] && ! array_intersect( $p['goals'], $goals ) ) {
			continue;
		}

		/* What they came for. Checked across all three vocabularies because a
		   sauna is a specialty on one listing and a service on the next. */
		$mine = array();
		foreach ( array( 'practice', 'specialty', 'service' ) as $tax ) {
			$terms = get_the_terms( $id, $tax );
			foreach ( ( $terms && ! is_wp_error( $terms ) ) ? $terms : array() as $t ) {
				$mine[ $t->slug ] = true;
			}
		}
		if ( $p['kinds'] && ! array_intersect_key( $mine, array_flip( $p['kinds'] ) ) ) {
			continue;
		}

		$cats = get_the_terms( $id, 'practice' );
		$cats = ( $cats && ! is_wp_error( $cats ) ) ? $cats : array();

		if ( $p['skip_spirit'] && \Oria\Core\Dna\spiritual( $id ) ) {
			continue;
		}

		$band = (string) get_field( 'price_band', $id );
		if ( 'free' === $p['budget'] && 0 !== strcasecmp( $band, 'free' ) ) {
			continue;
		}
		if ( 'mid' === $p['budget'] && ! in_array( strtolower( $band ), array( 'free', '$', '$$' ), true ) ) {
			continue;
		}

		/* The DNA bars, keyed. Note "physical", not "intensity" -- and
		   "beginner", which is the useful one here: beginner_scale() reads
		   the modality's own experience note as well as the audience tag,
		   so it can speak for the 379 listings that carry no tag at all. */
		$bar = array();
		foreach ( \Oria\Core\Dna\bars( $id ) as $b ) {
			$bar[ (string) $b['key'] ] = (int) $b['score'];
		}

		$phys = $bar['physical'] ?? null;
		$soc  = $bar['social'] ?? null;

		if ( 'gentle' === $p['effort'] && null !== $phys && $phys > 3 ) { continue; }
		if ( 'active' === $p['effort'] && null !== $phys && $phys < 3 ) { continue; }
		if ( 'quiet' === $p['social'] && null !== $soc && $soc > 3 )    { continue; }
		if ( 'people' === $p['social'] && null !== $soc && $soc < 3 )   { continue; }

		/* Three is the neutral default the scale falls back to, not a
		   reading of the room. Excluding on it would be wrong, but so is
		   treating it as agreement: "happy to sweat" answered with six
		   meditation studios, all of which scored a shrugging 3 and then won
		   on the beginner bonus. Confirmation earns points; the default
		   earns none, so a genuinely brisk room outranks an unmeasured one. */
		$fit = 0.0;
		if ( 'gentle' === $p['effort'] && null !== $phys && $phys <= 2 ) { $fit += 2.5; }
		if ( 'active' === $p['effort'] && null !== $phys && $phys >= 4 ) { $fit += 2.5; }
		if ( 'quiet' === $p['social'] && null !== $soc && $soc <= 2 )    { $fit += 2.5; }
		if ( 'people' === $p['social'] && null !== $soc && $soc >= 4 )   { $fit += 2.5; }

		$tagged   = has_term( 'beginners', 'audience', $id );
		$easyStart = $tagged || ( ( $bar['beginner'] ?? 0 ) >= 4 );

		/* A preference, not a filter. Asked as a hard rule, "I've never done
		   this" would answer with the 25 tagged listings and nothing else --
		   the commonest sentence this box will ever see, answered worst. */
		$score = $fit;

		/* Nudges, not the answer, when nobody asked to be eased in. At 0.6
		   this outranked the entire distance range and put a yoga spa six
		   kilometres out above a place called Alchemy Saunas at one, for a
		   search that said "sauna near perth". */
		if ( $easyStart ) { $score += $p['beginner'] ? 3.0 : 0.2; }
		if ( $tagged )    { $score += $p['beginner'] ? 1.5 : 0.1; }

		/* Over 10 rather than 40: across a city twenty kilometres wide the
		   old divisor moved the score by half a point end to end, which is
		   less than a tiebreaker. "Near me" is most of why anyone searches a
		   directory by city, so it has to be able to outweigh a nudge. */
		$km = \Oria\Core\Geo\km_from_cbd( $id );

		/* A stated limit is a filter, not a nudge. Someone who says "close to
		   the city" and is shown a studio seventeen kilometres out has been
		   ignored, however good the match is on every other axis. Listings
		   with no coordinates are kept: about all of them have some, but
		   dropping an unplaceable listing would hide it for a reason the
		   visitor never asked about. */
		if ( $p['max_km'] > 0 && null !== $km && $km > $p['max_km'] ) {
			continue;
		}

		if ( null !== $km ) { $score -= (float) $km / 10; }

		$quote = $p['beginner'] ? first_timer_quote( $id ) : null;
		if ( $quote ) { $score += 2.0; }

		$suburb = '';
		$areas  = get_the_terms( $id, 'area' );
		foreach ( ( $areas && ! is_wp_error( $areas ) ) ? $areas : array() as $a ) {
			if ( $a->parent ) {
				$suburb = wp_specialchars_decode( $a->name, ENT_QUOTES );
				break;
			}
		}

		$why = reasons( $p, $id, $bar, $tagged, $band, $km, $goals, $mine );

		/* Nominatim's, never Google's: the Places terms forbid storing their
		   coordinates, and geo.php geocodes through OpenStreetMap for exactly
		   that reason. Attribution rides with the map, as it does on
		   /wellness-map/. */
		$pos = function_exists( '\Oria\Core\Geo\position' ) ? \Oria\Core\Geo\position( $id ) : null;

		$rows[] = array(
			'id'       => $id,
			'reasons'  => $why['chips'],
			'fit'      => strength( $p, $why['met'] ),
			'title'    => wp_specialchars_decode( get_the_title( $id ), ENT_QUOTES ),
			'url'      => get_permalink( $id ),
			'cat'      => $cats ? wp_specialchars_decode( $cats[0]->name, ENT_QUOTES ) : '',
			'suburb'   => $suburb,
			'band'     => '' === $band ? '' : ( 0 === strcasecmp( $band, 'free' ) ? 'free' : $band ),
			/* Geo\label() names the centre it measured from -- "3.1 km from
			   the CBD", not a bare "3.1 km", which every reader takes to mean
			   from where they are standing. Nobody has told us where that is. */
			'where'    => function_exists( '\Oria\Core\Geo\label' ) ? \Oria\Core\Geo\label( $id ) : '',
			/* For the save button, which keys the device shortlist by post id
			   exactly as every other card on the site does. */
			'lat'      => $pos ? round( (float) $pos['lat'], 5 ) : null,
			'lng'      => $pos ? round( (float) $pos['lng'], 5 ) : null,
			'km'       => null === $km ? null : round( (float) $km, 1 ),
			'beginner' => $tagged,
			'quote'    => $quote,
			'_s'       => $score,
		);
	}

	usort( $rows, static fn( array $a, array $b ): int => $b['_s'] <=> $a['_s'] );
	$rows = array_slice( $rows, 0, $limit );

	/*
	 * Pictures last, for the six that survived.
	 *
	 * Never inside the loop above: that walks all four hundred listings, and
	 * card_photo() carries a per-request budget of two live Places fetches.
	 * Spending it on listings that are about to be discarded would leave the
	 * six being shown with no photo at all.
	 */
	foreach ( $rows as &$r ) {
		unset( $r['_s'] );
		$r += picture( $r['id'] );
	}
	unset( $r );

	return $rows;
}

/**
 * Why this place came back — as facts, never as benefits.
 *
 * Every chip names something the directory knows: what the place offers, how
 * busy the room is, what it costs, how far out it sits. None of them says a
 * session helps, eases or improves anything, which is the same line the
 * comparison attributes and the week planner hold.
 *
 * Only reasons that answer what was actually ASKED are listed first, because
 * a chip that says "Quiet" is interesting when someone asked for quiet and
 * noise when they did not.
 *
 * @return array{chips: string[], met: int}
 */
function reasons( array $p, int $id, array $bar, bool $tagged, string $band, ?float $km, array $goals, array $mine ): array {
	$out   = array();
	$vocab = vocabulary();

	/* One request, however many slugs it became. "A sauna" expands to both
	   the infrared and the traditional term, and no listing carries both, so
	   counting them separately capped every sauna search at half marks. */
	$kind_hit = false;
	foreach ( $p['kinds'] as $slug ) {
		if ( isset( $mine[ $slug ], $vocab[ $slug ] ) ) {
			$out[]    = $vocab[ $slug ];
			$kind_hit = true;
		}
	}
	foreach ( $p['goals'] as $g ) {
		if ( in_array( $g, $goals, true ) ) {
			$out[] = $g;
		}
	}

	$phys = $bar['physical'] ?? null;
	$soc  = $bar['social'] ?? null;

	if ( 'gentle' === $p['effort'] && null !== $phys && $phys <= 2 ) { $out[] = __( 'Low intensity', 'oria' ); }
	if ( 'active' === $p['effort'] && null !== $phys && $phys >= 4 ) { $out[] = __( 'Physical', 'oria' ); }
	if ( 'quiet' === $p['social'] && null !== $soc && $soc <= 2 )    { $out[] = __( 'Quiet', 'oria' ); }
	if ( 'people' === $p['social'] && null !== $soc && $soc >= 4 )   { $out[] = __( 'Social', 'oria' ); }
	if ( $p['beginner'] && $tagged )                                 { $out[] = __( 'Beginner friendly', 'oria' ); }
	if ( 'free' === $p['budget'] && 0 === strcasecmp( $band, 'free' ) ) { $out[] = __( 'Free', 'oria' ); }
	if ( 'mid' === $p['budget'] && in_array( $band, array( '$', '$$' ), true ) ) { $out[] = __( 'Modest', 'oria' ); }
	if ( $p['max_km'] > 0 && null !== $km && $km <= $p['max_km'] ) {
		/* translators: %d: kilometres. */
		$out[] = sprintf( __( 'Within %d km', 'oria' ), $p['max_km'] );
	}

	/* Everything above answers something that was asked for. Counted here,
	   before the extras are appended: the bonus chips below would otherwise
	   push every result to "Strong match", which is what the first version
	   of this did -- a badge every card wears is not a signal. */
	$met = count( array_unique( $out ) );
	if ( $kind_hit ) {
		// however many kind chips were added, the request behind them was one
		$met -= max( 0, count( array_intersect_key( $vocab, array_flip( $p['kinds'] ) ) ) - 1 );
		$met  = max( 1, $met );
	}

	// Unasked-for, but worth saying once the asked-for ones are in.
	if ( count( $out ) < 4 ) {
		if ( ! $p['beginner'] && $tagged )                       { $out[] = __( 'Beginner friendly', 'oria' ); }
		if ( ! $p['max_km'] && null !== $km && $km < 3 )         { $out[] = __( 'Close in', 'oria' ); }
		/* Only when nothing above already said it. Asking for hands-on care
		   produced "Hands-on care / Quiet / Close in / Hands-on" on every
		   card -- the bonus chip repeating the goal that earned the match. */
		$said_touch = false;
		foreach ( $out as $said ) {
			if ( false !== stripos( $said, 'hands-on' ) || false !== stripos( $said, 'massage' ) ) {
				$said_touch = true;
			}
		}
		if ( ! $said_touch && ( $bar['handson'] ?? 0 ) >= 4 )    { $out[] = __( 'Hands-on', 'oria' ); }
	}

	return array(
		'chips' => array_values( array_slice( array_unique( $out ), 0, 5 ) ),
		'met'   => $met,
	);
}

/**
 * How well this answers what was asked — in words, deliberately.
 *
 * The brief floated "92% fit". The number would be made up: the ranking is a
 * heuristic sum of a few attribute checks and a distance penalty, and
 * printing two significant figures on it would claim an accuracy that does
 * not exist. Three plain bands say as much as the score honestly supports.
 */
function strength( array $p, int $matched ): string {
	$asked = ( $p['kinds'] ? 1 : 0 ) + count( $p['goals'] )
		+ ( 'any' !== $p['effort'] ? 1 : 0 )
		+ ( 'any' !== $p['social'] ? 1 : 0 )
		+ ( 'any' !== $p['budget'] ? 1 : 0 )
		+ ( $p['max_km'] > 0 ? 1 : 0 )
		+ ( $p['beginner'] ? 1 : 0 );

	if ( $asked < 1 ) {
		return '';   // nothing was asked for, so nothing was matched
	}

	$ratio = $matched / $asked;
	if ( $ratio >= 0.99 ) { return __( 'Strong match', 'oria' ); }
	if ( $ratio >= 0.5 )  { return __( 'Good fit', 'oria' ); }
	return __( 'Worth a look', 'oria' );
}

/**
 * A listing's picture, and the credit it owes.
 *
 * listing_image() is the site's existing chain -- the listing's own featured
 * image first, then its cached Google Places photo, then a generated
 * placeholder -- and reusing it is the point: a second chain here would
 * drift from the one every other card on the site uses.
 *
 * The credit is only filled in when the photo actually came from Google.
 * places.php sets the rule at the top of the file: "photos must be shown
 * with their author attributions". A listing with its own uploaded photo
 * owes nobody a line, and printing one anyway would credit a stranger for
 * a business's own picture.
 *
 * @return array{img: string, scene: string, credit: string}
 */
function picture( int $post_id ): array {
	$out = array( 'img' => '', 'scene' => '', 'credit' => '' );

	if ( function_exists( '\Oria\Theme\listing_scene' ) ) {
		$out['scene'] = (string) \Oria\Theme\listing_scene( $post_id );
	}
	if ( ! function_exists( '\Oria\Theme\listing_image' ) ) {
		return $out;
	}

	$out['img'] = (string) \Oria\Theme\listing_image( $post_id );

	// Its own image needs no credit; only a Google photo does.
	if ( get_the_post_thumbnail_url( $post_id ) ) {
		return $out;
	}
	$cache = function_exists( '\Oria\Core\Places\data_for' )
		? \Oria\Core\Places\data_for( $post_id, false )
		: null;
	$name = (string) ( $cache['attributions'][0]['name'] ?? '' );
	if ( '' !== $name && $out['img'] !== $out['scene'] ) {
		$out['credit'] = $name;
	}

	return $out;
}

/**
 * A reviewer's own sentence about arriving new, or null.
 *
 * Quoted, never summarised. "Reviews mention welcoming instructors" is the
 * site making a claim about a place; "one reviewer wrote: ..." is the
 * reviewer making it, with their name on it, which is both more useful and
 * more honest. Cached Google data only -- no live fetch to answer a search.
 */
function first_timer_quote( int $id ): ?array {
	if ( ! function_exists( '\Oria\Core\Places\data_for' ) ) {
		return null;
	}
	$cache = \Oria\Core\Places\data_for( $id, false );
	if ( ! $cache ) {
		return null;
	}

	/* "never done" on its own pulled in a review praising the variety of a
	   class -- "at least one move that I have never done in my life" -- which
	   is a true sentence and useless evidence. The phrases here have to be
	   about arriving somewhere new, not merely contain the words. */
	$pattern = '/\b(first (?:time|class|visit|session|day)|never (?:been|tried|done (?:yoga|pilates|this))|'
		. 'complete (?:beginner|novice)|as a beginner|new (?:here|to (?:yoga|pilates|this))|'
		. 'no experience|welcom\w+|made me feel (?:at ease|welcome|comfortable)|put me at ease)\b/i';

	foreach ( (array) ( $cache['reviews'] ?? array() ) as $rv ) {
		$text = trim( (string) ( $rv['text'] ?? '' ) );
		if ( '' === $text || ! preg_match( $pattern, $text ) ) {
			continue;
		}
		// The sentence that matched, not the whole review.
		foreach ( preg_split( '/(?<=[.!?])\s+/', $text ) as $sentence ) {
			if ( preg_match( $pattern, $sentence ) && mb_strlen( $sentence ) < 220 ) {
				return array(
					'text'   => trim( $sentence ),
					'author' => sanitize_text_field( (string) ( $rv['author'] ?? '' ) ),
				);
			}
		}
	}
	return null;
}
