<?php
/**
 * What changed on a listing, who changed it, and when.
 *
 * Once a practice owns its own listing, the directory stops being the only
 * author of the page. That is the point of claiming -- but it means an
 * admin opening a listing can no longer tell whether the opening hours in
 * front of them are the ones we researched or the ones the owner corrected
 * last week, and there is no way to see that an owner logged in, changed
 * three things and has not been back since.
 *
 * So every human save is recorded: the fields that actually changed, a
 * short before and after for each, and whether it was the owner or us.
 *
 * Three deliberate limits.
 *
 * Only human saves. The importer, the Stripe webhook and every CLI tool
 * write through update_field() without a logged-in user, and a history
 * full of "Oria Haven changed 40 fields" on an import run would bury the
 * one line that matters. No current user, no entry.
 *
 * Only what changed. A save that touches nothing records nothing, so
 * opening a listing and pressing Update does not add noise.
 *
 * Summaries, not copies. A gallery records "3 photos", not three
 * attachment ids; a repeater records its row count. The history is meant
 * to be read at a glance and must never grow into a second copy of the
 * listing inside postmeta.
 */

declare(strict_types=1);

namespace Oria\Core\Audit;

use Oria\Core\PostTypes;
use Oria\Core\Tiers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the history lives. Underscored, so it stays out of custom fields. */
const META = '_oria_audit';

/** Entries kept per listing, newest first. Older ones fall off the end. */
const MAX = 100;

/** Longest before/after string stored for one field. */
const SNIP = 120;

function bootstrap(): void {
	// Priority 1 runs before ACF writes the posted values, so get_field()
	// still returns what was there; priority 20 runs after.
	add_action( 'acf/save_post', __NAMESPACE__ . '\before', 1 );
	add_action( 'acf/save_post', __NAMESPACE__ . '\after', 20 );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\box' );
}

/**
 * The fields worth watching: everything an owner can edit, plus the two
 * pieces of the post itself that are part of the listing rather than of
 * WordPress.
 *
 * @return array<int, string>
 */
function watched(): array {
	return array_merge( array_keys( Tiers\FIELD_TIERS ), array( 'post_title', 'post_excerpt' ) );
}

/** @param int|string $post_id */
function listing_id( $post_id ): int {
	if ( ! is_numeric( $post_id ) ) {
		return 0; // options pages, taxonomy terms, users
	}
	$id = (int) $post_id;
	return PostTypes\LISTING === get_post_type( $id ) ? $id : 0;
}

/**
 * Snapshot, or the reason not to bother.
 *
 * @param int|string $post_id
 */
function before( $post_id ): void {
	$id = listing_id( $post_id );
	if ( ! $id || ! get_current_user_id() ) {
		return;
	}
	$snaps = &snapshots();
	if ( isset( $snaps[ $id ] ) ) {
		return; // Re-entered: save_description() updates the post mid-save.
	}
	$snaps[ $id ] = read( $id );
}

/** @param int|string $post_id */
function after( $post_id ): void {
	$id   = listing_id( $post_id );
	$user = get_current_user_id();
	if ( ! $id || ! $user ) {
		return;
	}

	$snaps = &snapshots();
	if ( ! isset( $snaps[ $id ] ) ) {
		return;
	}
	$was = $snaps[ $id ];
	unset( $snaps[ $id ] );

	$now     = read( $id );
	$changes = array();
	foreach ( watched() as $name ) {
		$old = $was[ $name ] ?? null;
		$new = $now[ $name ] ?? null;
		if ( same( $old, $new ) ) {
			continue;
		}
		$changes[] = array(
			'field' => label( $name, $id ),
			'from'  => summarise( $old ),
			'to'    => summarise( $new ),
		);
	}

	if ( $changes ) {
		add_entry( $id, entry( $id, $user, '', $changes ) );
	}
}

/**
 * Record something that is not a field edit -- a claim approved, a payment,
 * a plan lapsing. Called by the modules that do those things, so the
 * history reads as one timeline rather than only the typing half of it.
 */
function note( int $listing_id, string $text, int $actor = 0 ): void {
	if ( $listing_id <= 0 || '' === trim( $text ) ) {
		return;
	}
	add_entry( $listing_id, entry( $listing_id, $actor, $text, array() ) );
}

/**
 * @param array<int, array{field: string, from: string, to: string}> $changes
 * @return array<string, mixed>
 */
