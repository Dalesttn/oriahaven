<?php
/**
 * Offers a business adds itself, from My Oria (Offers brief, Oct 2026).
 *
 * The owner form asks for the same structured offer the researched ones
 * carry -- format, upfront price, what it is for, what is included, who can
 * buy it, terms, weekend use, what happens afterwards, a booking link -- so
 * an owner's offer filters, sorts and compares on /offers/ exactly like one
 * we read off a website. The words still live in the three ACF fields
 * (offer_title / offer_text / offer_until); the structure is the same
 * `_offer_extra` JSON Offers\extra_from() writes for offers.json rows, one
 * shape for both.
 *
 *   is_field() / read() / put()   the editor's value() and write() route the
 *                                 offer_* structure fields here
 *   needs_review()                a listing's FIRST owner offer is checked by
 *                                 us before it shows; once one is approved
 *                                 (meta _oria_offer_ok) later edits go live
 *   hold() / approve() / decline()  the waiting room, the admin page
 *                                 (Listings > Offer submissions) and the
 *                                 emails either side of it
 *   after_write()                 an owner's offer is the owner's: the
 *                                 advertised source we recorded is cleared,
 *                                 the date is set, and the card says it
 *                                 came from the business
 *
 * Removing an offer is never held: taking something down should not wait
 * on us. Emails here are transactional (the owner asked for something), so
 * the outreach pause does not apply.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\OwnerOffers;

use Oria\Core\Offers;
use Oria\Core\PostTypes;
use Oria\Core\ListingEditor as Ed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PENDING  = '_oria_offer_pending';
const TRUSTED  = '_oria_offer_ok';
const PAGE     = 'oria-offer-submissions';

/**
 * Registry field name => key inside the `_offer_extra` JSON. Lists are
 * edited one item per line.
 */
const FIELDS = array(
	'offer_format'      => 'format',
	'offer_amount'      => 'amount',
	'offer_price'       => 'price',
	'offer_basis'       => 'basis',
	'offer_days'        => 'days',
	'offer_activities'  => 'activities',
	'offer_includes'    => 'includes',
	'offer_audience'    => 'audience',
	'offer_eligibility' => 'eligibility',
	'offer_terms'       => 'terms',
	'offer_weekend'     => 'weekend',
	'offer_renewal'     => 'renewal',
	'offer_link'        => 'link',
);
const LISTS = array( 'offer_includes', 'offer_terms' );

/** Every editor field that makes up the offer, the ACF three included. */
function group(): array {
	return array_merge( array( 'offer_title', 'offer_text', 'offer_until' ), array_keys( FIELDS ) );
}

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_offer_decide', __NAMESPACE__ . '\handle_decide' );
	add_action( 'admin_post_oria_offer_withdraw', __NAMESPACE__ . '\handle_withdraw' );
}
bootstrap();

/* ------------------------------------------------------------ the fields */

function is_field( string $name ): bool {
	return isset( FIELDS[ $name ] );
}

/** One structure field, as the editor draws it. */
function read( int $listing, string $name ) {
	$x = Offers\extra( $listing );
	$k = FIELDS[ $name ] ?? '';
	if ( '' === $k || ! array_key_exists( $k, $x ) ) {
		return in_array( $name, array( 'offer_activities' ), true ) ? array() : '';
	}
	$v = $x[ $k ];
	if ( in_array( $name, LISTS, true ) ) {
		return implode( "\n", (array) $v );
	}
	if ( 'offer_weekend' === $name ) {
		return true === $v ? 'yes' : ( false === $v ? 'no' : '' );
	}
	return is_array( $v ) ? $v : (string) $v;
}

