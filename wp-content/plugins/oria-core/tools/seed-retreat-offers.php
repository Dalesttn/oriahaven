<?php
/**
 * Starter retreat offers, researched on BookRetreats on 2026-09-27.
 *
 * Saved as DRAFTS with no affiliate link: each goes live only after the
 * exact link from the BookRetreats link builder is pasted in, the offer is
 * marked Active and published. Idempotent -- matched on the source URL, an
 * existing offer is left alone (so an editor's changes are never undone).
 *
 * Summaries are original. Inclusions, exclusions, prices (in the currency
 * shown on the source, US dollars) and dates are as the source listed them
 * on the day. Photos come from each retreat's own BookRetreats page, which
 * its affiliate terms allow ("you can use photos from our site to promote
 * our site"); the source is recorded on every offer.
 *
 * Run: wp eval-file wp-content/plugins/oria-core/tools/seed-retreat-offers.php
 */

use Oria\Core\Retreats as R;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$checked = '2026-09-27';
$offers  = array(
	array(
		'title'        => '4 Day Rest & Restore Women\'s Wellness Retreat',
		'source_url'   => 'https://bookretreats.com/r/4-day-rest-restore-womens-wellness-retreat-perth-australia',
		'image'        => 'https://bookretreats.com/cdn-cgi/image/width=1200,quality=80,f=auto,sharpen=1,fit=cover,gravity=auto/assets/photo/retreat/0m/42k/42167/p_1821273/1000_1736994769.jpg',
		'provider'     => 'Sasha & Marty Ott, Ferguson Valley Escape',
		'destination'  => 'wa',
		'locality'     => 'Ferguson Valley',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '4',
		'nights'       => '3',
		'date_model'   => 'check',
		'summary'      => 'Three unhurried nights on a property in the Ferguson Valley, about two hours south of Perth. The days mix gentle yoga or bush walks with massage, a cooking demonstration and a creative session, with plenty of free time in between.',
		'suits'        => 'A women-only retreat for anyone wanting a slower few days close to home, with company but no packed schedule.',
		'pace'         => 'Gentle, with free time each day',
		'inclusions'   => "3 nights accommodation\nThree meals a day\nDaily yoga or bushwalk\nHawaiian and pamper circle massages\nSound healing and a cooking demonstration",
		'exclusions'   => "Transport to the venue\nAlcohol (bring your own)\nExtra treatments",
		'price_mode'   => 'from',
		'price_amount' => '914',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared suite',
	),
	array(
		'title'        => '7 Day Silent Meditation and Quiet Retreat',
		'source_url'   => 'https://bookretreats.com/r/7-day-silent-meditation-and-quiet-retreat-in-australia',
		'image'        => 'https://bookretreats.com/cdn-cgi/image/width=1200,quality=80,f=auto,sharpen=1,fit=cover,gravity=auto/assets/photo/retreat/0m/73k/73782/p_2812904/1000_group-meditation-session-cozy-room-1788203805.jpg',
		'provider'     => 'Matthew & Cate Zoltan, Inn The Tuarts',
		'destination'  => 'wa',
		'locality'     => 'Busselton',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '7',
		'nights'       => '6',
		'date_model'   => 'fixed',
		'start'        => '2026-11-07',
		'end'          => '2026-11-13',
		'summary'      => 'A week of quiet on nine secluded acres outside Busselton. Days are built around guided meditation morning and afternoon, slow walking practice and meals taken in silence, with one-to-one time with the teacher when needed.',
		'suits'        => 'People who want a structured silent retreat in WA, including first-timers (a meditation guide is provided).',
		'pace'         => 'Quiet and structured, mostly in silence',
		'inclusions'   => "6 nights in a private room with ensuite\nTwo vegan meals a day\nDaily guided meditation\nAirport pickup and drop-off",
		'exclusions'   => "Flights\nLunch\nTravel insurance",
		'price_mode'   => 'from',
		'price_amount' => '2215',
		'price_currency' => 'USD',
		'price_basis'  => 'per person',
	),
	array(
		'title'        => '7 Day Solo Travelers Retreat: Yoga, Culture & Fun',
		'source_url'   => 'https://bookretreats.com/r/7-day-unforgettable-solo-yoga-retreat-in-ubud-bali',
		'image'        => 'https://bookretreats.com/cdn-cgi/image/width=1200,quality=80,f=auto,sharpen=1,fit=cover,gravity=auto/assets/photo/retreat/0m/8k/8119/p_2503867/1000_1772814487.jpg',
		'provider'     => 'Firefly Retreat Bali',
		'destination'  => 'bali',
		'locality'     => 'Ubud',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '7',
		'nights'       => '6',
		'date_model'   => 'multiple',
		'summary'      => 'A sociable week among the rice fields outside Ubud, with sunrise and sunset yoga and a different Balinese workshop most days, from offerings and cooking to herbal tonics. It starts every Sunday, so it is easy to fit around flights.',
		'suits'        => 'Solo travellers who would like yoga and company, and time to see some of Bali rather than stay put.',
		'pace'         => 'Active mornings and evenings, social in between',
		'inclusions'   => "6 nights accommodation\nAll meals (vegetarian)\nDaily yoga and meditation\nCultural workshops and an excursion\nOne Balinese massage",
		'exclusions'   => "Flights\nAirport transfers\nVisa and travel insurance",
		'price_mode'   => 'from',
		'price_amount' => '399',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared room',
	),
	array(
		'title'        => '7 Day Yoga, Meditation and Breathwork Retreat in Ubud',
		'source_url'   => 'https://bookretreats.com/r/7-day-yoga-meditation-and-breathwork-retreat-in-ubud-bali',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/24k/24157/p_2813179/1000_group-yoga-session-bamboo-pavilion-1787831103.jpg',
		'provider'     => 'Shakti Wellness, Wakanda Ubud Resort',
		'destination'  => 'bali',
		'locality'     => 'Ubud',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '7',
		'nights'       => '6',
		'date_model'   => 'check',
		'summary'      => 'A fuller week at a resort ten minutes from central Ubud: two yoga classes most days, daily meditation, and workshops on breathwork and sound, with group trips to waterfalls, rice terraces and the art market.',
		'suits'        => 'People who want a structured week of yoga and breathwork with suite accommodation.',
		'pace'         => 'Structured, two classes most days',
		'inclusions'   => "6 nights in a suite\nMost meals (organic vegetarian)\n12 yoga classes and 6 meditation sessions\nTwo massages and a breathwork session\nAirport pickup",
		'exclusions'   => "Flights\nTravel insurance\nSome lunches",
		'price_mode'   => 'from',
		'price_amount' => '2099',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, junior suite',
	),
);

