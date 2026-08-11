<?php
/**
 * The "get matched" form itself — used twice on the front page: inside
 * the mobile band (sections/get-matched.php) and inside the desktop
 * dialog. One source so the two can never drift apart. No element IDs
 * in here, because both copies are in the DOM at once.
 *
 * Optional $args prefill the two pickers: 'service', 'service_slug',
 * 'area', 'area_slug'. The Wellness Finder passes what the visitor has
 * just told it, because asking someone the same question twice in one
 * page is the fastest way to lose them.
 */

declare(strict_types=1);

if ( ! function_exists( '\Oria\Core\Leads\bootstrap' ) ) {
	return;
}

$oria_pre_service      = (string) ( $args['service'] ?? '' );
$oria_pre_service_slug = (string) ( $args['service_slug'] ?? '' );
$oria_pre_area         = (string) ( $args['area'] ?? '' );
$oria_pre_area_slug    = (string) ( $args['area_slug'] ?? '' );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_state   = isset( $_GET['olead'] ) ? (string) $_GET['olead'] : '';
$oria_matched = isset( $_GET['omatched'] ) ? (int) $_GET['omatched'] : -1;
// phpcs:enable

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => true ) );
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
$oria_specs     = get_terms( array( 'taxonomy' => 'specialty', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 30 ) );
$oria_specs     = is_wp_error( $oria_specs ) ? array() : $oria_specs;
$oria_regions   = get_terms( array( 'taxonomy' => 'area', 'parent' => 0, 'hide_empty' => false ) );
$oria_regions   = is_wp_error( $oria_regions ) ? array() : $oria_regions;

if ( 'sent' === $oria_state ) : ?>
	<div class="notice" style="background:rgba(255,255,255,.95)">
		<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" style="width:20px;height:20px;flex:none"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>
		<span>
			<?php if ( $oria_matched > 0 ) : ?>
				<b><?php echo esc_html( sprintf( _n( 'Sent to %d matching practice.', 'Sent to %d matching practices.', $oria_matched, 'oria' ), $oria_matched ) ); ?></b>
				<?php esc_html_e( 'Check your email — we\'ve listed exactly who received it, and they\'ll reply to you directly.', 'oria' ); ?>
			<?php else : ?>
				<b><?php esc_html_e( 'Request received.', 'oria' ); ?></b>
				<?php esc_html_e( "We're finding you options by hand and will email you within a day.", 'oria' ); ?>
			<?php endif; ?>
		</span>
	</div>
	<?php return; ?>
<?php endif; ?>

