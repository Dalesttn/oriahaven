<?php
/**
 * The questions somebody actually has before handing over a card.
 *
 * Reuses the site's FAQ part so the markup, the accordion behaviour and the
 * schema are the ones already in use. No FAQPage markup is emitted for a
 * product that cannot be bought yet -- that is for the template to decide,
 * not this list.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_credits = (int) ( $args['credits'] ?? 50 );
$oria_cutoff  = (int) \Oria\Pass\Settings\get( 'cancel_cutoff_hrs' );

$oria_faqs = array(
	array(
		'q' => __( 'What is Oria Pass?', 'oria' ),
		'a' => sprintf(
			/* translators: %d: credits per month */
			__( 'A monthly membership that gives you %d credits to spend on eligible sessions with participating wellness businesses around Perth.', 'oria' ),
			$oria_credits
		),
	),
	array(
		'q' => __( 'How do credits work?', 'oria' ),
		'a' => __( 'Each eligible session has its own credit price, set by the studio. Booking deducts that many credits from your balance. A yoga class costs fewer credits than a float.', 'oria' ),
	),
	array(
		'q' => __( 'Does every place on Oria Haven take the Pass?', 'oria' ),
		'a' => __( 'No, and it is not meant to. Only participating businesses, and only the sessions they choose to open up. The rest of the directory works exactly as it does now.', 'oria' ),
	),
	array(
		'q' => __( 'Can I still book directly with a business?', 'oria' ),
		'a' => __( 'Always. The Pass sits alongside a studio\'s own booking, it does not replace it.', 'oria' ),
	),
	array(
		'q' => __( 'Do unused credits roll over?', 'oria' ),
		'a' => __( 'No. Credits are for the month they are issued in and expire at the end of it, which is what keeps the price where it is.', 'oria' ),
	),
	array(
		'q' => __( 'What if I cancel a booking?', 'oria' ),
		'a' => sprintf(
			/* translators: %d: hours */
			__( 'Cancel more than %d hours before it starts and your credits come back. Inside that window they do not, because the studio has held a place for you. If the studio cancels, you always get your credits back.', 'oria' ),
			$oria_cutoff
		),
	),
	array(
		'q' => __( 'Can I cancel the membership?', 'oria' ),
		'a' => __( 'Yes, from your account. You keep the credits you have already been given for that month.', 'oria' ),
	),
	array(
		'q' => __( 'Where does it work?', 'oria' ),
		'a' => __( 'Perth to begin with, across the businesses signing up now. Where it goes after that depends on who joins.', 'oria' ),
	),
);

get_template_part(
	'template-parts/faq',
	null,
	array(
		'faqs'    => $oria_faqs,
		'heading' => __( 'Questions worth asking first', 'oria' ),
		'id'      => 'pass-faq',
	)
);
