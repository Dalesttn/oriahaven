<?php
/**
 * Retreat escapes: editorially chosen retreat packages, booked on
 * BookRetreats through Dale's own affiliate links.
 *
 * An OFFER is a specific package (one venue may have several); a listing
 * is a practice. The two stay separate: offers are not listings, never
 * enter Perth counts, maps or location filters, and have no public page of
 * their own -- a card that links to the provider is the whole product.
 *
 * Link integrity, by construction:
 *   - Each offer stores the exact URL pasted from the BookRetreats link
 *     builder. Nothing here builds, appends to or rewrites it.
 *   - A URL must be https and on an allowlisted host (Retreats settings),
 *     with no whitespace or control characters. There is no redirect
 *     endpoint and no cookie is set by this site.
 *   - Anchors are ordinary crawlable links with rel="sponsored", so they
 *     work with scripts off. Analytics only ever sees the offer id and
 *     where the card was, never the tracking URL.
 *
 * Nothing is promoted by accident: an offer shows only when it is
 * published, marked Active, has a valid link, a source, an image and its
 * own summary, and has not run out of dates. Everything else is an admin
 * concern, reported in the admin, never a dead public button.
 *
 * @package OriaCore
 */

declare(strict_types=1);

namespace Oria\Core\Retreats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT    = 'oria_retreat';
const OPTION = 'oria_retreats';
const PATH   = 'retreat-escapes';
const QV     = 'oria_retreats_hub';

const DESTINATIONS = array(
	'near-perth' => 'Near Perth',
	'wa'         => 'Western Australia',
	'bali'       => 'Bali',
);
const LENGTHS = array(
	'day'     => 'Day retreat',
	'weekend' => 'Weekend',
	'longer'  => 'Longer stay',
);
const DATE_MODELS = array(
	'check'    => 'Dates on the provider site',
	'fixed'    => 'One fixed departure',
	'multiple' => 'Several departures',
);
const STATES = array(
	'draft'       => 'Draft',
	'active'      => 'Active',
	'paused'      => 'Paused',
	'expired'     => 'Expired',
	'unavailable' => 'Unavailable',
);
const CURRENCIES = array( 'AUD', 'USD', 'IDR', 'EUR', 'GBP', 'NZD' );

/** Every stored field: key => sanitiser kind. */
const FIELDS = array(
	'provider'      => 'text',
	'listing'       => 'int',
	'booking'       => 'text',
	'source_url'    => 'url',
	'aff_url'       => 'aff',
	'destination'   => 'dest',
	'country'       => 'text',
	'locality'      => 'text',
	'length'        => 'len',
	'days'          => 'int',
	'nights'        => 'int',
	'date_model'    => 'datemodel',
	'start'         => 'date',
	'end'           => 'date',
	'image_source'  => 'text',
	'summary'       => 'textarea',
	'suits'         => 'textarea',
	'pace'          => 'text',
	'inclusions'    => 'textarea',
	'exclusions'    => 'textarea',
	'price_mode'    => 'pricemode',
	'price_amount'  => 'money',
	'price_currency' => 'currency',
	'price_basis'   => 'text',
	'price_checked' => 'date',
	'reviewed'      => 'date',
	'state'         => 'state',
	'order'         => 'int',
);

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register_cpt' );
	add_action( 'init', __NAMESPACE__ . '\route' );
	add_filter( 'query_vars', static fn( array $v ): array => array_merge( $v, array( QV ) ) );
	add_action( 'parse_query', __NAMESPACE__ . '\fix_query' );
	add_action( 'template_redirect', __NAMESPACE__ . '\gate' );
	add_filter( 'template_include', __NAMESPACE__ . '\template' );
	add_filter( 'wpseo_title', __NAMESPACE__ . '\seo_title' );
	add_filter( 'wpseo_metadesc', __NAMESPACE__ . '\seo_description' );
	add_filter( 'wpseo_canonical', __NAMESPACE__ . '\seo_canonical' );
	add_filter( 'wpseo_robots', __NAMESPACE__ . '\seo_robots' );
	add_filter( 'document_title_parts', __NAMESPACE__ . '\core_title' );

	add_action( 'admin_menu', __NAMESPACE__ . '\settings_page' );
	add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	add_action( 'add_meta_boxes_' . CPT, __NAMESPACE__ . '\metaboxes' );
	add_action( 'save_post_' . CPT, __NAMESPACE__ . '\save', 10, 2 );
	add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\publish_guard', 10, 2 );
	add_action( 'admin_notices', __NAMESPACE__ . '\admin_notices' );
	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );

	add_shortcode( 'retreat_offers', __NAMESPACE__ . '\shortcode' );
	add_action( 'rest_api_init', __NAMESPACE__ . '\rest' );
}