<form class="stack" style="gap:.8rem" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-oria-event="match_started">
	<input type="hidden" name="action" value="oria_match">
	<input type="hidden" name="oform_ts" value="<?php echo esc_attr( (string) time() ); ?>">
	<?php wp_nonce_field( 'oria_match', 'oform_nonce' ); ?>
	<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

	<?php if ( 'error' === $oria_state ) : ?>
		<p style="font-size:.8125rem;color:#ffb4a2"><?php esc_html_e( 'That didn\'t send — check your name and email and try again.', 'oria' ); ?></p>
	<?php endif; ?>

	<?php
	/*
	 * The service and area pickers are comboboxes: type-ahead against the
	 * option list, because a plain select with 50 services and 90 suburbs
	 * is a wall. The visible input posts its text (match_*_text) so a
	 * no-JS submission still carries what was asked for — the server
	 * resolves names as well as slugs — and script fills the hidden slug
	 * when a suggestion is picked. Option data prints once per page even
	 * though the form renders twice (band + dialog).
	 */
	if ( ! defined( 'ORIA_MATCH_DATA_PRINTED' ) ) {
		define( 'ORIA_MATCH_DATA_PRINTED', true );
		$oria_service_opts = array();
		foreach ( $oria_practices as $oria_t ) {
			$oria_service_opts[] = array( 's' => $oria_t->slug, 'l' => \Oria\Theme\tname( $oria_t ), 'g' => __( 'Practice', 'oria' ) );
		}
		foreach ( $oria_specs as $oria_t ) {
			$oria_service_opts[] = array( 's' => $oria_t->slug, 'l' => \Oria\Theme\tname( $oria_t ), 'g' => __( 'Service', 'oria' ) );
		}
		$oria_area_opts = array();
		foreach ( $oria_regions as $oria_r ) {
			$oria_area_opts[] = array( 's' => $oria_r->slug, 'l' => sprintf( __( 'All of %s', 'oria' ), \Oria\Theme\tname( $oria_r ) ), 'g' => __( 'Region', 'oria' ) );
			$oria_subs      = get_terms( array( 'taxonomy' => 'area', 'parent' => $oria_r->term_id, 'hide_empty' => true, 'orderby' => 'name' ) );
			foreach ( is_wp_error( $oria_subs ) ? array() : $oria_subs as $oria_sub ) {
				$oria_area_opts[] = array( 's' => $oria_sub->slug, 'l' => \Oria\Theme\tname( $oria_sub ), 'g' => \Oria\Theme\tname( $oria_r ) );
			}
		}
		printf(
			'<script>window.ORIA_MATCH = %s;</script>',
			wp_json_encode( array( 'services' => $oria_service_opts, 'areas' => $oria_area_opts ) )
		);
	}
	?>
	<div class="grid matchband__pair">
		<label class="field" data-matchcombo="services"><span class="field__label"><?php esc_html_e( 'What would you like to try?', 'oria' ); ?></span>
			<input class="input" type="text" name="match_service_text" required autocomplete="off"
				value="<?php echo esc_attr( $oria_pre_service ); ?>"
				placeholder="<?php esc_attr_e( 'Start typing — yoga, reiki, massage…', 'oria' ); ?>"
				role="combobox" aria-autocomplete="list" aria-expanded="false">
			<input type="hidden" name="match_service" value="<?php echo esc_attr( $oria_pre_service_slug ); ?>">
			<span class="oform-lookup" data-matchcombo-panel hidden></span></label>
		<label class="field" data-matchcombo="areas"><span class="field__label"><?php esc_html_e( 'Where suits you?', 'oria' ); ?></span>
			<input class="input" type="text" name="match_area_text" autocomplete="off"
				value="<?php echo esc_attr( $oria_pre_area ); ?>"
				placeholder="<?php esc_attr_e( 'Anywhere in Perth', 'oria' ); ?>"
				role="combobox" aria-autocomplete="list" aria-expanded="false">
			<input type="hidden" name="match_area" value="<?php echo esc_attr( $oria_pre_area_slug ); ?>">
			<span class="oform-lookup" data-matchcombo-panel hidden></span></label>
	</div>

	<label class="field"><span class="field__label"><?php esc_html_e( 'When suits you?', 'oria' ); ?></span>
		<select class="select" name="match_timing">
			<option value="Flexible"><?php esc_html_e( "I'm flexible", 'oria' ); ?></option>
			<option value="Weekday daytime"><?php esc_html_e( 'Weekday daytime', 'oria' ); ?></option>
			<option value="Weekday evenings"><?php esc_html_e( 'Weekday evenings', 'oria' ); ?></option>
			<option value="Weekends"><?php esc_html_e( 'Weekends', 'oria' ); ?></option>
		</select></label>

	<div class="grid matchband__pair">
		<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
			<input class="input" type="text" name="lead_name" required></label>
		<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
			<input class="input" type="email" name="lead_email" required></label>
	</div>
	<label class="field"><span class="field__label"><?php esc_html_e( 'Phone', 'oria' ); ?> <span class="matchform__opt">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
		<input class="input" type="tel" name="lead_phone"></label>
	<label class="field"><span class="field__label"><?php esc_html_e( 'Anything practical?', 'oria' ); ?> <span class="matchform__opt">· <?php esc_html_e( 'optional', 'oria' ); ?></span></span>
		<textarea class="textarea" name="lead_notes" style="min-height:64px" maxlength="600" placeholder="<?php esc_attr_e( 'e.g. beginner, mornings only, near the train line — please don\'t include medical details', 'oria' ); ?>"></textarea></label>

	<button class="btn btn--light btn--block" type="submit"><?php esc_html_e( 'Match me with practices', 'oria' ); ?></button>
	<p class="hint matchform__hint">
		<?php esc_html_e( 'We share only these details, only with the matched practices, so they can reply to you. Your receipt lists exactly who received them.', 'oria' ); ?>
	</p>
</form>
