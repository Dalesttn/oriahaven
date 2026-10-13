<?php
/**
 * Facility access: what it takes, and costs, to use one facility at a venue.
 *
 * A listing's price_from and duration_min are one number each for the whole
 * venue. A bathhouse, a hotel spa and a leisure centre all "have a steam
 * room", but one sells a 90-minute bathhouse session, one opens it only with
 * a treatment of an hour or more, and one includes it in a pool entry. The
 * steam-room page needs those answers side by side, with where each came
 * from and when it was checked -- so they are stored here, per facility,
 * rather than squeezed into the venue-wide fields or written into copy.
 *
 * One row per facility (steam room today; the select allows more). Each row
 * holds the venue facts for that facility and a nested list of offers. An
 * unknown price is an empty field, never 0. Editors correct these in the
 * listing's "Facility access" box; the page, the profile and the tests all
 * read them through rows() / facility() below.
 *
 * Data: wp oria facility-access (tools/facility-access-import.php) applies
 * the researched values with dry-run and apply modes.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\FacilityAccess;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FIELD = 'facility_access';

const FACILITIES = array(
	'steam-room'  => 'Steam room',
	'sauna'       => 'Sauna',
	'cold-plunge' => 'Cold plunge',
);

const STATUSES = array(
	'confirmed'               => 'Confirmed on an official source',
	'temporarily_unavailable' => 'Temporarily unavailable',
	'conflicting'             => 'Sources disagree',
	'not_found'               => 'Not found on official sources',
);

const SETTINGS = array(
	'unknown'          => 'Not stated',
	'shared'           => 'Shared',
	'private_booking'  => 'Can be booked privately',
	'gender_separated' => 'Separate men\'s and women\'s areas',
);

const YES_NO = array(
	'unknown' => 'Not stated',
	'yes'     => 'Yes',
	'no'      => 'No',
);

const BOOKING = array(
	'unknown'     => 'Not stated',
	'required'    => 'Booking required',
	'recommended' => 'Booking recommended',
	'walk_in'     => 'Walk in',
);

const KINDS = array(
	'casual_visit'      => 'Casual entry',
	'bathhouse_session' => 'Bathhouse session',
	'steam_session'     => 'Steam session',
	'treatment_bundle'  => 'With a treatment',
	'membership'        => 'Membership',
	'intro_offer'       => 'Introductory offer',
	'concession'        => 'Concession',
	'couple_or_group'   => 'Couple or group',
	'contrast_session'  => 'Contrast session',
	'private_session'   => 'Private session',
	'pack'              => 'Pack of visits',
);

function bootstrap(): void {
	add_action( 'acf/init', __NAMESPACE__ . '\register_fields' );
	// rows() remembers each listing for the request; a write forgets it, so
	// an editor's save, the import and the tests all read what was written.
	foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\forget', 10, 3 );
	}
}

/** Drop the remembered rows for a listing when its facility meta changes. */
function forget( $meta_ids, $post_id, $meta_key ): void {
	if ( is_string( $meta_key ) && 0 === strpos( ltrim( $meta_key, '_' ), FIELD ) ) {
		unset( $GLOBALS['oria_facility_access_memo'][ (int) $post_id ] );
	}
}

/* ------------------------------------------------------------------ fields */

function choice_field( string $key, string $name, string $label, array $choices, string $width = '25', string $default = '' ): array {
	return array(
		'key'           => $key,
		'name'          => $name,
		'label'         => $label,
		'type'          => 'select',
		'choices'       => $choices,
		'default_value' => '' !== $default ? $default : (string) array_key_first( $choices ),
		'wrapper'       => array( 'width' => $width ),
	);
}

function text_field( string $key, string $name, string $label, string $width = '50', string $type = 'text', string $help = '' ): array {
	return array(
		'key'          => $key,
		'name'         => $name,
		'label'        => $label,
		'type'         => $type,
		'instructions' => $help,
		'wrapper'      => array( 'width' => $width ),
	) + ( 'textarea' === $type ? array( 'rows' => 3, 'new_lines' => '' ) : array() );
}