function register_cpt(): void {
	register_post_type(
		CPT,
		array(
			'labels'              => array(
				'name'          => __( 'Retreat offers', 'oria' ),
				'singular_name' => __( 'Retreat offer', 'oria' ),
				'add_new_item'  => __( 'Add retreat offer', 'oria' ),
				'edit_item'     => __( 'Edit retreat offer', 'oria' ),
				'menu_name'     => __( 'Retreat offers', 'oria' ),
			),
			// No public pages: the card and its link are the product.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'show_in_nav_menus'   => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-palmtree',
			'menu_position'       => 57,
			'supports'            => array( 'title', 'thumbnail' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);
}

/* --------------------------------------------------------------- settings */

/** @return array{enabled: bool, disclosure: string, review_days: int, hosts: string[], links: array<string, string>} */
function settings(): array {
	$s = get_option( OPTION, array() );
	$s = is_array( $s ) ? $s : array();
	$hosts = array_values( array_filter( array_map( static fn( $h ): string => strtolower( trim( (string) $h ) ), (array) ( $s['hosts'] ?? array() ) ) ) );
	return array(
		'enabled'     => ! empty( $s['enabled'] ),
		'disclosure'  => '' !== trim( (string) ( $s['disclosure'] ?? '' ) ) ? (string) $s['disclosure'] : default_disclosure(),
		'review_days' => max( 1, (int) ( $s['review_days'] ?? 30 ) ),
		'hosts'       => $hosts ?: array( 'bookretreats.com', 'www.bookretreats.com' ),
		'links'       => array_map( 'strval', (array) ( $s['links'] ?? array() ) ),
	);
}

function default_disclosure(): string {
	return __( 'Some retreat links are affiliate links. If you make a qualifying booking through one, Oria Haven may earn a commission. You complete your booking with the named booking provider; its prices, availability and booking terms apply.', 'oria' );
}

function settings_page(): void {
	add_submenu_page(
		'edit.php?post_type=' . CPT,
		__( 'Retreat settings', 'oria' ),
		__( 'Settings', 'oria' ),
		'manage_options',
		'oria-retreat-settings',
		__NAMESPACE__ . '\render_settings'
	);
}

function register_settings(): void {
	register_setting(
		'oria_retreats',
		OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
			'default'           => array(),
		)
	);
}

function sanitize_settings( $raw ): array {
	$in    = is_array( $raw ) ? $raw : array();
	$hosts = preg_split( '/[\s,]+/', strtolower( (string) ( $in['hosts'] ?? '' ) ) ) ?: array();
	$hosts = array_values( array_unique( array_filter( $hosts, static fn( string $h ): bool => (bool) preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $h ) ) ) );
	$out   = array(
		'enabled'     => ! empty( $in['enabled'] ),
		'disclosure'  => sanitize_textarea_field( (string) ( $in['disclosure'] ?? '' ) ),
		'review_days' => max( 1, min( 365, (int) ( $in['review_days'] ?? 30 ) ) ),
		'hosts'       => $hosts,
		'links'       => array(),
	);
	foreach ( array_merge( array( 'all' => '' ), DESTINATIONS ) as $k => $label ) {
		$u = trim( (string) ( $in['links'][ $k ] ?? '' ) );
		if ( '' !== $u && valid_link( $u, $hosts ?: array( 'bookretreats.com', 'www.bookretreats.com' ) ) ) {
			$out['links'][ $k ] = $u;
		}
	}
	return $out;
}

