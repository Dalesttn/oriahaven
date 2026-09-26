<?php
/**
 * A few things to know — the Pass FAQ.
 *
 * Native <details>, so it works without JavaScript. Every rule stated here
 * is read from the Pass settings (credits, cancellation cut-off, expiry),
 * never typed into the copy: changing a rule is a settings change, and the
 * answers follow. While the Pass is not live, answers describe the plan.
 *
 * The FAQPage JSON-LD is built from the same array as the markup, so the
 * two can never say different things.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Settings;

$oria_live    = ! empty( $args['live'] );
$oria_credits = (int) ( $args['credits'] ?? Settings\get( 'credits_per_cycle' ) );
$oria_period  = (string) ( $args['period'] ?? Settings\get( 'price_period' ) );
$oria_city    = (string) ( $args['city'] ?? Settings\get( 'city' ) );
$oria_cutoff  = (int) Settings\get( 'cancel_cutoff_hrs' );
$oria_expiry  = (string) Settings\get( 'credit_expiry' );

$oria_faqs = array(
	array(
		'q' => __( 'Is the Pass available yet?', 'oria' ),
		'a' => $oria_live
			? sprintf(
				/* translators: %s: city */
				__( 'Yes. Oria Pass is open in %s, with participating businesses only.', 'oria' ),
				$oria_city
			)
			: __( 'Not yet. Oria Pass is being prepared with the first participating businesses. Join the waitlist and we will email you when there is news about the launch. Joining the waitlist is free and is not a membership.', 'oria' ),
	),
	array(
		'q' => __( 'How do credits work?', 'oria' ),
		'a' => sprintf(
			$oria_live
				/* translators: 1: credits, 2: period */
				? __( 'The membership gives you %1$d credits each %2$s. Each eligible session has its own credit cost, set by the business offering it, and you see that cost before you book.', 'oria' )
				/* translators: 1: credits, 2: period */
				: __( 'The planned membership includes %1$d credits each %2$s. Each eligible session will have its own credit cost, set by the business offering it, and you will see that cost before you book.', 'oria' ),
			$oria_credits,
			$oria_period
		),
	),
	array(
		'q' => __( 'Which businesses and experiences are included?', 'oria' ),
		'a' => __( 'Only participating businesses, and only the sessions they choose to offer. Being listed on Oria Haven does not mean a business takes part in Oria Pass. The experience types on this page are possibilities, not a confirmed launch programme.', 'oria' ),
	),
	array(
		'q' => __( 'How long do credits remain valid?', 'oria' ),
		'a' => 'cycle' === $oria_expiry
			? sprintf(
				$oria_live
					/* translators: %s: period */
					? __( 'Credits are for the %s they are issued in. Unused credits expire at the end of it and do not roll over.', 'oria' )
					/* translators: %s: period */
					: __( 'As planned, credits will be for the %s they are issued in. Unused credits will expire at the end of it and will not roll over.', 'oria' ),
				$oria_period
			)
			: __( 'How long unused credits last will be confirmed before the Pass opens.', 'oria' ),
	),
	array(
		'q' => __( 'What happens if a booking or membership is cancelled?', 'oria' ),
		'a' => sprintf(
			/* translators: %d: hours */
			( $oria_live ? '' : __( 'This is the planned policy. ', 'oria' ) ) . __( 'Cancel a booking more than %d hours before it starts and the credits come back. Inside that window they do not, because the business has held a place for you. If the business cancels, your credits always come back. You can cancel your membership from your account and keep the credits already issued for that period.', 'oria' ),
			$oria_cutoff
		),
	),
	array(
		'q' => __( 'Can I still book directly with providers?', 'oria' ),
		'a' => __( 'Yes. Oria Pass sits alongside a business\'s own bookings and memberships. It does not replace them.', 'oria' ),
	),
);

$oria_ld = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map(
		static fn( array $f ): array => array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
		),
		$oria_faqs
	),
);
?>
<div class="oria-pl__faq-list">
	<?php foreach ( $oria_faqs as $oria_f ) : ?>
		<details class="oria-pl__qa">
			<summary><?php echo esc_html( $oria_f['q'] ); ?></summary>
			<p><?php echo esc_html( $oria_f['a'] ); ?></p>
		</details>
	<?php endforeach; ?>
</div>
<script type="application/ld+json"><?php echo wp_json_encode( $oria_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