function entry( int $listing_id, int $user_id, string $note, array $changes ): array {
	$who = $user_id ? get_userdata( $user_id ) : false;

	if ( ! $who instanceof \WP_User ) {
		$role = 'system';
		$name = __( 'Oria Haven', 'oria' );
	} elseif ( (int) get_post_meta( $listing_id, 'claimed_by', true ) === $user_id ) {
		// Owner first: an owner who is also an admin is still the owner
		// here, because the question this answers is whose words these are.
		$role = 'owner';
		$name = $who->display_name;
	} else {
		$role = user_can( $who, 'manage_options' ) ? 'staff' : 'other';
		$name = $who->display_name;
	}

	return array(
		'at'      => current_time( 'mysql' ),
		'user'    => $user_id,
		'name'    => $name,
		'role'    => $role,
		'note'    => $note,
		'changes' => $changes,
	);
}

/** @param array<string, mixed> $entry */
function add_entry( int $listing_id, array $entry ): void {
	$log = get_post_meta( $listing_id, META, true );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, $entry );
	update_post_meta( $listing_id, META, array_slice( $log, 0, MAX ) );
}

/* ------------------------------------------------------------- reading */

/**
 * The current value of every watched field.
 *
 * @return array<string, mixed>
 */
function read( int $id ): array {
	$out = array();
	foreach ( watched() as $name ) {
		if ( 'post_title' === $name || 'post_excerpt' === $name ) {
			$out[ $name ] = (string) get_post_field( $name, $id, 'raw' );
			continue;
		}
		$out[ $name ] = function_exists( 'get_field' ) ? get_field( $name, $id, false ) : get_post_meta( $id, $name, true );
	}
	return $out;
}

/**
 * Whether two values are the same as far as a reader is concerned.
 *
 * Compared as encoded structures rather than with ==, because ACF hands
 * back '' where it once handed back null, and 0 where it handed back '0'.
 * Without this every save would report half the form as changed.
 *
 * @param mixed $a
 * @param mixed $b
 */
function same( $a, $b ): bool {
	return canonical( $a ) === canonical( $b );
}

/** @param mixed $value */
function canonical( $value ): string {
	if ( null === $value || '' === $value || array() === $value ) {
		return '';
	}
	if ( is_scalar( $value ) ) {
		return trim( (string) $value );
	}
	return (string) wp_json_encode( $value );
}

/**
 * One value, in as few words as will still tell you what happened.
 *
 * @param mixed $value
 */
function summarise( $value ): string {
	if ( null === $value || '' === $value || array() === $value ) {
		return __( 'empty', 'oria' );
	}

	if ( is_bool( $value ) ) {
		return $value ? __( 'yes', 'oria' ) : __( 'no', 'oria' );
	}

	if ( is_array( $value ) ) {
		$count = count( $value );
		// A list of scalars is short enough to show; rows and galleries are
		// not, and their count is the useful part anyway.
		if ( $count && count( array_filter( $value, 'is_scalar' ) ) === $count ) {
			$text = implode( ', ', array_map( 'strval', $value ) );
			return snip( $text );
		}
		/* translators: %d: number of entries */
		return sprintf( _n( '%d entry', '%d entries', $count, 'oria' ), $count );
	}

	return snip( (string) $value );
}

function snip( string $text ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( '' === $text ) {
		return __( 'empty', 'oria' );
	}
	// mb_* is not guaranteed -- the local PHP has no mbstring -- and
	// cutting UTF-8 with substr() splits a multi-byte character into
	// mojibake. seems_utf8()/wp_html_excerpt() is WordPress's own answer.
	return strlen( $text ) > SNIP ? rtrim( wp_html_excerpt( $text, SNIP ) ) . '…' : $text;
}

/** The field's own label, so the history reads in the words of the form. */
function label( string $name, int $id ): string {
	if ( 'post_title' === $name ) {
		return __( 'Name', 'oria' );
	}
	if ( 'post_excerpt' === $name ) {
		return __( 'Description', 'oria' );
	}
	if ( function_exists( 'get_field_object' ) ) {
		$field = get_field_object( $name, $id, false, false );
		if ( is_array( $field ) && ! empty( $field['label'] ) ) {
			return (string) $field['label'];
		}
	}
	return ucfirst( str_replace( '_', ' ', $name ) );
}

/**
 * Per-request store of pre-save values.
 *
 * @return array<int, array<string, mixed>>
 */
function &snapshots(): array {
	static $snaps = array();
	return $snaps;
}

/* ------------------------------------------------------------- the panel */