function render_settings(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = settings();
	echo '<div class="wrap"><h1>' . esc_html__( 'Retreat settings', 'oria' ) . '</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'oria_retreats' );
	echo '<table class="form-table" role="presentation"><tbody>';
	printf(
		'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s[enabled]" value="1"%s> %s</label><p class="description">%s</p></td></tr>',
		esc_html__( 'Recommendations', 'oria' ),
		esc_attr( OPTION ),
		checked( $s['enabled'], true, false ),
		esc_html__( 'Show retreat recommendations on the site', 'oria' ),
		esc_html__( 'Off hides the hub, the menu item and every retreat module. Even when on, nothing appears until at least one offer is Active and complete.', 'oria' )
	);
	printf(
		'<tr><th scope="row"><label for="ro-disc">%s</label></th><td><textarea id="ro-disc" class="large-text" rows="3" name="%s[disclosure]">%s</textarea><p class="description">%s</p></td></tr>',
		esc_html__( 'Disclosure', 'oria' ),
		esc_attr( OPTION ),
		esc_textarea( $s['disclosure'] ),
		esc_html__( 'Shown beside every set of affiliate recommendations. Leave empty for the default wording.', 'oria' )
	);
	printf(
		'<tr><th scope="row"><label for="ro-days">%s</label></th><td><input id="ro-days" type="number" min="1" max="365" name="%s[review_days]" value="%d"> %s<p class="description">%s</p></td></tr>',
		esc_html__( 'Price review window', 'oria' ),
		esc_attr( OPTION ),
		(int) $s['review_days'],
		esc_html__( 'days', 'oria' ),
		esc_html__( 'A price checked longer ago than this is shown as "Check current price" and flagged in the offer list.', 'oria' )
	);
	printf(
		'<tr><th scope="row"><label for="ro-hosts">%s</label></th><td><input id="ro-hosts" type="text" class="large-text" name="%s[hosts]" value="%s"><p class="description">%s</p></td></tr>',
		esc_html__( 'Allowed link hosts', 'oria' ),
		esc_attr( OPTION ),
		esc_attr( implode( ', ', $s['hosts'] ) ),
		esc_html__( 'Affiliate links must be https and on one of these hosts. If the links from your BookRetreats link builder use a tracking domain, add it here exactly as it appears in the link.', 'oria' )
	);
	echo '<tr><th scope="row">' . esc_html__( 'Search links (optional)', 'oria' ) . '</th><td>';
	foreach ( array_merge( array( 'all' => __( 'All retreats', 'oria' ) ), DESTINATIONS ) as $k => $label ) {
		printf(
			'<p><label>%s<br><input type="url" class="large-text" name="%s[links][%s]" value="%s" placeholder="%s"></label></p>',
			esc_html( $label ),
			esc_attr( OPTION ),
			esc_attr( $k ),
			esc_attr( (string) ( $s['links'][ $k ] ?? '' ) ),
			esc_attr__( 'Paste a complete link from the BookRetreats link builder', 'oria' )
		);
	}
	echo '<p class="description">' . esc_html__( 'Only complete links generated in your BookRetreats dashboard. Nothing is added to them.', 'oria' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button();
	echo '</form>';
	echo '<h2>' . esc_html__( 'Results', 'oria' ) . '</h2><p>' . esc_html__( 'The offer list shows outbound affiliate clicks from the last 30 days: presses of a "Check dates" link, not people and not bookings. Bookings and commission are reported only in your BookRetreats affiliate dashboard; reconcile there.', 'oria' ) . '</p>';
	echo '</div>';
}

/* ----------------------------------------------------------------- links */

/**
 * Whether a pasted URL is safe to publish as-is: https, an allowlisted
 * host, no whitespace or control characters, a sane length. The URL is
 * never modified; it is either accepted exactly or refused.
 */
function valid_link( string $url, ?array $hosts = null ): bool {
	$hosts = $hosts ?? settings()['hosts'];
	if ( '' === $url || strlen( $url ) > 2048 || preg_match( '/[\s\x00-\x1F\x7F<>"\'\\\\]/', $url ) ) {
		return false;
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
		return false;
	}
	return in_array( strtolower( (string) ( $parts['host'] ?? '' ) ), $hosts, true );
}

/* --------------------------------------------------------------- the data */

function get( int $id, string $key ): string {
	return (string) get_post_meta( $id, '_ro_' . $key, true );
}

/** Lines of a textarea field, trimmed, empty ones dropped. */
function lines( int $id, string $key ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', get( $id, $key ) ) ?: array() ) ) );
}

/** Today in the site's own timezone (Australia/Perth). */
function today(): string {
	return wp_date( 'Y-m-d' );
}

/**
 * What stops an offer being shown, in plain words. Empty means it can be.
 * Works from stored values, or from a draft of values about to be saved.
 *
 * @param array<string, string>|null $v Values to check instead of the stored ones.
 * @return string[]
 */
function problems( int $id, ?array $v = null, ?int $thumb = null ): array {
	$val   = static fn( string $k ): string => null !== $v ? (string) ( $v[ $k ] ?? '' ) : get( $id, $k );
	$thumb = $thumb ?? (int) get_post_thumbnail_id( $id );
	$out   = array();
	if ( ! valid_link( $val( 'aff_url' ) ) ) {
		$out[] = __( 'an affiliate link from your BookRetreats link builder (https, on an allowed host)', 'oria' );
	}
	if ( '' === $val( 'source_url' ) ) {
		$out[] = __( 'the original source URL', 'oria' );
	}
	if ( ! $thumb ) {
		$out[] = __( 'an image (Featured image)', 'oria' );
	}
	if ( '' === $val( 'image_source' ) ) {
		$out[] = __( 'where the image came from', 'oria' );
	}
	if ( mb_strlen( trim( $val( 'summary' ) ) ) < 60 ) {
		$out[] = __( 'an original summary (a couple of sentences)', 'oria' );
	}
	if ( ! isset( DESTINATIONS[ $val( 'destination' ) ] ) ) {
		$out[] = __( 'a destination', 'oria' );
	}
	if ( ! isset( LENGTHS[ $val( 'length' ) ] ) ) {
		$out[] = __( 'a duration', 'oria' );
	}
	if ( '' === $val( 'reviewed' ) ) {
		$out[] = __( 'the date you last checked it', 'oria' );
	}
	return $out;
}

/** Whether its dates have all gone. "Dates on the provider site" never expires by date. */
function expired( int $id ): bool {
	$model = get( $id, 'date_model' );
	$end   = get( $id, 'end' ) ?: get( $id, 'start' );
	if ( in_array( $model, array( 'fixed', 'multiple' ), true ) && '' !== $end ) {
		return $end < today();
	}
	return false;
}

/** Shown to the public? Every rule in one place. */
function eligible( int $id ): bool {
	return CPT === get_post_type( $id )
		&& 'publish' === get_post_status( $id )
		&& 'active' === get( $id, 'state' )
		&& ! expired( $id )
		&& ! problems( $id );
}

/**
 * The offers that may be shown, in editorial order.
 *
 * @param array{destination?: string, length?: string, ids?: int[], limit?: int} $args
 * @return int[]
 */
function active_offers( array $args = array() ): array {
	if ( ! settings()['enabled'] ) {
		return array();
	}
	$q = array(
		'post_type'      => CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	if ( ! empty( $args['ids'] ) ) {
		$q['post__in'] = array_map( 'intval', (array) $args['ids'] );
		$q['orderby']  = 'post__in';
	}
	$ids = array_values( array_filter( array_map( 'intval', get_posts( $q ) ), __NAMESPACE__ . '\eligible' ) );
	if ( ! empty( $args['destination'] ) ) {
		$ids = array_values( array_filter( $ids, static fn( int $id ): bool => get( $id, 'destination' ) === $args['destination'] ) );
	}
	if ( ! empty( $args['length'] ) ) {
		$ids = array_values( array_filter( $ids, static fn( int $id ): bool => get( $id, 'length' ) === $args['length'] ) );
	}
	if ( empty( $args['ids'] ) ) {
		usort(
			$ids,
			static fn( int $a, int $b ): int => array( (int) get( $a, 'order' ) ?: 999, get_the_title( $a ) ) <=> array( (int) get( $b, 'order' ) ?: 999, get_the_title( $b ) )
		);
	}
	return array_slice( $ids, 0, (int) ( $args['limit'] ?? 50 ) );
}

/** Is there anything to show at all? Drives the hub, the menu and every module. */
function published(): bool {
	static $p = null;
	if ( null === $p ) {
		$p = (bool) active_offers( array( 'limit' => 1 ) );
	}
	return $p;
}

/** A price that is still within its review window, else null. */
function price_fresh( int $id ): bool {
	$checked = get( $id, 'price_checked' );
	if ( '' === $checked ) {
		return false;
	}
	$age = ( strtotime( today() ) - strtotime( $checked ) ) / DAY_IN_SECONDS;
	return $age <= settings()['review_days'];
}

/** "From A$1,450 per person, twin share", or "Check current price". */
function price_label( int $id ): string {
	$amount = get( $id, 'price_amount' );
	if ( 'from' !== get( $id, 'price_mode' ) || '' === $amount || ! price_fresh( $id ) ) {
		return __( 'Check current price', 'oria' );
	}
	$cur    = get( $id, 'price_currency' ) ?: 'AUD';
	$symbol = array( 'AUD' => 'A$', 'USD' => 'US$', 'NZD' => 'NZ$', 'EUR' => '€', 'GBP' => '£' )[ $cur ] ?? $cur . ' ';
	$label  = sprintf( /* translators: %s: price */ __( 'From %s', 'oria' ), $symbol . number_format_i18n( (float) $amount, fmod( (float) $amount, 1.0 ) ? 2 : 0 ) );
	$basis  = get( $id, 'price_basis' );
	return '' !== $basis ? $label . ' ' . $basis : $label;
}

/** "3 days, 2 nights" / "Day retreat". */
function duration_label( int $id ): string {
	$days   = (int) get( $id, 'days' );
	$nights = (int) get( $id, 'nights' );
	if ( 'day' === get( $id, 'length' ) || ( $days <= 1 && ! $nights ) ) {
		return __( 'Day retreat', 'oria' );
	}
	$bits = array();
	if ( $days ) {
		$bits[] = sprintf( _n( '%d day', '%d days', $days, 'oria' ), $days );
	}
	if ( $nights ) {
		$bits[] = sprintf( _n( '%d night', '%d nights', $nights, 'oria' ), $nights );
	}
	return $bits ? implode( ', ', $bits ) : ( LENGTHS[ get( $id, 'length' ) ] ?? '' );
}

/** Where, in words: "Ubud, Bali" / "Margaret River, WA". */
function place_label( int $id ): string {
	$bits = array_filter( array( get( $id, 'locality' ), DESTINATIONS[ get( $id, 'destination' ) ] ?? '' ) );
	return implode( ', ', array_unique( $bits ) );
}

/* ---------------------------------------------------------------- output */

/** One card, from the theme's template part. */
function card( int $id, string $placement ): string {
	ob_start();
	get_template_part( 'template-parts/retreat-card', null, array( 'id' => $id, 'placement' => $placement ) );
	return (string) ob_get_clean();
}

function disclosure_html(): string {
	return '<p class="ro-disclosure" role="note">' . esc_html( settings()['disclosure'] ) . '</p>';
}

/**
 * [retreat_offers ids="12,34" placement="journal" heading="..."]
 * An editor-chosen list for a journal article. Draws nothing unless at
 * least one of the chosen offers is eligible.
 */
function shortcode( $atts ): string {
	$a   = shortcode_atts( array( 'ids' => '', 'placement' => 'journal', 'heading' => '' ), (array) $atts, 'retreat_offers' );
	$ids = array_filter( array_map( 'intval', explode( ',', (string) $a['ids'] ) ) );
	return module( $ids ? active_offers( array( 'ids' => $ids ) ) : array(), sanitize_key( (string) $a['placement'] ), (string) $a['heading'] );
}

/** A small disclosed set of cards for any placement. '' when there is nothing. */
function module( array $ids, string $placement, string $heading = '' ): string {
	if ( ! $ids ) {
		return '';
	}
	$css = get_template_directory() . '/assets/css/retreats.css';
	if ( is_readable( $css ) ) {
		wp_enqueue_style( 'oria-retreats', get_template_directory_uri() . '/assets/css/retreats.css', array(), (string) filemtime( $css ) );
	}
	$out  = '<section class="ro-module" aria-label="' . esc_attr( $heading ?: __( 'Retreat escapes', 'oria' ) ) . '">';
	$out .= '' !== $heading ? '<h2 class="ro-module__title">' . esc_html( $heading ) . '</h2>' : '';
	$out .= disclosure_html();
	$out .= '<div class="ro-grid">';
	foreach ( $ids as $id ) {
		$out .= card( (int) $id, $placement );
	}
	$out .= '</div></section>';
	return $out;
}

/* ------------------------------------------------------------------ route */

function route(): void {
	add_rewrite_rule( '^' . PATH . '/?$', 'index.php?' . QV . '=1', 'top' );
	if ( get_option( 'oria_retreats_rewrite' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'oria_retreats_rewrite', '1' );
	}
}

function is_hub(): bool {
	return (bool) get_query_var( QV );
}

/** The hub is being shown (not 404ed): live, or previewed by an editor. */
function serving(): bool {
	return is_hub() && ( published() || current_user_can( 'edit_posts' ) );
}

function hub_url(): string {
	return home_url( '/' . PATH . '/' );
}

function fix_query( \WP_Query $q ): void {
	if ( $q->is_main_query() && $q->get( QV ) ) {
		$q->is_home = $q->is_front_page = $q->is_archive = $q->is_singular = $q->is_404 = false;
		$q->set( 'posts_per_page', 1 );
	}
}

/**
 * The hub stays unpublished until it has something to offer. An editor
 * can preview it; everybody else gets a 404, so there is never a public,
 * empty commercial page.
 */
function gate(): void {
	if ( ! is_hub() || published() || current_user_can( 'edit_posts' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
}

function template( string $t ): string {
	if ( ! is_hub() || ( ! published() && ! current_user_can( 'edit_posts' ) ) ) {
		return $t;
	}
	return locate_template( array( 'oria-retreat-escapes.php' ) ) ?: $t;
}

function seo_title( $t ) {
	return serving() ? __( 'Retreat escapes: find a retreat that fits your time | Oria Haven', 'oria' ) : $t;
}

function core_title( array $parts ): array {
	if ( serving() ) {
		$parts['title'] = __( 'Retreat escapes', 'oria' );
	}
	return $parts;
}

function seo_description( $d ) {
	return serving() ? __( 'Compare retreats from a day near Perth to a longer escape: the setting, the pace and the practical details, before you choose.', 'oria' ) : $d;
}

function seo_canonical( $u ) {
	return serving() ? hub_url() : $u;
}

function seo_robots( $r ) {
	return ( is_hub() && ! published() ) ? 'noindex, nofollow' : $r;
}

/* ------------------------------------------------------------------ admin */

function metaboxes(): void {
	add_meta_box( 'oria-ro-offer', __( 'Offer details', 'oria' ), __NAMESPACE__ . '\render_metabox', CPT, 'normal', 'high' );
	add_meta_box( 'oria-ro-state', __( 'Status', 'oria' ), __NAMESPACE__ . '\render_state', CPT, 'side', 'high' );
}

function field_row( int $id, string $key, string $label, string $type = 'text', string $help = '', array $options = array() ): void {
	$val  = get( $id, $key );
	$name = 'ro[' . $key . ']';
	$fid  = 'ro-' . $key;
	echo '<tr><th scope="row"><label for="' . esc_attr( $fid ) . '">' . esc_html( $label ) . '</label></th><td>';
	switch ( $type ) {
		case 'textarea':
			printf( '<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>', esc_attr( $fid ), esc_attr( $name ), esc_textarea( $val ) );
			break;
		case 'select':
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $fid ), esc_attr( $name ) );
			foreach ( $options as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( (string) $k ), selected( $val, (string) $k, false ), esc_html( $l ) );
			}
			echo '</select>';
			break;
		case 'date':
			printf( '<input id="%1$s" type="date" name="%2$s" value="%3$s">', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ) );
			break;
		case 'number':
			printf( '<input id="%1$s" type="number" min="0" step="any" name="%2$s" value="%3$s" style="width:8em">', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ) );
			break;
		default:
			printf( '<input id="%1$s" type="text" name="%2$s" value="%3$s" class="large-text">', esc_attr( $fid ), esc_attr( $name ), esc_attr( $val ) );
	}
	if ( '' !== $help ) {
		echo '<p class="description">' . esc_html( $help ) . '</p>';
	}
	echo '</td></tr>';
}