function register_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$k = 'field_oria_fa_';
	acf_add_local_field_group(
		array(
			'key'        => 'group_oria_facility_access',
			'title'      => 'Facility access (steam room and similar)',
			'position'   => 'normal',
			'menu_order' => 30,
			'location'   => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => PostTypes\LISTING ) ) ),
			'fields'     => array(
				array(
					'key'          => $k . 'rows',
					'name'         => FIELD,
					'label'        => 'Facilities',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Add facility',
					'instructions' => 'Only what the venue\'s own website, menu or booking page states, for this branch. Leave a price empty when it is not published -- never 0. Record where each fact came from and the day you checked it.',
					'sub_fields'   => array(
						choice_field( $k . 'facility', 'facility', 'Facility', FACILITIES ),
						choice_field( $k . 'status', 'status', 'Status', STATUSES ),
						choice_field( $k . 'setting', 'setting', 'Setting', SETTINGS ),
						choice_field( $k . 'casual', 'casual', 'Casual visit possible', YES_NO ),
						text_field( $k . 'condition', 'condition', 'Entry condition', '50', 'text', 'e.g. "With a treatment of 60 minutes or longer". Blank when casual entry is open to anyone.' ),
						choice_field( $k . 'booking', 'booking', 'Booking', BOOKING ),
						text_field( $k . 'includes', 'includes', 'Included with the main offer', '50', 'textarea', 'One facility per line. Paid add-ons do not belong here.' ),
						text_field( $k . 'min_age', 'min_age', 'Minimum age', '15', 'number' ),
						text_field( $k . 'swimwear', 'swimwear', 'Swimwear', '35' ),
						text_field( $k . 'towels', 'towels', 'Towels', '25' ),
						text_field( $k . 'essentials', 'essentials', 'Other visit essentials', '50', 'textarea', 'One per line: lockers, showers, parking -- only what is published.' ),
						text_field( $k . 'quiet', 'quiet', 'Quiet session', '50', 'text', 'Only a named, published silent or quiet session, with its schedule.' ),
						text_field( $k . 'accessibility', 'accessibility', 'Accessibility (published)', '50' ),
						text_field( $k . 'accessibility_url', 'accessibility_url', 'Accessibility page', '50', 'url' ),
						text_field( $k . 'book_url', 'book_url', 'Check sessions / book (this branch)', '50', 'url' ),
						text_field( $k . 'rules_url', 'rules_url', 'Rules or status page', '50', 'url' ),
						text_field( $k . 'notice', 'notice', 'Temporary notice', '50', 'text', 'A closure or maintenance notice, kept apart from permanent facts.' ),
						text_field( $k . 'notice_until', 'notice_until', 'Notice shown until', '25', 'date_picker' ),
						array(
							'key'          => $k . 'offers',
							'name'         => 'offers',
							'label'        => 'Offers that include this facility',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => 'Add offer',
							'sub_fields'   => array(
								text_field( $k . 'o_product', 'product', 'Product (their name)', '22' ),
								choice_field( $k . 'o_kind', 'kind', 'Kind', KINDS, '14' ),
								text_field( $k . 'o_price', 'price', 'Price AUD (empty = unknown)', '9', 'number' ),
								text_field( $k . 'o_basis', 'basis', 'Basis', '12' ),
								text_field( $k . 'o_people', 'people', 'People', '6', 'number' ),
								text_field( $k . 'o_duration', 'duration', 'Access (min)', '8', 'number' ),
								text_field( $k . 'o_conditions', 'conditions', 'Conditions', '17' ),
								array(
									'key'     => $k . 'o_main',
									'name'    => 'main',
									'label'   => 'Standard adult single visit',
									'type'    => 'true_false',
									'ui'      => 1,
									'wrapper' => array( 'width' => '12' ),
								),
							),
						),
						text_field( $k . 'sources', 'sources', 'Sources', '50', 'textarea', 'One URL per line.' ),
						text_field( $k . 'checked', 'checked', 'Last verified', '20', 'date_picker' ),
						text_field( $k . 'evidence', 'evidence', 'Evidence note', '30', 'textarea' ),
					),
				),
			),
		)
	);
}

/* ------------------------------------------------------------------ reading */

/**
 * Raw repeater meta, read directly so it answers the same in a template, a
 * REST request or WP-CLI (ACF's by-name lookup does not, off-admin).
 */
