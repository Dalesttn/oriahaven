<?php
/**
 * The experience: what this place offers, what a visit is like, and where
 * those details came from -- one panel where there used to be two sections.
 *
 * "What you'll find here" gave one activity half a wide grid, a cropped
 * thumbnail and ticks restating its own description; "Before your first
 * visit" put two numbers in two oversized cards and ended in a long
 * disclaimer. Now: the activity (or activities), a row of practical facts,
 * a strip of amenities, and the provenance in a disclosure whose summary
 * already says the part that matters.
 *
 * Three data rules, each learned from what the fields actually hold:
 *
 *   Group size is a BAND the practice picks ("Class sized (12+)"), not a
 *   headcount. It is shown as the band, never as "12+ people".
 *
 *   Session length and group size are recorded for the practice, not per
 *   activity. They appear once, never beside one activity as if they were
 *   its own.
 *
 *   Provenance says what is recorded. A research listing collected from
 *   organisers' pages is not "checked by hand" unless an editor reviewed
 *   it; "confirmed by the practice" needs a stored confirmation date, not
 *   merely a claim.
 *
 * Args:
 *   id, sec          listing id, section number
 *   activities       [{label, url, note, traits[]}] -- services with a page
 *   also             string[] -- services without one
 *   minutes          int
 *   group            one-to-one | small | class | solo | ''
 *   extra            [[label, value]] -- what to bring, booking, next session
 *   amenities        Amenities\for_listing() groups
 *   status, verified claim status, verified_at
 *   is_classes       bool
 *   city             string
 *   icon             category slug for the title icon
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$ex_id    = (int) ( $args['id'] ?? 0 );
$ex_sec   = (int) ( $args['sec'] ?? 0 );
$ex_acts  = array_values( (array) ( $args['activities'] ?? array() ) );
$ex_also  = array_values( array_filter( array_map( 'strval', (array) ( $args['also'] ?? array() ) ) ) );
$ex_mins  = (int) ( $args['minutes'] ?? 0 );
$ex_group = (string) ( $args['group'] ?? '' );
$ex_extra = array_values( (array) ( $args['extra'] ?? array() ) );
$ex_city  = (string) ( $args['city'] ?? __( 'Perth', 'oria' ) );

$ex_bands = array(
	'one-to-one' => array( __( 'One to one', 'oria' ), '', false ),
	'small'      => array( __( 'Small groups', 'oria' ), __( 'under 12', 'oria' ), true ),
	'class'      => array( __( 'Class sized', 'oria' ), __( '12 or more', 'oria' ), true ),
	'solo'       => array( __( 'On your own', 'oria' ), __( 'a room or a machine', 'oria' ), false ),
);
$ex_band = $ex_bands[ $ex_group ] ?? null;

// Every amenity once, in its group's order: a strip, not a set of headed lists.
$ex_amen = array();
foreach ( (array) ( $args['amenities'] ?? array() ) as $ex_grp ) {
	foreach ( (array) ( $ex_grp['items'] ?? array() ) as $ex_it ) {
		$ex_amen[ (string) $ex_it['slug'] ] = array( 'label' => (string) $ex_it['label'], 'icon' => 'amenity' );
	}
}

/*
 * Some practices list how you pay and how you get there among their
 * services -- "Weekly memberships", "Casual classes", "On-site parking".
 * Those are options, not activities: they join the strip in the practice's
 * own words, and only real services stay under "Also offered".
 */