function render_metabox( \WP_Post $post ): void {
	wp_nonce_field( 'oria_ro_save', 'oria_ro_nonce' );
	$id = (int) $post->ID;
	echo '<p>' . esc_html__( 'Write everything in your own words. Nothing is copied from the provider page, and nothing is invented: leave a field empty rather than guess.', 'oria' ) . '</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	field_row( $id, 'aff_url', __( 'Affiliate link', 'oria' ), 'text', __( 'The complete link from your BookRetreats link builder, pasted exactly. It is stored and published unchanged.', 'oria' ) );
	if ( '' !== get( $id, 'aff_rejected' ) ) {
		echo '<tr><td></td><td><p style="color:#b32d2e;margin-top:-10px">' . esc_html( sprintf( /* translators: %s: rejected link */ __( 'Not saved: "%s". A link must start https:// and be on an allowed host (Retreat offers, Settings). Paste it exactly as the BookRetreats link builder gives it.', 'oria' ), get( $id, 'aff_rejected' ) ) ) . '</p></td></tr>';
	}
	field_row( $id, 'source_url', __( 'Original source URL', 'oria' ), 'text', __( 'The retreat page you checked the details against.', 'oria' ) );
	field_row( $id, 'provider', __( 'Organiser', 'oria' ), 'text', __( 'The retreat organiser or venue, as named on the source.', 'oria' ) );
	field_row( $id, 'booking', __( 'Booking provider', 'oria' ), 'text', __( 'Normally BookRetreats.', 'oria' ) );
	field_row( $id, 'listing', __( 'Related Oria listing ID', 'oria' ), 'number', __( 'Optional: when the organiser is also listed on Oria Haven.', 'oria' ) );
	field_row( $id, 'destination', __( 'Destination', 'oria' ), 'select', __( 'Near Perth, Western Australia and Bali are kept separate. A Bali retreat never appears in Perth results.', 'oria' ), array_merge( array( '' => '—' ), DESTINATIONS ) );
	field_row( $id, 'locality', __( 'Locality', 'oria' ), 'text', __( 'Town or area, e.g. Ubud, Margaret River.', 'oria' ) );
	field_row( $id, 'country', __( 'Country', 'oria' ) );
	field_row( $id, 'length', __( 'Duration type', 'oria' ), 'select', '', array_merge( array( '' => '—' ), LENGTHS ) );
	field_row( $id, 'days', __( 'Days', 'oria' ), 'number' );
	field_row( $id, 'nights', __( 'Nights', 'oria' ), 'number' );
	field_row( $id, 'date_model', __( 'Dates', 'oria' ), 'select', __( 'Never invent a recurrence. "Dates on the provider site" is the safe default.', 'oria' ), DATE_MODELS );
	field_row( $id, 'start', __( 'Start date', 'oria' ), 'date', __( 'Only for a verified departure.', 'oria' ) );
	field_row( $id, 'end', __( 'Last date', 'oria' ), 'date', __( 'For one departure, its end. For several, the last one. After this the offer leaves the site.', 'oria' ) );
	field_row( $id, 'image_source', __( 'Image source', 'oria' ), 'text', __( 'Where the Featured image came from, e.g. the BookRetreats listing URL (their affiliate terms allow using site photos to promote them).', 'oria' ) );
	field_row( $id, 'summary', __( 'Summary', 'oria' ), 'textarea', __( 'Two or three sentences in your own words: the setting and what the days are like.', 'oria' ) );
	field_row( $id, 'suits', __( 'Who it may suit', 'oria' ), 'textarea', __( 'From the actual programme. Never medical suitability.', 'oria' ) );
	field_row( $id, 'pace', __( 'Pace and style', 'oria' ) );
	field_row( $id, 'inclusions', __( 'Key inclusions', 'oria' ), 'textarea', __( 'One per line, only what the source confirms. The first three show on the card.', 'oria' ) );
	field_row( $id, 'exclusions', __( 'Not included / limitations', 'oria' ), 'textarea', __( 'One per line, e.g. flights, airport transfers, shared room basis.', 'oria' ) );
	field_row( $id, 'price_mode', __( 'Price', 'oria' ), 'select', '', array( 'check' => __( 'Check current price', 'oria' ), 'from' => __( 'Verified starting price', 'oria' ) ) );
	field_row( $id, 'price_amount', __( 'Starting price', 'oria' ), 'number' );
	field_row( $id, 'price_currency', __( 'Currency', 'oria' ), 'select', __( 'As shown on the source. Never assume AUD.', 'oria' ), array_combine( CURRENCIES, CURRENCIES ) );
	field_row( $id, 'price_basis', __( 'Price basis', 'oria' ), 'text', __( 'e.g. per person, twin share.', 'oria' ) );
	field_row( $id, 'price_checked', __( 'Price checked on', 'oria' ), 'date', __( 'After the review window the card shows "Check current price".', 'oria' ) );
	field_row( $id, 'reviewed', __( 'Last editorial check', 'oria' ), 'date', __( 'The day you actually checked this against the source.', 'oria' ) );
	echo '</tbody></table>';
}