foreach ( $offers as $i => $o ) {
	$existing = get_posts(
		array(
			'post_type'   => R\CPT,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_ro_source_url', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $o['source_url'], // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $existing ) {
		echo 'exists: ', $o['title'], "\n";
		continue;
	}
	$id = wp_insert_post( array( 'post_type' => R\CPT, 'post_status' => 'draft', 'post_title' => $o['title'] ) );
	$meta = array_diff_key( $o, array( 'title' => 1, 'image' => 1 ) ) + array(
		'booking'       => 'BookRetreats',
		'aff_url'       => '',
		'image_source'  => $o['source_url'],
		'price_checked' => $checked,
		'reviewed'      => $checked,
		'state'         => 'draft',
		'order'         => (string) ( $i + 1 ),
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_ro_' . $k, wp_slash( (string) $v ) );
	}
	$att = media_sideload_image( $o['image'], $id, $o['title'], 'id' );
	if ( is_wp_error( $att ) ) {
		echo 'image failed for ', $o['title'], ': ', $att->get_error_message(), "\n";
	} else {
		set_post_thumbnail( $id, (int) $att );
		update_post_meta( (int) $att, '_oria_image_source', $o['source_url'] );
	}
	echo 'created draft #', $id, ': ', $o['title'], "\n";
}