$ex_optrules = array(
	// Whole phrases only: "Spa packages" or "Drop-in meditation class" are things to do, not ways to pay.
	'calendar' => '/^((weekly|monthly|annual|yoga|studio) )?memberships?$|\bunlimited\b/i',
	'ticket'   => '/^(casual (classes|visits?)|class pass(es)?|trial class(es)?|gift (vouchers?|cards?)|([a-z]+ )?day pass(es)?)$|\bintro(ductory)?\b.*\b(pass|pack|offer)\b/i',
	'car'      => '/\b(parking|car park)\b/i',
);
$ex_rest = array();
foreach ( $ex_also as $ex_a ) {
	$ex_kind = '';
	foreach ( $ex_optrules as $ex_k => $ex_re ) {
		if ( preg_match( $ex_re, $ex_a ) ) {
			$ex_kind = $ex_k;
			break;
		}
	}
	if ( '' === $ex_kind ) {
		$ex_rest[] = $ex_a;
	} else {
		$ex_amen[ 'opt-' . sanitize_title( $ex_a ) ] = array( 'label' => $ex_a, 'icon' => $ex_kind );
	}
}
$ex_also = $ex_rest;

if ( ! $ex_acts && ! $ex_also && ! $ex_mins && ! $ex_band && ! $ex_extra && ! $ex_amen ) {
	return;
}

/* ---- where it came from ------------------------------------------------ */
$ex_status   = (string) ( $args['status'] ?? '' );
$ex_verified = (string) ( $args['verified'] ?? '' );
$ex_batch    = function_exists( '\Oria\Core\Sources\joining' ) ? (string) get_post_meta( $ex_id, \Oria\Core\Sources\BATCH, true ) : '';
if ( '' !== $ex_verified && 'unclaimed' !== $ex_status ) {
	/* translators: %s: date */
	$ex_src_sum  = sprintf( __( 'Confirmed by the practice · %s', 'oria' ), mysql2date( 'j F Y', $ex_verified ) );
	$ex_src_body = __( 'The practice manages this listing and confirmed these details on the date shown. Session details can still change — check with them if it matters.', 'oria' );
} elseif ( 'unclaimed' !== $ex_status ) {
	$ex_src_sum  = __( 'Managed by the practice', 'oria' );
	$ex_src_body = __( 'The practice has claimed this listing and can edit it. No confirmation date is recorded, so check current details with them before you go.', 'oria' );
} elseif ( '' !== $ex_batch ) {
	$ex_state    = (string) get_post_meta( $ex_id, \Oria\Core\Sources\REVIEW, true );
	$ex_when     = (string) get_post_meta( $ex_id, \Oria\Core\Sources\CHECKED, true );
	$ex_date     = '' !== $ex_when ? wp_date( 'j F Y', (int) strtotime( $ex_when ) ) : '';
	$ex_src_sum  = __( 'From public sources · not yet confirmed by the organiser', 'oria' );
	$ex_src_body = 'reviewed' === $ex_state
		/* translators: %s: date */
		? sprintf( __( 'Collected from the organiser’s public pages%s and reviewed by an Oria Haven editor. Check current details with them before you go.', 'oria' ), '' !== $ex_date ? ' ' . sprintf( __( 'on %s', 'oria' ), $ex_date ) : '' )
		/* translators: %s: date */
		: sprintf( __( 'Collected from the organiser’s public pages%s and not yet individually reviewed. Check current details with them before you go.', 'oria' ), '' !== $ex_date ? ' ' . sprintf( __( 'on %s', 'oria' ), $ex_date ) : '' );
} else {
	$ex_src_sum  = __( 'From public sources · not yet confirmed by the practice', 'oria' );
	$ex_src_body = __( 'Gathered from public sources such as the practice’s website and Google listing, and checked by hand when it was listed. Check current session details with the practice before you go.', 'oria' );
}

$ex_one   = 1 === count( $ex_acts );
$ex_title = $ex_one
	? (string) $ex_acts[0]['label']
	: ( $ex_acts ? ( ! empty( $args['is_classes'] ) ? __( 'Classes you’ll find here', 'oria' ) : __( 'What you can do here', 'oria' ) ) : __( 'Before your first visit', 'oria' ) );
/*
 * The category illustration drawn for this tile (assets/img/cat-icons/,
 * Recraft vector icons in the site palette: deep green line, sage accent,
 * the tile's own #EAF0E5 ground). A sub-category without one borrows its
 * parent's; with neither, the outline glyph the plugin already carries.
 */