/** Write one structure field into the JSON, through extra_from() so only valid values survive. */
function put( int $listing, string $name, $value ): void {
	$k = FIELDS[ $name ] ?? '';
	if ( '' === $k ) {
		return;
	}
	$row = Offers\extra( $listing );
	if ( in_array( $name, LISTS, true ) ) {
		$value = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $value ) ?: array() ) ) );
	} elseif ( 'offer_weekend' === $name ) {
		$value = 'yes' === $value ? true : ( 'no' === $value ? false : null );
	} elseif ( 'offer_amount' === $name ) {
		$value = '' === (string) $value ? null : (float) $value;
	} elseif ( 'offer_days' === $name ) {
		$value = (int) $value;
	} elseif ( 'offer_link' === $name ) {
		$value = wp_http_validate_url( (string) $value ) ? esc_url_raw( (string) $value, array( 'http', 'https' ) ) : '';
	}
	if ( null === $value || '' === $value || array() === $value ) {
		// Unticked activities fall back to the listing's own categories on /offers/.
		unset( $row[ $k ] );
	} else {
		$row[ $k ] = $value;
	}
	$clean = Offers\extra_from( $row );
	if ( ! empty( $row['link'] ) ) {
		$clean['link'] = (string) $row['link'];
	}
	if ( $clean ) {
		update_post_meta( $listing, Offers\META, (string) wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	} else {
		delete_post_meta( $listing, Offers\META );
	}
}

/**
 * After an owner's offer is written: it is theirs now. The source we
 * recorded off their website goes (so the card stops saying we read it
 * there), the date is today, and a price with no display string borrows
 * the upfront amount.
 */
function after_write( int $listing ): void {
	if ( '' === trim( (string) Ed\value( $listing, 'offer_title' ) ) ) {
		clear( $listing );
		return;
	}
	update_field( 'offer_source', '', $listing );
	update_field( 'offer_checked', current_time( 'Y-m-d' ), $listing );
	$x = Offers\extra( $listing );
	if ( empty( $x['price'] ) && isset( $x['amount'] ) ) {
		$x['price'] = function_exists( '\Oria\Core\OffersHub\money' ) ? \Oria\Core\OffersHub\money( (float) $x['amount'] ) : '$' . $x['amount'];
		update_post_meta( $listing, Offers\META, (string) wp_json_encode( $x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
	do_action( 'litespeed_purge_post', $listing );
	do_action( 'litespeed_purge_url', home_url( '/offers/' ) );
}

/** Take the owner's offer down entirely. */
function clear( int $listing ): void {
	foreach ( Offers\FIELDS as $f ) {
		update_field( $f, '', $listing );
	}
	delete_post_meta( $listing, Offers\META );
	do_action( 'litespeed_purge_post', $listing );
	do_action( 'litespeed_purge_url', home_url( '/offers/' ) );
}

/* ---------------------------------------------------------- the waiting room */

/** Does an owner's offer on this listing wait for us? Until one has been approved, yes. */
function needs_review( int $listing ): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return false;
	}
	return '1' !== (string) get_post_meta( $listing, TRUSTED, true );
}

/** @return array{fields?:array<string,mixed>, at?:int, by?:int} */
function pending( int $listing ): array {
	$p = get_post_meta( $listing, PENDING, true );
	return is_array( $p ) && ! empty( $p['fields'] ) ? $p : array();
}

/** Would these values change what is live? */
function differs( int $listing, array $fields ): bool {
	foreach ( $fields as $name => $v ) {
		if ( ! Ed\same( Ed\value( $listing, $name ), $v ) ) {
			return true;
		}
	}
	return false;
}

/** Hold a proposed offer and tell us. A second save replaces the first. */
function hold( int $listing, array $fields, int $user ): void {
	update_post_meta( $listing, PENDING, array( 'fields' => $fields, 'at' => time(), 'by' => $user ) );
	$to = (string) get_option( 'admin_email' );
	if ( '' === $to ) {
		return;
	}
	$who = get_userdata( $user );
	wp_mail(
		$to,
		sprintf( /* translators: %s: business */ __( '[Oria] New offer to check: %s', 'oria' ), html_entity_decode( get_the_title( $listing ), ENT_QUOTES, 'UTF-8' ) ),
		sprintf(
			"%s (%s) added an offer:\n\n%s\n%s\n\nCheck it and approve or decline:\n%s\n",
			$who ? $who->display_name : 'The owner',
			$who ? $who->user_email : '',
			(string) ( $fields['offer_title'] ?? '' ),
			(string) ( $fields['offer_text'] ?? '' ),
			admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . PAGE )
		)
	);
}