function meta( int $post_id, string $key ): string {
	return trim( (string) get_post_meta( $post_id, $key, true ) );
}

/** "one\ntwo" -> ['one','two'] */
function lines( string $text ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ?: array() ), 'strlen' ) );
}

/** A date field (Ymd or Y-m-d) as Y-m-d, or ''. */
function ymd( string $v ): string {
	$v = preg_replace( '/\D/', '', $v );
	return 8 === strlen( (string) $v ) ? substr( $v, 0, 4 ) . '-' . substr( $v, 4, 2 ) . '-' . substr( $v, 6, 2 ) : '';
}

/**
 * Every facility row for a listing.
 *
 * @return list<array<string, mixed>>
 */
function rows( int $post_id ): array {
	$memo = &$GLOBALS['oria_facility_access_memo'];
	if ( isset( $memo[ $post_id ] ) ) {
		return $memo[ $post_id ];
	}
	$out = array();
	$n   = (int) get_post_meta( $post_id, FIELD, true );
	for ( $i = 0; $i < $n; $i++ ) {
		$p   = FIELD . '_' . $i . '_';
		$row = array( 'index' => $i );
		foreach ( array( 'facility', 'status', 'setting', 'casual', 'condition', 'booking', 'swimwear', 'towels', 'quiet', 'accessibility', 'accessibility_url', 'book_url', 'rules_url', 'notice', 'evidence' ) as $f ) {
			$row[ $f ] = meta( $post_id, $p . $f );
		}
		$row['min_age']      = '' === meta( $post_id, $p . 'min_age' ) ? null : (int) meta( $post_id, $p . 'min_age' );
		$row['includes']     = lines( meta( $post_id, $p . 'includes' ) );
		$row['essentials']   = lines( meta( $post_id, $p . 'essentials' ) );
		$row['sources']      = lines( meta( $post_id, $p . 'sources' ) );
		$row['checked']      = ymd( meta( $post_id, $p . 'checked' ) );
		$row['notice_until'] = ymd( meta( $post_id, $p . 'notice_until' ) );
		$row['offers']       = array();
		$m = (int) get_post_meta( $post_id, $p . 'offers', true );
		for ( $j = 0; $j < $m; $j++ ) {
			$q     = $p . 'offers_' . $j . '_';
			$price = meta( $post_id, $q . 'price' );
			$row['offers'][] = array(
				'product'    => meta( $post_id, $q . 'product' ),
				'kind'       => meta( $post_id, $q . 'kind' ),
				'price'      => is_numeric( $price ) ? (float) $price : null,
				'basis'      => meta( $post_id, $q . 'basis' ),
				'people'     => max( 1, (int) meta( $post_id, $q . 'people' ) ),
				'duration'   => (int) meta( $post_id, $q . 'duration' ) ?: null,
				'conditions' => meta( $post_id, $q . 'conditions' ),
				'main'       => '1' === meta( $post_id, $q . 'main' ),
			);
		}
		$out[] = $row;
	}
	return $memo[ $post_id ] = $out;
}

/** The row for one facility, or null. */
function facility( int $post_id, string $facility ): ?array {
	foreach ( rows( $post_id ) as $row ) {
		if ( $row['facility'] === $facility ) {
			return $row;
		}
	}
	return null;
}

/** Is a temporary notice still current? (No end date: shown until removed.) */
function notice_live( array $row ): bool {
	if ( '' === $row['notice'] ) {
		return false;
	}
	return '' === $row['notice_until'] || $row['notice_until'] >= wp_date( 'Y-m-d' );
}

/**
 * The offer the page compares on: the one marked as the standard adult
 * single visit; else the first offer that is not a membership. Null when
 * there is none -- a membership is never shown as an entry price.
 */
function main_offer( array $row ): ?array {
	foreach ( $row['offers'] as $o ) {
		if ( $o['main'] ) {
			return $o;
		}
	}
	foreach ( $row['offers'] as $o ) {
		if ( 'membership' !== $o['kind'] ) {
			return $o;
		}
	}
	return null;
}

function money( float $v ): string {
	return '$' . number_format_i18n( $v, fmod( $v, 1.0 ) ? 2 : 0 );
}

