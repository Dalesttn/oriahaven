<?php
/**
 * Health Food Stores (practice health-food-stores): landing intro and FAQ.
 *
 *   wp eval-file wp-content/plugins/oria-core/tools/health-stores-content.php
 *
 * Every answer comes from batch 2026-10-09-health-stores, whose facts were
 * verified against each store's own website. Names, not counts, so the
 * answers stay true however many of the drafts end up published -- but if
 * a store is NOT published, remove it from the FAQ (term edit screen).
 * No health claims: what the stores stock and how to shop, nothing more.
 * Safe to rerun: it overwrites only this term's intro and FAQ override.
 *
 * @package Oria\Core
 */

defined( 'ABSPATH' ) || exit;

$term = get_term_by( 'slug', 'health-food-stores', 'practice' );
if ( ! $term instanceof WP_Term ) {
	WP_CLI::error( 'Run `wp oria sources setup` first: the health-food-stores category does not exist.' );
}

$intro = '<p>Perth&rsquo;s independent health food stores are more varied than the name suggests. Some are organic grocers with fridges of local produce, some are refill stores where you bring your own jars and pay by weight, and a few have a café or a practitioner on site.</p>'
	. '<p>Each store below shows what it actually stocks &mdash; produce, bulk and refill, supplements, gluten-free and plant-based ranges &mdash; and whether it delivers or offers click and collect, all checked on the store&rsquo;s own website. Use the filters to narrow it down, and check opening hours before a special trip.</p>';

$faq = array(
	'Where can I buy bulk and refill groceries in Perth?'                 => 'Stores here with bulk bins or refill stations include Earth Wholefoods (Joondalup), The Little Big Store (Wangara), Scoop Wholefoods (Cottesloe), Loose Produce (Victoria Park), Replenish (Kalamunda), Wasteless Pantry (Mundaring and Bassendean) and Pantryman (Mandurah). Each store sets its own rules for bringing containers, so check its page before you go.',
	'Which health food stores sell organic fruit and vegetables?'           => 'Earth Wholefoods, The Little Big Store, Dunn & Walton (Doubleview), Loose Produce and The Organic Collective (Hamilton Hill) stock organic produce. The Little Big Store, Dunn & Walton and The Organic Collective also deliver produce boxes.',
	'Do any Perth health food stores deliver?'                             => 'Yes. The Little Big Store delivers to most of metro Perth, Scoop Wholefoods offers free local delivery around Cottesloe over a minimum spend, Dunn & Walton delivers produce boxes by postcode, The Organic Collective delivers box subscriptions, and Wasteless Pantry delivers from its online shop. Replenish and The Little Big Store also offer click and collect.',
	'Where can I find gluten-free and plant-based food?'                   => 'Earth Wholefoods, The Little Big Store, Scoop Wholefoods, Loose Produce, Wasteless Pantry Bassendean, Sam\'s Health Emporium and Pantryman all list gluten-free ranges, and several carry vegan and plant-based lines too. If you have an allergy, check the label in store.',
	'Can I see a naturopath or nutritionist at a health food store?'       => 'Sam\'s Health Emporium in Willetton has an in-store clinic with naturopaths and nutritionists, and Earth Wholefoods in Joondalup has a nutrition clinic. Both take bookings by phone.',
	'Are health food stores open on Sundays?'                              => 'Scoop Wholefoods, Replenish, both Wasteless Pantry stores, Sam\'s Health Emporium and Pantryman open on Sundays; Earth Wholefoods and The Little Big Store are closed. These are the hours each store publishes, so check before you go.',
);

update_term_meta( $term->term_id, 'landing_intro', $intro );
$lines = array();
foreach ( $faq as $q => $a ) {
	$lines[] = $q . ' | ' . $a;
}
update_term_meta( $term->term_id, \Oria\Core\Faq\META_OVERRIDE, implode( "\n", $lines ) );

WP_CLI::success( sprintf( 'Intro and %d FAQs set on %s (#%d).', count( $faq ), $term->name, $term->term_id ) );