function render_state( \WP_Post $post ): void {
	$id = (int) $post->ID;
	echo '<p><label for="ro-state"><b>' . esc_html__( 'Recommendation status', 'oria' ) . '</b></label><br>';
	echo '<select id="ro-state" name="ro[state]" style="width:100%">';
	foreach ( STATES as $k => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( get( $id, 'state' ) ?: 'draft', $k, false ), esc_html( $l ) );
	}
	echo '</select></p>';
	echo '<p><label for="ro-order"><b>' . esc_html__( 'Editorial order', 'oria' ) . '</b></label><br><input id="ro-order" type="number" min="0" name="ro[order]" value="' . esc_attr( get( $id, 'order' ) ) . '" style="width:6em"></p>';
	echo '<p class="description">' . esc_html__( 'Chosen for suitability and information quality. Commission never decides the order.', 'oria' ) . '</p>';
	$p = problems( $id );
	if ( $p ) {
		echo '<p style="color:#b32d2e"><b>' . esc_html__( 'Not shown yet. Still needs:', 'oria' ) . '</b></p><ul style="list-style:disc;margin-left:1.2em">';
		foreach ( $p as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	} elseif ( eligible( $id ) ) {
		echo '<p style="color:#1a7f37"><b>' . esc_html__( 'Showing on the site.', 'oria' ) . '</b></p>';
	}
}