$ex_icon     = '';
$ex_icon_url = '';
$ex_slug     = sanitize_key( (string) ( $args['icon'] ?? '' ) );
if ( '' !== $ex_slug ) {
	$ex_try = array( $ex_slug );
	$ex_t   = get_term_by( 'slug', $ex_slug, 'practice' );
	if ( $ex_t instanceof \WP_Term && $ex_t->parent ) {
		$ex_p = get_term( (int) $ex_t->parent, 'practice' );
		if ( $ex_p instanceof \WP_Term ) {
			$ex_try[] = $ex_p->slug;
		}
	}
	foreach ( $ex_try as $ex_s ) {
		$ex_rel = 'assets/img/cat-icons/' . $ex_s . '.svg';
		if ( is_readable( get_template_directory() . '/' . $ex_rel ) ) {
			$ex_icon_url = get_template_directory_uri() . '/' . $ex_rel;
			break;
		}
	}
	if ( '' === $ex_icon_url && function_exists( '\Oria\Core\Categories\glyph' ) ) {
		$ex_icon = \Oria\Core\Categories\glyph( $ex_slug );
	}
}

$ex_ico = static function ( string $name ): string {
	$p = array(
		'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'users' => '<circle cx="9" cy="8.5" r="2.8"/><path d="M3.8 19a5.2 5.2 0 0 1 10.4 0"/><circle cx="16.8" cy="9.5" r="2.2"/><path d="M15.5 14.2a4.3 4.3 0 0 1 4.7 4.8"/>',
	);
	return '<svg class="ov-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
};
$ex_opt_ico = static function ( string $name ): string {
	$p = array(
		'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'ticket'   => '<path d="M3.5 8.5V6h17v2.5a2.5 2.5 0 0 0 0 5V16h-17v-2.5a2.5 2.5 0 0 0 0-5Z"/><path d="M13 6v10" stroke-dasharray="2 2"/>',
		'car'      => '<path d="M5 16.5V12l1.8-4.5h10.4L19 12v4.5"/><path d="M4 12h16v4.5H4Z"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
};

/* translators: 1: activity, 2: city */
// "Reformer Pilates" -> "reformer Pilates"; a brand like "iKOU" is left alone.
$ex_more = static fn( string $label ): string => sprintf( __( 'Explore more %1$s in %2$s', 'oria' ), preg_match( '/^[A-Z][a-z]/', $label ) ? lcfirst( $label ) : $label, $ex_city );
?>
<section class="xp-sec oh-experience" id="services" aria-labelledby="xp-s<?php echo (int) $ex_sec; ?>">
	<div class="ov-shell">
		<div class="ov-main">
			<p class="ov-eyebrow"><?php esc_html_e( 'The experience', 'oria' ); ?></p>
			<div class="ov-title">
				<h2 class="ov-h" id="xp-s<?php echo (int) $ex_sec; ?>"><?php echo esc_html( $ex_title ); ?></h2>
				<?php if ( '' !== $ex_icon_url ) : ?>
					<span class="ov-icon ov-icon--art" aria-hidden="true"><img src="<?php echo esc_url( $ex_icon_url ); ?>" alt="" width="48" height="48" decoding="async"></span>
				<?php elseif ( '' !== $ex_icon ) : ?>
					<span class="ov-icon" aria-hidden="true"><?php echo $ex_icon; // phpcs:ignore WordPress.Security.EscapeOutput -- plugin-bundled SVG ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $ex_one ) : ?>
				<?php if ( '' !== (string) $ex_acts[0]['note'] ) : ?>
					<p class="ov-description"><?php echo esc_html( (string) $ex_acts[0]['note'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $ex_acts[0]['traits'] ) ) : ?>
					<p class="ov-traits"><?php echo esc_html( implode( ' · ', (array) $ex_acts[0]['traits'] ) ); ?></p>
				<?php endif; ?>
			<?php elseif ( $ex_acts ) : ?>
				<ul class="ov-acts">
					<?php foreach ( $ex_acts as $ex_a ) : ?>
						<li class="ov-act">
							<h3 class="ov-act__name"><?php echo esc_html( (string) $ex_a['label'] ); ?></h3>
							<?php if ( '' !== (string) $ex_a['note'] ) : ?>
								<p class="ov-act__note"><?php echo esc_html( (string) $ex_a['note'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $ex_a['traits'] ) ) : ?>
								<p class="ov-traits"><?php echo esc_html( implode( ' · ', (array) $ex_a['traits'] ) ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== (string) $ex_a['url'] ) : ?>
								<a class="ov-related" href="<?php echo esc_url( (string) $ex_a['url'] ); ?>"><?php echo esc_html( $ex_more( (string) $ex_a['label'] ) ); ?> <span aria-hidden="true">&rarr;</span></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $ex_also ) : ?>
				<p class="ov-also"><span class="ov-also__k"><?php esc_html_e( 'Also offered:', 'oria' ); ?></span> <?php echo esc_html( implode( ', ', $ex_also ) ); ?></p>
			<?php endif; ?>

			<?php if ( $ex_mins > 0 || $ex_band ) : ?>
				<dl class="ov-facts" id="first-visit">
					<?php if ( $ex_mins > 0 ) : ?>
						<div class="ov-fact">
							<dt class="ov-label"><?php echo $ex_ico( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Typical session', 'oria' ); ?></dt>
							<dd class="ov-value"><?php echo esc_html( number_format_i18n( $ex_mins ) ); ?> <span class="ov-unit"><?php echo esc_html( _n( 'minute', 'minutes', $ex_mins, 'oria' ) ); ?></span></dd>
						</div>
					<?php endif; ?>
					<?php if ( $ex_band ) : ?>
						<div class="ov-fact">
							<dt class="ov-label"><?php echo $ex_ico( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Group size', 'oria' ); ?></dt>
							<dd class="ov-value"><?php echo esc_html( $ex_band[0] ); ?><?php if ( '' !== $ex_band[1] ) : ?> <span class="ov-unit">(<?php echo esc_html( $ex_band[1] ); ?>)</span><?php endif; ?></dd>
							<?php if ( $ex_band[2] ) : ?>
								<dd class="ov-note"><?php esc_html_e( 'The band the practice lists, not a set class size', 'oria' ); ?></dd>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</dl>
			<?php endif; ?>

			<?php if ( $ex_extra ) : ?>
				<dl class="ov-more"<?php echo ( $ex_mins > 0 || $ex_band ) ? '' : ' id="first-visit"'; ?>>
					<?php foreach ( $ex_extra as $ex_x ) : ?>
						<div class="ov-more__row">
							<dt><?php echo esc_html( (string) $ex_x[0] ); ?></dt>
							<dd><?php echo esc_html( (string) $ex_x[1] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>

		<?php if ( $ex_amen ) : ?>
			<ul class="ov-options" id="amenities" aria-label="<?php esc_attr_e( 'Amenities and options', 'oria' ); ?>">
				<?php foreach ( $ex_amen as $ex_slug => $ex_opt ) : ?>
					<li class="ov-option">
						<?php
						if ( 'amenity' === $ex_opt['icon'] ) {
							echo function_exists( '\Oria\Theme\amenity_icon' ) ? \Oria\Theme\amenity_icon( (string) $ex_slug ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built SVG
						} else {
							echo $ex_opt_ico( (string) $ex_opt['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup
						}
						?>
						<span><?php echo esc_html( (string) $ex_opt['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<details class="ov-source">
			<summary><?php echo esc_html( $ex_src_sum ); ?></summary>
			<p><?php echo esc_html( $ex_src_body ); ?></p>
		</details>
	</div>

	<?php if ( $ex_one && '' !== (string) $ex_acts[0]['url'] ) : ?>
		<p class="ov-after"><a class="ov-related" href="<?php echo esc_url( (string) $ex_acts[0]['url'] ); ?>"><?php echo esc_html( $ex_more( (string) $ex_acts[0]['label'] ) ); ?> <span aria-hidden="true">&rarr;</span></a></p>
	<?php endif; ?>
</section>
