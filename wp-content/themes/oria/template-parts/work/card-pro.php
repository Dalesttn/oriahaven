<?php
/**
 * A practitioner card (brief section 36): photo, name, profession, place,
 * experience, skills, availability, verified badges. Nothing private.
 *
 * $args: id
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_id     = (int) ( $args['id'] ?? 0 );
$oria_skills = wp_get_post_terms( $oria_id, Work\SKILL, array( 'fields' => 'names' ) );
$oria_for    = (array) Work\meta( $oria_id, 'available_for', array() );
$oria_when   = (array) Work\meta( $oria_id, 'availability', array() );
$oria_years  = (int) Work\meta( $oria_id, 'years', 0 );
$oria_badges = Work\badges( $oria_id );
$oria_avail  = in_array( 'not', $oria_when, true ) ? '' : ( in_array( 'cover', $oria_for, true ) ? __( 'Available for casual cover', 'oria' ) : ( in_array( 'now', $oria_when, true ) ? __( 'Available now', 'oria' ) : ( in_array( 'open', $oria_when, true ) ? __( 'Open to opportunities', 'oria' ) : '' ) ) );
// An availability post is the more useful line: when, specifically (brief section 46).
if ( '' !== ( $oria_post = Work\avail_label( $oria_id ) ) ) {
	/* translators: %s: days and dates */
	$oria_avail = sprintf( __( 'Available %s', 'oria' ), $oria_post );
}
$oria_km   = $args['km'] ?? null;
$oria_rate = (string) Work\meta( $oria_id, 'rate' );
?>
<a class="wkpro" href="<?php echo esc_url( (string) get_permalink( $oria_id ) ); ?>" data-wk-event="practitioner_card_click">
	<span class="wkpro__photo">
		<?php if ( has_post_thumbnail( $oria_id ) ) : ?>
			<?php echo get_the_post_thumbnail( $oria_id, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		<?php else : ?>
			<span aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( get_the_title( $oria_id ), 0, 1 ) ) ); ?></span>
		<?php endif; ?>
	</span>
	<span class="wkpro__body">
		<span class="wkcard__title"><?php echo esc_html( get_the_title( $oria_id ) ); ?></span>
		<span class="wkcard__org"><?php echo esc_html( implode( ' · ', array_filter( array( (string) Work\meta( $oria_id, 'title' ) ?: Work\profession_name( $oria_id ), Work\place_label( $oria_id ), null !== $oria_km ? sprintf( __( '%s km away', 'oria' ), number_format_i18n( (float) $oria_km, $oria_km < 10 ? 1 : 0 ) ) : '' ) ) ) ); ?></span>
		<?php if ( '' !== $oria_rate ) : ?><span class="wkpro__meta"><?php echo esc_html( $oria_rate ); ?></span><?php endif; ?>
		<?php if ( $oria_years ) : ?>
			<span class="wkpro__meta"><?php echo esc_html( sprintf( _n( '%d year experience', '%d years experience', $oria_years, 'oria' ), $oria_years ) ); ?></span>
		<?php endif; ?>
		<?php if ( $oria_skills ) : ?>
			<span class="wkpro__skills"><?php echo esc_html( implode( ' • ', array_slice( $oria_skills, 0, 4 ) ) ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $oria_avail ) : ?>
			<span class="wkavail"><span class="wkavail__dot" aria-hidden="true"></span><?php echo esc_html( $oria_avail ); ?></span>
		<?php endif; ?>
		<?php if ( $oria_badges ) : ?>
			<span class="wkpro__badges">
				<?php foreach ( array_slice( $oria_badges, 0, 3 ) as $oria_b ) : ?>
					<span class="wkverified"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.4 6.6 11.3 12.5 4.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><?php echo esc_html( $oria_b ); ?></span>
				<?php endforeach; ?>
			</span>
		<?php endif; ?>
		<span class="wkcard__cta"><?php esc_html_e( 'View profile', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></span>
	</span>
</a>