function box(): void {
	// Admins only. An owner does not need to be told what they just typed,
	// and the panel names them by role, which is our bookkeeping, not theirs.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box(
		'oria_audit',
		__( 'Edit history', 'oria' ),
		__NAMESPACE__ . '\render',
		PostTypes\LISTING,
		'normal',
		'low'
	);
}

function render( \WP_Post $post ): void {
	$log = get_post_meta( $post->ID, META, true );
	$log = is_array( $log ) ? $log : array();

	$owner = (int) get_post_meta( $post->ID, 'claimed_by', true );
	$who   = $owner ? get_userdata( $owner ) : false;

	echo '<style>
	.oria-audit{margin:-6px 0 0}
	.oria-audit__owner{margin:0 0 14px;padding:10px 12px;background:#f6f7f7;border-left:3px solid #c3c4c7;font-size:13px}
	.oria-audit__e{padding:10px 0;border-top:1px solid #f0f0f1}
	.oria-audit__e:first-of-type{border-top:0}
	.oria-audit__h{font-size:12px;color:#646970;margin:0 0 6px}
	.oria-audit__w{font-weight:600;color:#1d2327}
	.oria-audit__r{display:inline-block;margin-left:4px;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
	.oria-audit__r--owner{background:#edf7f0;color:#1a7a3f}
	.oria-audit__r--staff{background:#f0f0f1;color:#50575e}
	.oria-audit__r--system{background:#f0f0f1;color:#787c82}
	.oria-audit__r--other{background:#fcf0f1;color:#8a2424}
	.oria-audit table{width:100%;border-collapse:collapse;font-size:13px}
	.oria-audit td{padding:3px 10px 3px 0;vertical-align:top}
	.oria-audit td:first-child{width:170px;color:#646970}
	.oria-audit__from{color:#8a8f94;text-decoration:line-through}
	.oria-audit__note{font-size:13px;color:#1d2327}
	</style>';

	echo '<div class="oria-audit">';

	printf(
		'<p class="oria-audit__owner">%s</p>',
		$who instanceof \WP_User
			? sprintf(
				/* translators: 1: owner name, 2: email, 3: plan name */
				esc_html__( 'Owned by %1$s (%2$s). Plan: %3$s. Anything below marked "owner" is their wording, not ours.', 'oria' ),
				esc_html( $who->display_name ),
				esc_html( $who->user_email ),
				esc_html( \Oria\Core\Claims\STATUSES[ \Oria\Core\Claims\badge( $post->ID ) ] )
			)
			: esc_html__( 'Nobody owns this listing yet, so everything on the page is ours.', 'oria' )
	);

	if ( ! $log ) {
		echo '<p class="description">' . esc_html__( 'Nothing recorded yet. Saves made by a logged-in person are listed here from now on; imports and automated updates are not.', 'oria' ) . '</p></div>';
		return;
	}

	$roles = array(
		'owner'  => __( 'owner', 'oria' ),
		'staff'  => __( 'staff', 'oria' ),
		'system' => __( 'system', 'oria' ),
		'other'  => __( 'not the owner', 'oria' ),
	);

	foreach ( $log as $e ) {
		$role = (string) ( $e['role'] ?? 'system' );
		echo '<div class="oria-audit__e">';
		printf(
			'<p class="oria-audit__h"><span class="oria-audit__w">%s</span><span class="oria-audit__r oria-audit__r--%s">%s</span> &middot; %s</p>',
			esc_html( (string) ( $e['name'] ?? '' ) ),
			esc_attr( $role ),
			esc_html( $roles[ $role ] ?? $role ),
			esc_html( mysql2date( 'j M Y, g:ia', (string) ( $e['at'] ?? '' ) ) )
		);

		if ( ! empty( $e['note'] ) ) {
			printf( '<p class="oria-audit__note">%s</p>', esc_html( (string) $e['note'] ) );
		}

		if ( ! empty( $e['changes'] ) && is_array( $e['changes'] ) ) {
			echo '<table>';
			foreach ( $e['changes'] as $c ) {
				printf(
					'<tr><td>%s</td><td><span class="oria-audit__from">%s</span> &rarr; %s</td></tr>',
					esc_html( (string) ( $c['field'] ?? '' ) ),
					esc_html( (string) ( $c['from'] ?? '' ) ),
					esc_html( (string) ( $c['to'] ?? '' ) )
				);
			}
			echo '</table>';
		}
		echo '</div>';
	}

	echo '</div>';
}