/**
 * "$89 bathhouse session, per adult" / "Check current price".
 * The product and basis always travel with the number.
 */
function price_label( ?array $offer ): string {
	// Access that comes with a treatment is not free entry: say what it takes.
	if ( $offer && null === $offer['price'] && 'treatment_bundle' === $offer['kind'] ) {
		return __( 'Included with a treatment', 'oria' );
	}
	if ( ! $offer || null === $offer['price'] ) {
		return __( 'Check current price', 'oria' );
	}
	$bits = array( money( (float) $offer['price'] ) );
	if ( '' !== $offer['basis'] ) {
		$bits[] = $offer['basis'];
	}
	return implode( ' ', $bits );
}

/** "90 min access" or ''. */
function duration_label( ?array $offer ): string {
	/* translators: %d: minutes of facility access */
	return ( $offer && $offer['duration'] ) ? sprintf( __( '%d min access', 'oria' ), (int) $offer['duration'] ) : '';
}

/** Entry in words: "Casual entry, 18+, shared" -- only what is known. */
function access_line( array $row ): string {
	$bits = array();
	if ( 'yes' === $row['casual'] ) {
		$bits[] = __( 'Casual entry', 'oria' );
	} elseif ( 'no' === $row['casual'] && '' !== $row['condition'] ) {
		$bits[] = $row['condition'];
	} elseif ( 'no' === $row['casual'] ) {
		$bits[] = __( 'No casual entry', 'oria' );
	} elseif ( '' !== $row['condition'] ) {
		$bits[] = $row['condition'];
	}
	if ( null !== $row['min_age'] ) {
		$bits[] = $row['min_age'] . '+';
	}
	if ( 'unknown' !== $row['setting'] && isset( SETTINGS[ $row['setting'] ] ) && '' !== $row['setting'] ) {
		$bits[] = strtolower( SETTINGS[ $row['setting'] ] );
	}
	$line = implode( ', ', $bits );
	return '' === $line ? '' : ucfirst( $line );
}

/** "28 September 2026", or ''. */
function checked_label( array $row ): string {
	return '' !== $row['checked'] ? wp_date( 'j F Y', (int) strtotime( $row['checked'] . ' 12:00:00' ) ) : '';
}

/*
 * Freshness policy. A price checked more than STALE_DAYS ago is still shown,
 * with its checked date, but it no longer counts for a budget filter, a
 * price sort or an "under $50" shortcut, and tools/facility-stale.php lists
 * it for re-checking. Four months is about how long venues hold a casual
 * price; an undated row is stale from the start.
 */
const STALE_DAYS = 120;

/*
 * Offers that are one visit for one person, so their prices compare like
 * for like. A pack, a membership, a first-visit deal or a couple's price is
 * never compared with a single casual entry.
 */
const PER_VISIT = array( 'casual_visit', 'bathhouse_session', 'steam_session', 'contrast_session', 'private_session' );

function is_stale( array $row ): bool {
	$ts = '' !== (string) $row['checked'] ? strtotime( $row['checked'] . ' 12:00:00' ) : false;
	return ! $ts || ( (int) current_time( 'timestamp' ) - $ts ) > STALE_DAYS * DAY_IN_SECONDS;
}

/** True when this offer is one visit for one person at a published price. */
function is_per_visit( ?array $offer ): bool {
	return null !== $offer
		&& null !== $offer['price']
		&& (float) $offer['price'] > 0
		&& in_array( (string) $offer['kind'], PER_VISIT, true )
		&& (int) $offer['people'] <= 1;
}

