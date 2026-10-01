<?php
/**
 * Work in Wellness: plans (brief sections 37, 75-76).
 *
 * Who may use which employer feature. At launch everything is free: the
 * "paywall" switch (Work in Wellness -> Settings) is off, and allows()
 * answers yes to any employer. The day it is switched on, the gate already
 * exists everywhere it needs to be, and each account's plan is whatever an
 * admin has set on it (Users -> profile -> "Work in Wellness plan") until a
 * checkout writes it instead.
 *
 * Nothing here takes a payment, and no plan or price changes by itself.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work\Plans;

use Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const META_PLAN  = '_oria_work_plan';
const META_UNTIL = '_oria_work_plan_until';
const PAYWALL    = 'oria_work_paywall';

/** plan => label. */
const PLANS = array(
	'free'    => 'Free',
	'cover'   => 'Oria Cover',
	'recruit' => 'Oria Recruit',
);

/** Feature => plans that include it once the paywall is on. */
const FEATURES = array(
	'search'    => array( 'recruit' ),           // Full practitioner search (radius, verified, experience).
	'talent'    => array( 'recruit' ),           // Saved talent lists, notes and status.
	'invite'    => array( 'recruit', 'cover' ),  // Invite a practitioner to a job or shift.
	'emergency' => array( 'recruit', 'cover' ),  // Find emergency cover.
	'analytics' => array( 'recruit' ),           // Per-job analytics beyond the counts.
);

function bootstrap(): void {
	add_action( 'show_user_profile', __NAMESPACE__ . '\user_field' );
	add_action( 'edit_user_profile', __NAMESPACE__ . '\user_field' );
	add_action( 'personal_options_update', __NAMESPACE__ . '\save_user_field' );
	add_action( 'edit_user_profile_update', __NAMESPACE__ . '\save_user_field' );
	add_action( 'admin_menu', __NAMESPACE__ . '\settings_menu', 20 );
	add_action( 'admin_post_oria_work_settings', __NAMESPACE__ . '\save_settings' );
}

function paywall_on(): bool {
	return (bool) get_option( PAYWALL, false );
}

/** The account's current plan ('free' when none or lapsed). */
function plan( int $user_id ): string {
	$p     = (string) get_user_meta( $user_id, META_PLAN, true );
	$until = (int) get_user_meta( $user_id, META_UNTIL, true );
	if ( ! isset( PLANS[ $p ] ) || ( $until && $until < time() ) ) {
		return 'free';
	}
	return $p;
}

/**
 * May this account use $feature? Admins always; employers always while the
 * paywall is off; otherwise by plan.
 */
function allows( int $user_id, string $feature ): bool {
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	if ( ! Work\is_employer( $user_id ) ) {
		return false;
	}
	if ( ! paywall_on() ) {
		return true;
	}
	return in_array( plan( $user_id ), FEATURES[ $feature ] ?? array(), true );
}

/** One line for the dashboard: what the account is on. */
function summary( int $user_id ): string {
	if ( ! paywall_on() ) {
		return __( 'Launch: every feature is free while we launch.', 'oria' );
	}
	$p     = plan( $user_id );
	$until = (int) get_user_meta( $user_id, META_UNTIL, true );
	/* translators: 1: plan, 2: date */
	return $until && 'free' !== $p ? sprintf( __( '%1$s, until %2$s.', 'oria' ), PLANS[ $p ], wp_date( 'j M Y', $until ) ) : PLANS[ $p ];
}

/* --------------------------------------------------------------- admin */

function user_field( \WP_User $user ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$p     = (string) get_user_meta( $user->ID, META_PLAN, true ) ?: 'free';
	$until = (int) get_user_meta( $user->ID, META_UNTIL, true );
	wp_nonce_field( 'oria_work_plan', '_wk_plan' );
	?>
	<h2><?php esc_html_e( 'Work in Wellness plan', 'oria' ); ?></h2>
	<table class="form-table"><tr>
		<th><label for="wk-plan"><?php esc_html_e( 'Plan', 'oria' ); ?></label></th>
		<td>
			<select id="wk-plan" name="wk_plan"><?php foreach ( PLANS as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p, $k ); ?>><?php echo esc_html( $v ); ?></option><?php endforeach; ?></select>
			<label><?php esc_html_e( 'until', 'oria' ); ?> <input type="date" name="wk_plan_until" value="<?php echo esc_attr( $until ? wp_date( 'Y-m-d', $until ) : '' ); ?>"></label>
			<p class="description"><?php echo esc_html( paywall_on() ? __( 'The paywall is ON: this plan decides which employer features this account can use.', 'oria' ) : __( 'The paywall is off, so every employer has every feature. This plan takes effect when it is switched on.', 'oria' ) ); ?></p>
		</td>
	</tr></table>
	<?php
}

function save_user_field( int $user_id ): void {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wk_plan'] ) || ! wp_verify_nonce( (string) $_POST['_wk_plan'], 'oria_work_plan' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$p = sanitize_key( (string) ( $_POST['wk_plan'] ?? 'free' ) );
	update_user_meta( $user_id, META_PLAN, isset( PLANS[ $p ] ) ? $p : 'free' );
	$d = sanitize_text_field( (string) ( $_POST['wk_plan_until'] ?? '' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
		update_user_meta( $user_id, META_UNTIL, ( date_create_immutable( $d . ' 23:59', wp_timezone() ) ?: new \DateTimeImmutable() )->getTimestamp() );
	} else {
		delete_user_meta( $user_id, META_UNTIL );
	}
}

function settings_menu(): void {
	add_submenu_page( 'oria-work', __( 'Work settings', 'oria' ), __( 'Settings', 'oria' ), 'manage_options', 'oria-work-settings', __NAMESPACE__ . '\settings_page' );
}

function settings_page(): void {
	$prices = Work\prices();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Work in Wellness settings', 'oria' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'oria' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_work_settings">
			<?php wp_nonce_field( 'oria_work_settings' ); ?>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'Paywall', 'oria' ); ?></th><td>
					<label><input type="checkbox" name="paywall" value="1" <?php checked( paywall_on() ); ?>> <?php esc_html_e( 'Limit employer features to paid plans', 'oria' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave this off during launch. When on, practitioner search, saved talent, invites, emergency cover and job analytics follow each account\'s plan (set on the user\'s profile). There is no checkout yet: plans are set by hand.', 'oria' ); ?></p>
				</td></tr>
				<tr><th><?php esc_html_e( 'Planned prices', 'oria' ); ?></th><td>
					<p class="description"><?php esc_html_e( 'Shown on /for-business/recruitment/ as "Coming". Change them in code via the oria_work_prices filter.', 'oria' ); ?></p>
					<ul><?php foreach ( $prices as $k => $v ) : ?><li><code><?php echo esc_html( $k ); ?></code> — $<?php echo (int) $v; ?></li><?php endforeach; ?></ul>
				</td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function save_settings(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'oria_work_settings' );
	update_option( PAYWALL, empty( $_POST['paywall'] ) ? 0 : 1, false );
	wp_safe_redirect( admin_url( 'admin.php?page=oria-work-settings&saved=1' ) );
	exit;
}