/** Clean one submitted value by its kind. */
function clean( string $kind, $raw ): string {
	$raw = is_scalar( $raw ) ? trim( (string) wp_unslash( $raw ) ) : '';
	switch ( $kind ) {
		case 'int':
			return '' === $raw ? '' : (string) max( 0, (int) $raw );
		case 'money':
			return '' === $raw ? '' : (string) max( 0, round( (float) $raw, 2 ) );
		case 'url':
			return esc_url_raw( $raw, array( 'https', 'http' ) );
		case 'aff':
			// Stored exactly, or not at all. A link we would have to "fix" is a link we cannot trust.
			return valid_link( $raw ) ? $raw : '';
		case 'date':
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
		case 'textarea':
			return sanitize_textarea_field( $raw );
		case 'dest':
			return isset( DESTINATIONS[ $raw ] ) ? $raw : '';
		case 'len':
			return isset( LENGTHS[ $raw ] ) ? $raw : '';
		case 'datemodel':
			return isset( DATE_MODELS[ $raw ] ) ? $raw : 'check';
		case 'pricemode':
			return 'from' === $raw ? 'from' : 'check';
		case 'currency':
			return in_array( $raw, CURRENCIES, true ) ? $raw : 'AUD';
		case 'state':
			return isset( STATES[ $raw ] ) ? $raw : 'draft';
		default:
			return sanitize_text_field( $raw );
	}
}