/** Does this row's main offer include a cold plunge or ice bath? */
function has_cold( array $row ): bool {
	foreach ( $row['includes'] as $f ) {
		if ( preg_match( '/cold plunge|ice bath|plunge pool|cold pool/i', $f ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The compact, public summary one card or table row needs, for a listing and
 * a facility. Null when the listing has no confirmed row for it.
 *
 * @return array<string, mixed>|null
 */
function summary( int $post_id, string $facility ): ?array {
	$row = facility( $post_id, $facility );
	if ( ! $row || ! in_array( $row['status'], array( 'confirmed', 'temporarily_unavailable' ), true ) ) {
		return null;
	}
	$main = main_offer( $row );
	// An offer's conditions without what the access line already says (age,
	// swimwear, all-gender), so "16+" is not printed twice on one card.
	$cond = $main ? implode(
		'; ',
		array_filter(
			array_map( 'trim', explode( ';', (string) $main['conditions'] ) ),
			static fn( string $c ): bool => '' !== $c && ! preg_match( '/^(\d+\s*\+|swimwear required|all[- ]gender)$/i', $c )
		)
	) : '';
	$stale     = is_stale( $row );
	$per_visit = is_per_visit( $main );
	return array(
		'price'      => $main ? $main['price'] : null,
		// The figure a budget filter, a price sort or a shortcut may use:
		// one visit, one person, checked within STALE_DAYS. Null otherwise,
		// so unknown, stale and package prices never pass as cheap.
		'compare'    => $per_visit && ! $stale ? (float) $main['price'] : null,
		'per_visit'  => $per_visit,
		'stale'      => $stale,
		'price_text' => price_label( $main ),
		'product'    => $main ? $main['product'] : '',
		'kind'       => $main ? ( KINDS[ $main['kind'] ] ?? '' ) : '',
		'duration'   => duration_label( $main ),
		'conditions' => $cond,
		'access'     => access_line( $row ),
		'casual'     => $row['casual'],
		'setting'    => $row['setting'],
		'includes'   => $row['includes'],
		'essentials' => array_values( array_filter( array_merge( array( '' !== $row['towels'] ? sprintf( __( 'Towels: %s', 'oria' ), $row['towels'] ) : '', '' !== $row['swimwear'] ? sprintf( __( 'Swimwear: %s', 'oria' ), $row['swimwear'] ) : '' ), $row['essentials'] ) ) ),
		'quiet'      => $row['quiet'],
		'cold'       => has_cold( $row ),
		'notice'     => notice_live( $row ) ? $row['notice'] : '',
		'unavailable' => 'temporarily_unavailable' === $row['status'],
		'book_url'   => $row['book_url'],
		'source'     => $row['sources'][0] ?? '',
		'checked'    => checked_label( $row ),
		'others'     => array_values( array_filter( $row['offers'], static fn( array $o ): bool => $o !== $main ) ),
	);
}

/* ------------------------------------------------------- listing profile */

/** An offer's identity for de-duplication: same name, price and party size. */
function offer_key( array $o ): string {
	return strtolower( trim( (string) $o['product'] ) ) . '|' . ( null === $o['price'] ? '' : (string) (float) $o['price'] ) . '|' . (int) $o['people'];
}

/** "two" for 2, up to ten; digits after that. */
function people_word( int $n ): string {
	$w = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten' );
	return $w[ $n ] ?? (string) $n;
}

/**
 * What an offer's price is for, in words: "per person", "total for two
 * people", "5 sessions". A price covering more than one person always says
 * so as a total, whatever the editor typed as the basis.
 */
function basis_label( array $o ): string {
	if ( (int) $o['people'] > 1 ) {
		/* translators: %s: number of people in words, e.g. two */
		return sprintf( __( 'total for %s people', 'oria' ), people_word( (int) $o['people'] ) );
	}
	return (string) $o['basis'];
}

/**
 * An offer's conditions as separate facts, minus what is said elsewhere on
 * the same card: the age ("18+") becomes "Ages 18+", swimwear goes to the
 * practical note, and a "90-minute access to…" line repeats the duration and
 * the inclusions list.
 *
 * @return array{facts: list<string>, swimwear: bool}
 */
function condition_facts( string $conditions, ?int $duration ): array {
	$facts = array();
	$swim  = false;
	foreach ( array_map( 'trim', explode( ';', $conditions ) ) as $c ) {
		if ( '' === $c ) {
			continue;
		}
		if ( preg_match( '/^swimwear required$/i', $c ) ) {
			$swim = true;
			continue;
		}
		if ( preg_match( '/^(\d+)\s*\+$/', $c, $m ) ) {
			/* translators: %d: minimum age */
			$facts[] = sprintf( __( 'Ages %d+', 'oria' ), (int) $m[1] );
			continue;
		}
		if ( $duration && preg_match( '/^' . $duration . '[- ]?min(ute)?s?\b.*\baccess\b/i', $c ) ) {
			continue;
		}
		$facts[] = ucfirst( $c );
	}
	return array( 'facts' => array_values( array_unique( $facts ) ), 'swimwear' => $swim );
}

/** Kinds shown as plans (bought ahead, used over time) rather than visits. */
const PLAN_KINDS = array( 'pack', 'membership' );

/**
 * Everything a listing's "Sessions & prices" section shows, merged across
 * its facility rows.
 *
 * A bathhouse is one visit whichever facility it was researched under:
 * Ember Bath House carries a steam-room row and a sauna row, both selling
 * the same $89 Bath House Session, and the profile used to print it twice
 * under two headings. Rows whose main offer is the same product at the same
 * price become one card (the most recently checked row speaks for it); every
 * other offer appears once, however many rows list it. Nothing is deleted
 * from the records -- this only decides what is shown.
 *
 * @return array{anchor: string, mains: list<array>, visits: list<array>, plans: list<array>}
 */
function profile( int $post_id ): array {
	$rows = array_values(
		array_filter(
			rows( $post_id ),
			static fn( array $r ): bool => in_array( $r['status'], array( 'confirmed', 'temporarily_unavailable' ), true )
		)
	);
	if ( ! $rows ) {
		return array( 'anchor' => '', 'mains' => array(), 'visits' => array(), 'plans' => array() );
	}
	$anchor = 'facility-' . $rows[0]['facility'];

	// Most recently checked first, so the freshest record wins every tie.
	$sorted = $rows;
	usort( $sorted, static fn( array $a, array $b ): int => strcmp( (string) $b['checked'], (string) $a['checked'] ) );

	$groups = array();
	foreach ( $sorted as $row ) {
		$main = main_offer( $row );
		$key  = $main ? offer_key( $main ) : 'row-' . $row['facility'];
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array( 'row' => $row, 'main' => $main, 'facilities' => array() );
		}
		$groups[ $key ]['facilities'][] = FACILITIES[ $row['facility'] ] ?? $row['facility'];
	}

	$seen = array_fill_keys( array_keys( $groups ), true );
	$alts = array();
	foreach ( $sorted as $row ) {
		foreach ( $row['offers'] as $o ) {
			$k = offer_key( $o );
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = true;
			$alts[]     = array( 'offer' => $o, 'row' => $row );
		}
	}

	$many  = count( $groups ) > 1;
	$mains = array();
	foreach ( $groups as $g ) {
		$row  = $g['row'];
		$main = $g['main'];
		$dur  = $main ? $main['duration'] : null;
		$cond = $main ? condition_facts( (string) $main['conditions'], $dur ) : array( 'facts' => array(), 'swimwear' => false );

		if ( 'yes' === $row['casual'] ) {
			$label = __( 'Casual entry', 'oria' );
		} elseif ( '' !== $row['condition'] ) {
			$label = $row['condition'];
		} elseif ( 'no' === $row['casual'] ) {
			$label = __( 'No casual entry', 'oria' );
		} else {
			$label = $main ? ( KINDS[ $main['kind'] ] ?? '' ) : '';
		}
		if ( $many ) {
			$label = implode( ' · ', array_filter( array( implode( ' & ', array_unique( $g['facilities'] ) ), $label ) ) );
		}

		$meta = array();
		if ( $dur ) {
			/* translators: %d: minutes */
			$meta[] = sprintf( __( '%d minutes', 'oria' ), (int) $dur );
		}
		if ( 'shared' === $row['setting'] ) {
			$meta[] = __( 'Shared experience', 'oria' );
		} elseif ( isset( SETTINGS[ $row['setting'] ] ) && ! in_array( $row['setting'], array( '', 'unknown' ), true ) ) {
			$meta[] = SETTINGS[ $row['setting'] ];
		}
		if ( null !== $row['min_age'] ) {
			/* translators: %d: minimum age */
			$meta[] = sprintf( __( 'Ages %d+', 'oria' ), (int) $row['min_age'] );
		}
		$facts = array_values( array_diff( $cond['facts'], $meta ) );

		// The practical note: what to bring, only what the record says.
		$notes = array();
		$swim  = trim( (string) $row['swimwear'] );
		if ( preg_match( '/^required\b\s*(.*)$/i', $swim, $m ) ) {
			$rest    = trim( $m[1], " .;,-" );
			$notes[] = '' !== $rest
				/* translators: %s: extra detail, e.g. "(all-gendered bath house)" */
				? sprintf( __( 'Bring swimwear — it’s required %s.', 'oria' ), $rest )
				: __( 'Bring swimwear — it’s required.', 'oria' );
		} elseif ( preg_match( '/^bring/i', $swim ) ) {
			$notes[] = ucfirst( $swim ); // Already an instruction: "Bring bathers."
		} elseif ( '' !== $swim ) {
			/* translators: %s: swimwear rule as published */
			$notes[] = sprintf( __( 'Swimwear: %s', 'oria' ), $swim );
		} elseif ( $cond['swimwear'] ) {
			$notes[] = __( 'Bring swimwear — it’s required.', 'oria' );
		}
		$has_towel = (bool) preg_grep( '/towel/i', $row['includes'] );
		if ( '' !== $row['towels'] && ! $has_towel ) {
			/* translators: %s: towel policy as published */
			$notes[] = sprintf( __( 'Towels: %s', 'oria' ), $row['towels'] );
		}

		$unknown = ! $main || null === $main['price'];
		$mains[] = array(
			'facility'  => $row['facility'],
			'label'     => $label,
			'title'     => $main && '' !== $main['product'] ? $main['product'] : sprintf( /* translators: %s: facility */ __( '%s access', 'oria' ), FACILITIES[ $row['facility'] ] ?? '' ),
			'meta'      => $meta,
			'facts'     => $facts,
			'includes'  => $row['includes'],
			'notes'     => $notes,
			'essentials' => $row['essentials'],
			'quiet'     => $row['quiet'],
			'notice'    => notice_live( $row ) ? $row['notice'] : ( 'temporarily_unavailable' === $row['status'] ? __( 'Temporarily unavailable', 'oria' ) : '' ),
			'price'     => $unknown ? '' : money( (float) $main['price'] ),
			'price_alt' => $unknown ? price_label( $main ) : '',
			'basis'     => $unknown ? '' : implode( ' · ', array_filter( array( basis_label( $main ), 'AUD' ) ) ),
			'book_url'  => $row['book_url'],
			'checked'   => checked_label( $row ),
			'source'    => $row['sources'][0] ?? '',
		);
	}

	$visits = array();
	$plans  = array();
	$main_dur = $mains && isset( $groups[ array_key_first( $groups ) ]['main']['duration'] ) ? $groups[ array_key_first( $groups ) ]['main']['duration'] : null;
	foreach ( $alts as $a ) {
		$o    = $a['offer'];
		$cond = condition_facts( (string) $o['conditions'], $o['duration'] );
		$det  = $cond['facts'];
		if ( $o['duration'] && $o['duration'] !== $main_dur ) {
			/* translators: %d: minutes */
			array_unshift( $det, sprintf( __( '%d minutes', 'oria' ), (int) $o['duration'] ) );
		}
		$card = array(
			'kind'     => KINDS[ $o['kind'] ] ?? '',
			'title'    => (string) $o['product'],
			'price'    => null === $o['price'] ? '' : money( (float) $o['price'] ),
			'price_alt' => null === $o['price'] ? price_label( $o ) : '',
			'basis'    => null === $o['price'] ? '' : basis_label( $o ),
			'facts'    => $det,
			'swimwear' => $cond['swimwear'],
			'line'     => '',
			'book_url' => (string) $a['row']['book_url'],
		);
		if ( in_array( $o['kind'], PLAN_KINDS, true ) ) {
			$plans[] = $card;
		} else {
			$visits[] = $card;
		}
	}

	/*
	 * A named silent or quiet session that is also an offer belongs on that
	 * offer's card, not repeated on the main one.
	 */
	foreach ( $mains as $i => $m ) {
		if ( '' === $m['quiet'] || ! preg_match( '/\b(silent|quiet)\b/i', $m['quiet'], $w ) ) {
			continue;
		}
		foreach ( $visits as $j => $v ) {
			if ( stripos( $v['title'], $w[1] ) !== false ) {
				$line = trim( (string) preg_replace( '/^[^;]*' . preg_quote( $v['title'], '/' ) . '[^;]*;\s*/i', '', $m['quiet'] ) );
				$visits[ $j ]['line'] = $line !== $m['quiet'] ? ucfirst( $line ) : '';
				$mains[ $i ]['quiet'] = $line !== $m['quiet'] ? '' : $m['quiet'];
				break;
			}
		}
	}

	return array( 'anchor' => $anchor, 'mains' => $mains, 'visits' => $visits, 'plans' => $plans );
}

/* ---------------------------------------------------------------------- faq */

/** "A, B and C" */
function listed( array $names ): string {
	$names = array_values( array_filter( $names ) );
	if ( count( $names ) < 2 ) {
		return (string) ( $names[0] ?? '' );
	}
	$last = array_pop( $names );
	return implode( ', ', $names ) . ' and ' . $last;
}

/**
 * Practical questions answered from the saved rows, never from copy: when a
 * price changes in the listing, the answer changes with it. A question the
 * data cannot answer is left out rather than answered vaguely.
 *
 * @param array<int, array> $sums listing id => summary()
 * @return list<array{q: string, a: string}>
 */
function faqs( array $sums, string $place ): array {
	$name = static fn( int $id ): string => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	$out  = array();

	// Cheapest verified price first; unknown last. The same order as the table.
	uasort(
		$sums,
		static fn( array $a, array $b ): int => ( null === $a['price'] ? INF : (float) $a['price'] ) <=> ( null === $b['price'] ? INF : (float) $b['price'] )
	);
	$priced = static fn( int $id, array $s ): string => null !== $s['price'] ? $name( $id ) . ', ' . money( (float) $s['price'] ) : $name( $id );

	$casual = array();
	foreach ( $sums as $id => $s ) {
		if ( 'yes' === $s['casual'] ) {
			$casual[] = $priced( (int) $id, $s );
		}
	}
	$out[] = array(
		'q' => sprintf( 'Which steam rooms in %s can I visit without a membership?', $place ),
		'a' => $casual
			? sprintf( 'Of the places listed here, these publish a casual visit that includes the steam room: %s. Anywhere else needs a treatment, a membership or another kind of booking, and each card says which.', listed( $casual ) )
			: 'None of the places listed here publishes a casual visit that includes the steam room. Each card says what access needs instead.',
	);

	$cheap = array();
	foreach ( $sums as $id => $s ) {
		if ( null !== $s['price'] && $s['price'] > 0 && $s['price'] < 25 ) {
			$cheap[] = $priced( (int) $id, $s );
		}
	}
	if ( $cheap ) {
		$out[] = array(
			'q' => 'Is there a steam room for under $25?',
			'a' => sprintf( 'Yes: %s. These are standard adult entries as the venues published them on the day checked. Whether the pools are included differs, so check the card.', listed( $cheap ) ),
		);
	}

	$alone = array();
	foreach ( $sums as $id => $s ) {
		if ( KINDS['steam_session'] === $s['kind'] ) {
			$alone[] = $name( (int) $id );
		}
	}
	$out[] = array(
		'q' => 'Is steam room access sold on its own?',
		'a' => $alone
			? sprintf( 'Rarely. %s sells a steam session on its own. Everywhere else the steam room comes with something larger: a pool or wellness entry, a bathhouse session, a recovery session, or a spa treatment.', listed( $alone ) )
			: 'Not at the places listed here. The steam room comes with something larger: a pool or wellness entry, a bathhouse session, a recovery session, or a spa treatment.',
	);

	$quiet = array();
	foreach ( $sums as $id => $s ) {
		if ( '' !== (string) $s['quiet'] ) {
			$quiet[] = $name( (int) $id ) . ': ' . $s['quiet'];
		}
	}
	if ( $quiet ) {
		$out[] = array(
			'q' => 'Are there quiet or silent steam sessions?',
			'a' => sprintf( 'Only where a venue names one. %s. No other place listed here publishes a quiet session.', implode( '. ', $quiet ) ),
		);
	}

	$out[] = array(
		'q' => 'What should I bring?',
		'a' => 'Swimwear. Whether towels are included, the minimum age and whether you must book all vary by venue: each card lists what that venue publishes, and its rules page has the rest.',
	);

	return $out;
}