/** Publish the waiting offer, trust the listing from now on, and tell the owner. */
function approve( int $listing ): bool {
	$p = pending( $listing );
	if ( ! $p ) {
		return false;
	}
	foreach ( (array) $p['fields'] as $name => $v ) {
		if ( in_array( $name, group(), true ) ) {
			Ed\write( $listing, (string) $name, $v );
		}
	}
	after_write( $listing );
	update_post_meta( $listing, TRUSTED, '1' );
	delete_post_meta( $listing, PENDING );
	audit( $listing, __( 'Offer approved and published', 'oria' ) );
	tell_owner(
		$listing,
		__( 'Your offer is live on Oria Haven', 'oria' ),
		sprintf(
			"Thanks for adding your offer. It's now on your profile, on the category pages your listing appears in, and on %s.\n\nFrom now on, changes you make to it go live straight away. You can edit or remove it any time in My Oria:\n%s\n",
			home_url( '/offers/' ),
			add_query_arg( 'section', 'offers', \Oria\Core\MyOria\url( 'listing-edit' ) )
		)
	);
	return true;
}

/** Turn the waiting offer down, with a reason the owner can act on. */
function decline( int $listing, string $reason ): bool {
	$p = pending( $listing );
	if ( ! $p ) {
		return false;
	}
	delete_post_meta( $listing, PENDING );
	audit( $listing, __( 'Offer declined', 'oria' ) . ( '' !== $reason ? ': ' . $reason : '' ) );
	tell_owner(
		$listing,
		__( 'About the offer you added on Oria Haven', 'oria' ),
		sprintf(
			"Thanks for adding an offer. We couldn't publish it as it stands%s\n\nYou can change it and save it again in My Oria, and we'll take another look:\n%s\n",
			'' !== $reason ? ":\n\n" . $reason : '.',
			add_query_arg( 'section', 'offers', \Oria\Core\MyOria\url( 'listing-edit' ) )
		)
	);
	return true;
}

function tell_owner( int $listing, string $subject, string $body ): void {
	$owner = get_userdata( (int) get_post_meta( $listing, 'claimed_by', true ) );
	if ( ! $owner || ! is_email( $owner->user_email ) ) {
		return;
	}
	wp_mail( $owner->user_email, $subject, $body . "\n" . __( 'Oria Haven', 'oria' ) . "\n" );
}

function audit( int $listing, string $text ): void {
	if ( function_exists( '\Oria\Core\Audit\note' ) ) {
		\Oria\Core\Audit\note( $listing, $text, get_current_user_id() );
	}
}

/** An owner taking back an offer that is still waiting. */
function handle_withdraw(): void {
	$listing = Ed\listing_for( get_current_user_id() );
	if ( ! $listing ) {
		wp_safe_redirect( \Oria\Core\MyOria\url() );
		exit;
	}
	// Its own nonce name: the button sits inside the editor form, which posts a _wpnonce of its own.
	check_admin_referer( 'oria_offer_withdraw', 'oria_wn' );
	delete_post_meta( $listing, PENDING );
	audit( $listing, __( 'Owner withdrew an offer that was waiting to be checked', 'oria' ) );
	wp_safe_redirect( add_query_arg( 'section', 'offers', \Oria\Core\MyOria\url( 'listing-edit' ) ) );
	exit;
}

/* ------------------------------------------------------------- admin page */

/** Listing ids with an offer waiting, oldest first. */
function waiting(): array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", PENDING ) ) );
	$ids = array_values( array_filter( $ids, static fn( int $id ): bool => (bool) pending( $id ) ) );
	usort( $ids, static fn( int $a, int $b ): int => (int) ( pending( $a )['at'] ?? 0 ) <=> (int) ( pending( $b )['at'] ?? 0 ) );
	return $ids;
}

function menu(): void {
	$n     = count( waiting() );
	$label = __( 'Offer submissions', 'oria' ) . ( $n ? ' <span class="awaiting-mod">' . (int) $n . '</span>' : '' );
	add_submenu_page( 'edit.php?post_type=' . PostTypes\LISTING, __( 'Offer submissions', 'oria' ), $label, 'manage_options', PAGE, __NAMESPACE__ . '\page' );
}