/** The submitted values, cleaned, or null when this is not our form. */
function submitted(): ?array {
	if ( ! isset( $_POST['oria_ro_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oria_ro_nonce'] ) ), 'oria_ro_save' ) ) {
		return null;
	}
	$in  = isset( $_POST['ro'] ) && is_array( $_POST['ro'] ) ? $_POST['ro'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned field by field below.
	$out = array();
	foreach ( FIELDS as $k => $kind ) {
		$out[ $k ] = clean( $kind, $in[ $k ] ?? '' );
	}
	// A link that was refused is kept aside, so the editor can see what was wrong with it.
	$raw                 = trim( (string) wp_unslash( (string) ( $in['aff_url'] ?? '' ) ) );
	$out['aff_rejected'] = ( '' !== $raw && '' === $out['aff_url'] ) ? mb_substr( sanitize_text_field( $raw ), 0, 300 ) : '';
	return $out;
}

/**
 * Publishing an Active offer that is not complete keeps it a draft, with a
 * note saying what is missing. Drafts, paused and expired offers save freely.
 */
function publish_guard( array $data, array $postarr ): array {
	if ( CPT !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
		return $data;
	}
	$v = submitted();
	if ( null === $v || 'active' !== $v['state'] ) {
		return $data;
	}
	$id    = (int) ( $postarr['ID'] ?? 0 );
	$thumb = isset( $_POST['_thumbnail_id'] ) ? (int) $_POST['_thumbnail_id'] : ( $id ? (int) get_post_thumbnail_id( $id ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in submitted().
	$missing = problems( $id, $v, $thumb > 0 ? $thumb : 0 );
	if ( $missing ) {
		$data['post_status'] = 'draft';
		set_transient( 'oria_ro_blocked_' . get_current_user_id(), $missing, 120 );
	}
	return $data;
}

function save( int $id, \WP_Post $post ): void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$v = submitted();
	if ( null === $v ) {
		return;
	}
	foreach ( $v as $k => $val ) {
		// wp_slash: update_post_meta() unslashes, which would alter a stored link.
		update_post_meta( $id, '_ro_' . $k, wp_slash( $val ) );
	}
}

function admin_notices(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && CPT === $screen->post_type ) {
		$blocked = get_transient( 'oria_ro_blocked_' . get_current_user_id() );
		if ( is_array( $blocked ) && $blocked ) {
			delete_transient( 'oria_ro_blocked_' . get_current_user_id() );
			echo '<div class="notice notice-warning"><p><b>' . esc_html__( 'Saved as a draft, not published.', 'oria' ) . '</b> ' . esc_html__( 'An Active offer needs:', 'oria' ) . ' ' . esc_html( implode( '; ', $blocked ) ) . '.</p></div>';
		}
		if ( ! settings()['enabled'] ) {
			printf( '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>', esc_html__( 'Retreat recommendations are switched off, so nothing is shown on the site.', 'oria' ), esc_url( admin_url( 'edit.php?post_type=' . CPT . '&page=oria-retreat-settings' ) ), esc_html__( 'Settings', 'oria' ) );
		}
		// Researched offers waiting only on the link from the BookRetreats link builder.
		$nolink = get_posts( array( 'post_type' => CPT, 'post_status' => array( 'draft', 'publish', 'pending' ), 'posts_per_page' => 50, 'fields' => 'ids' ) );
		$nolink = array_filter( $nolink, static fn( int $id ): bool => '' === get( $id, 'aff_url' ) );
		if ( $nolink ) {
			echo '<div class="notice notice-info"><p>' . esc_html( sprintf( _n( '%d retreat offer is waiting for its affiliate link. Create the link for its retreat page in your BookRetreats dashboard (Link Builder), paste it into the offer, set it to Active and publish.', '%d retreat offers are waiting for their affiliate links. Create the link for each retreat page in your BookRetreats dashboard (Link Builder), paste it into the offer, set it to Active and publish.', count( $nolink ), 'oria' ), count( $nolink ) ) ) . '</p></div>';
		}

		// Offers someone meant to show but cannot be: the setup list.
		$waiting = get_posts( array( 'post_type' => CPT, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 50, 'fields' => 'ids', 'meta_key' => '_ro_state', 'meta_value' => 'active' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$bad     = array_filter( $waiting, static fn( int $id ): bool => ! eligible( $id ) );
		if ( $bad ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( _n( '%d offer is marked Active but is not showing yet (missing a link, image, summary, source or date, or its dates have passed).', '%d offers are marked Active but are not showing yet (missing a link, image, summary, source or date, or their dates have passed).', count( $bad ), 'oria' ), count( $bad ) ) ) . '</p></div>';
		}
	}
}

function columns( array $cols ): array {
	return array(
		'cb'          => $cols['cb'] ?? '',
		'title'       => __( 'Offer', 'oria' ),
		'ro_dest'     => __( 'Destination', 'oria' ),
		'ro_state'    => __( 'Status', 'oria' ),
		'ro_price'    => __( 'Price check', 'oria' ),
		'ro_clicks'   => __( 'Outbound affiliate clicks (30 days)', 'oria' ),
		'date'        => __( 'Date', 'oria' ),
	);
}

function column_content( string $col, int $id ): void {
	switch ( $col ) {
		case 'ro_dest':
			echo esc_html( place_label( $id ) ?: '—' );
			break;
		case 'ro_state':
			$s = STATES[ get( $id, 'state' ) ] ?? 'Draft';
			echo esc_html( $s );
			echo eligible( $id ) ? ' <span style="color:#1a7f37">· ' . esc_html__( 'showing', 'oria' ) . '</span>' : ( 'active' === get( $id, 'state' ) ? ' <span style="color:#b32d2e">· ' . esc_html__( 'not showing', 'oria' ) . '</span>' : '' );
			break;
		case 'ro_price':
			if ( 'from' !== get( $id, 'price_mode' ) ) {
				esc_html_e( 'Shows "Check current price"', 'oria' );
			} else {
				echo price_fresh( $id ) ? esc_html( get( $id, 'price_checked' ) ) : '<b style="color:#b32d2e">' . esc_html__( 'Needs review', 'oria' ) . '</b>';
			}
			break;
		case 'ro_clicks':
			echo (int) clicks( $id, 30 );
			break;
	}
}

/* ------------------------------------------------------------ measurement */

/**
 * Outbound affiliate clicks, counted first-party per offer per day. A
 * click is a press of the link -- not a person, not a booking. Logged-in
 * users (Dale testing) and obvious bots are not counted; the same browser
 * pressing the same card within a minute counts once.
 */
function rest(): void {
	register_rest_route(
		'oria/v1',
		'/retreat-click',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id'        => array( 'type' => 'integer', 'required' => true ),
				'placement' => array( 'type' => 'string', 'required' => false ),
			),
			'callback'            => __NAMESPACE__ . '\record_click',
		)
	);
}

