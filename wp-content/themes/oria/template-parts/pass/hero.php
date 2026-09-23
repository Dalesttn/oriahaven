<?php
/**
 * Oria Pass hero.
 *
 * Two columns on desktop: the offer on the left, a stack of experience
 * cards on the right. The cards are the argument -- "one membership for
 * wellness across Perth" means nothing until somebody sees a Yin class in
 * Fremantle for eight credits.
 *
 * They are marked as examples, in the markup and in the copy, because in
 * this phase they are. Nothing here is bookable.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_live = ! empty( $args['live'] );
$oria_cta  = (string) ( $args['cta'] ?? __( 'Join the waitlist', 'oria' ) );

/* Illustrative only, and said so. Real inventory replaces this in Phase 3. */
$oria_cards = array(
	array( 'Yin Yoga', 'Fremantle', __( 'Tomorrow · 6:00pm', 'oria' ), 8 ),
	array( 'Infrared Sauna', 'Subiaco', __( 'Thursday · 5:30pm', 'oria' ), 14 ),
	array( 'Sound Bath', 'Scarborough', __( 'Sunday · 4:00pm', 'oria' ), 16 ),
);
?>

<header class="pass-hero">
	<div class="wrap pass-hero__in">

		<div class="pass-hero__say">
			<p class="micro pass-hero__eyebrow"><?php esc_html_e( 'Oria Pass', 'oria' ); ?></p>
			<h1 class="pass-hero__title"><?php esc_html_e( 'Try something good for you.', 'oria' ); ?></h1>
			<p class="pass-hero__lede">
				<?php
				printf(
					/* translators: %s: city */
					esc_html__( 'One flexible membership to discover yoga, Pilates, sauna, breathwork, sound healing, recovery and more across %s.', 'oria' ),
					esc_html( (string) ( $args['city'] ?? 'Perth' ) )
				);
				?>
			</p>

			<p class="pass-hero__acts">
				<a class="btn btn--light pass-hero__go" href="#pass-join" data-oria-event="oria_pass_join_click"><?php echo esc_html( $oria_cta ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
				<a class="pass-hero__alt" href="#pass-experiences"><?php esc_html_e( 'Explore experiences', 'oria' ); ?></a>
			</p>

			<?php if ( $oria_live ) : ?>
				<p class="pass-hero__price">
					<?php
					printf(
						/* translators: 1: price, 2: period */
						esc_html__( 'From %1$s a %2$s · Cancel anytime', 'oria' ),
						esc_html( (string) $args['price'] ),
						esc_html( (string) $args['period'] )
					);
					?>
				</p>
			<?php else : ?>
				<p class="pass-hero__price">
					<?php esc_html_e( 'Not open yet — we are signing up the first Perth studios now.', 'oria' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="pass-hero__stack" aria-label="<?php esc_attr_e( 'Examples of the kind of session Oria Pass will cover', 'oria' ); ?>">
			<?php foreach ( $oria_cards as $oria_i => $oria_c ) : ?>
				<article class="pcard pcard--float" style="--i:<?php echo (int) $oria_i; ?>">
					<div class="pcard__body">
						<h2 class="pcard__name"><?php echo esc_html( $oria_c[0] ); ?></h2>
						<p class="pcard__where"><?php echo esc_html( $oria_c[1] ); ?></p>
						<p class="pcard__when"><?php echo esc_html( $oria_c[2] ); ?></p>
					</div>
					<?php get_template_part( 'template-parts/pass/credit', null, array( 'credits' => $oria_c[3] ) ); ?>
				</article>
			<?php endforeach; ?>
			<p class="pass-hero__note"><?php esc_html_e( 'Examples, not live availability.', 'oria' ); ?></p>
		</div>

	</div>
</header>