/** The labels the owner saw, for the review table. */
function labels(): array {
	$out = array();
	foreach ( (array) ( Ed\section( 'offers' )['fields'] ?? array() ) as $f ) {
		$out[ (string) $f['name'] ] = array( 'label' => (string) $f['label'], 'choices' => Ed\choices_for( $f ) );
	}
	return $out;
}

function page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ids    = waiting();
	$labels = labels();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
	$done = sanitize_key( (string) ( $_GET['done'] ?? '' ) );

	echo '<div class="wrap"><h1>' . esc_html__( 'Offer submissions', 'oria' ) . '</h1>';
	echo '<p style="max-width:62rem">' . esc_html__( 'A business’s first offer waits here until you approve it. Check it is a real, current offer the business runs, the price and conditions are clear, and it makes no health claims. Once you approve one, that business’s later edits go live without coming back here.', 'oria' ) . '</p>';
	if ( 'approved' === $done ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Approved. The offer is live and the owner has been emailed.', 'oria' ) . '</p></div>';
	} elseif ( 'declined' === $done ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Declined. The owner has been emailed with your reason.', 'oria' ) . '</p></div>';
	}
	if ( ! $ids ) {
		echo '<p><strong>' . esc_html__( 'Nothing waiting.', 'oria' ) . '</strong></p></div>';
		return;
	}
	foreach ( $ids as $id ) {
		$p     = pending( $id );
		$owner = get_userdata( (int) ( $p['by'] ?? 0 ) );
		echo '<div class="card" style="max-width:62rem;padding:1rem 1.25rem;margin:1rem 0">';
		printf(
			'<h2 style="margin-top:0">%s</h2><p>%s · <a href="%s">%s</a> · <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ),
			esc_html( sprintf( /* translators: 1: owner, 2: date */ __( 'From %1$s, %2$s', 'oria' ), $owner ? $owner->display_name . ' (' . $owner->user_email . ')' : __( 'the owner', 'oria' ), wp_date( 'j M Y, g:i a', (int) ( $p['at'] ?? 0 ) ) ) ),
			esc_url( (string) get_edit_post_link( $id ) ),
			esc_html__( 'Edit listing', 'oria' ),
			esc_url( (string) get_permalink( $id ) ),
			esc_html__( 'View profile', 'oria' )
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( (array) $p['fields'] as $name => $v ) {
			if ( '' === $v || array() === $v || null === $v ) {
				continue;
			}
			$l       = $labels[ $name ] ?? array( 'label' => $name, 'choices' => array() );
			$choices = (array) $l['choices'];
			if ( is_array( $v ) ) {
				$v = implode( ', ', array_map( static fn( $one ) => (string) ( $choices[ $one ] ?? $one ), $v ) );
			} elseif ( isset( $choices[ $v ] ) ) {
				$v = (string) $choices[ $v ];
			} elseif ( 'offer_until' === $name && preg_match( '/^\d{8}$/', (string) $v ) ) {
				$v = wp_date( 'j M Y', (int) strtotime( (string) $v ) );
			}
			$cell = 'offer_link' === $name ? '<a href="' . esc_url( (string) $v ) . '" target="_blank" rel="noopener nofollow">' . esc_html( (string) $v ) . '</a>' : nl2br( esc_html( (string) $v ) );
			echo '<tr><th style="width:14rem">' . esc_html( (string) $l['label'] ) . '</th><td>' . $cell . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem;display:grid;gap:.5rem;max-width:40rem">';
		echo '<input type="hidden" name="action" value="oria_offer_decide"><input type="hidden" name="listing" value="' . (int) $id . '">';
		wp_nonce_field( 'oria_offer_decide_' . $id );
		echo '<label for="oria-reason-' . (int) $id . '">' . esc_html__( 'Reason, if you decline (sent to the owner)', 'oria' ) . '</label>';
		echo '<textarea id="oria-reason-' . (int) $id . '" name="reason" rows="3" class="large-text"></textarea>';
		echo '<p style="margin:0;display:flex;gap:.5rem"><button class="button button-primary" name="decision" value="approve">' . esc_html__( 'Approve and publish', 'oria' ) . '</button><button class="button" name="decision" value="decline">' . esc_html__( 'Decline', 'oria' ) . '</button></p>';
		echo '</form></div>';
	}
	echo '</div>';
}

function handle_decide(): void {
	$id = (int) ( $_POST['listing'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! $id ) {
		wp_die( esc_html__( 'Not allowed.', 'oria' ), 403 );
	}
	check_admin_referer( 'oria_offer_decide_' . $id );
	$decision = sanitize_key( (string) ( $_POST['decision'] ?? '' ) );
	$reason   = sanitize_textarea_field( wp_unslash( (string) ( $_POST['reason'] ?? '' ) ) );
	$done     = '';
	if ( 'approve' === $decision && approve( $id ) ) {
		$done = 'approved';
	} elseif ( 'decline' === $decision && decline( $id, $reason ) ) {
		$done = 'declined';
	}
	wp_safe_redirect( add_query_arg( 'done', $done, admin_url( 'edit.php?post_type=' . PostTypes\LISTING . '&page=' . PAGE ) ) );
	exit;
}

/* -------------------------------------------------- /offers/add/ (the way in) */

const ADD_QV = 'oria_offers_add';

add_action( 'init', __NAMESPACE__ . '\add_route' );
add_filter( 'query_vars', static fn( array $v ): array => array_merge( $v, array( ADD_QV ) ) );
add_action( 'parse_query', __NAMESPACE__ . '\add_fix_query' );
add_action( 'template_redirect', __NAMESPACE__ . '\add_redirect' );
add_filter( 'template_include', __NAMESPACE__ . '\add_template' );
add_filter( 'wpseo_title', __NAMESPACE__ . '\add_title' );
add_filter( 'document_title_parts', __NAMESPACE__ . '\add_core_title' );
add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\add_description' );
add_filter( 'wpseo_robots', __NAMESPACE__ . '\add_robots' );
add_filter( 'wpseo_canonical', __NAMESPACE__ . '\add_canonical' );

/** Where a business goes to add an offer. */
function add_url(): string {
	return home_url( '/offers/add/' );
}

/** The editor's offer section: My Oria signs a visitor in first and brings them back. */
function editor_url(): string {
	return add_query_arg( 'section', 'offers', \Oria\Core\MyOria\url( 'listing-edit' ) );
}

function add_route(): void {
	add_rewrite_rule( '^offers/add/?$', 'index.php?' . ADD_QV . '=1', 'top' );
	if ( get_option( 'oria_offers_add_rewrite' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'oria_offers_add_rewrite', '1' );
	}
}

function is_add(): bool {
	return (bool) get_query_var( ADD_QV );
}

function add_fix_query( \WP_Query $q ): void {
	if ( $q->is_main_query() && $q->get( ADD_QV ) ) {
		$q->is_home = $q->is_front_page = $q->is_archive = $q->is_singular = $q->is_404 = false;
		$q->set( 'posts_per_page', 1 );
	}
}

/** An owner who is signed in has nothing to read here: straight to their form. */
function add_redirect(): void {
	if ( is_add() && Ed\listing_for( get_current_user_id() ) ) {
		wp_safe_redirect( editor_url() );
		exit;
	}
}

function add_template( string $t ): string {
	return is_add() ? ( locate_template( array( 'oria-offers-add.php' ) ) ?: $t ) : $t;
}

function add_title( $t ) {
	return is_add() ? __( 'Add Your Offer to Oria Haven | Free for Perth Wellness Businesses', 'oria' ) : $t;
}

function add_core_title( array $parts ): array {
	if ( is_add() ) {
		$parts['title'] = __( 'Add your offer', 'oria' );
	}
	return $parts;
}

function add_description( $d ) {
	return is_add() ? __( 'Perth wellness businesses can add an intro pass, first-visit deal or special to Oria Haven for free. It shows on your profile, your category pages and the offers pages.', 'oria' ) : $d;
}

/** A how-to page for businesses, not something a searcher wants: kept out of the index. */
function add_robots( $r ) {
	return is_add() ? 'noindex, follow' : $r;
}

function add_canonical( $c ) {
	return is_add() ? add_url() : $c;
}