function record_click( \WP_REST_Request $req ): \WP_REST_Response {
	$id = (int) $req->get_param( 'id' );
	if ( ! eligible( $id ) || is_user_logged_in() ) {
		return new \WP_REST_Response( array( 'ok' => false ), 200 );
	}
	$ua = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|monitor/', $ua ) ) {
		return new \WP_REST_Response( array( 'ok' => false ), 200 );
	}
	$key = 'oria_ro_c_' . md5( wp_salt( 'nonce' ) . $id . (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . $ua );
	if ( get_transient( $key ) ) {
		return new \WP_REST_Response( array( 'ok' => true, 'dedup' => true ), 200 );
	}
	set_transient( $key, 1, MINUTE_IN_SECONDS );
	$b     = get_post_meta( $id, '_ro_clicks', true );
	$b     = is_array( $b ) ? $b : array();
	$day   = today();
	$b[ $day ] = (int) ( $b[ $day ] ?? 0 ) + 1;
	ksort( $b );
	$b = array_slice( $b, -120, null, true );
	update_post_meta( $id, '_ro_clicks', $b );
	return new \WP_REST_Response( array( 'ok' => true ), 200 );
}

function clicks( int $id, int $days ): int {
	$b    = get_post_meta( $id, '_ro_clicks', true );
	$from = wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
	$n    = 0;
	foreach ( is_array( $b ) ? $b : array() as $d => $c ) {
		if ( $d >= $from ) {
			$n += (int) $c;
		}
	}
	return $n;
}
