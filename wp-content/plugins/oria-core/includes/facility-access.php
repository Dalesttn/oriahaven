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
	return array(
		'price'      => $main ? $main['price'] : null,
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
