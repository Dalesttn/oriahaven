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

/**
 * The featured image: the copy bundled in data/retreat-images/ (named by the
 * source page's slug) first, because BookRetreats' image host refuses
 * requests from hosting-provider addresses; the remote URL only as a
 * fallback. Returns the attachment id or a WP_Error.
 */
function oria_retreat_image( int $post_id, array $o ) {
	$slug    = basename( rtrim( (string) $o['source_url'], '/' ) );
	$bundled = ORIA_CORE_DIR . 'data/retreat-images/' . $slug . '.jpg';
	if ( is_readable( $bundled ) ) {
		$tmp = wp_tempnam( $slug . '.jpg' );
		copy( $bundled, $tmp );
		$att = media_handle_sideload( array( 'name' => $slug . '.jpg', 'tmp_name' => $tmp ), $post_id, $o['title'] );
		if ( ! is_wp_error( $att ) ) {
			return (int) $att;
		}
	}
	return empty( $o['image'] ) ? new WP_Error( 'no_image', 'no bundled or remote image' ) : media_sideload_image( $o['image'], $post_id, $o['title'], 'id' );
}

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
		'styles'       => 'women,yoga',
		'label'        => 'For a slower few days',
		'meals'        => 'Three meals a day',
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
		'styles'       => 'silence',
		'label'        => 'For the quiet seeker',
		'meals'        => 'Two vegan meals a day',
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
		'styles'       => 'yoga,solo',
		'label'        => 'For the solo explorer',
		'meals'        => 'All meals, vegetarian',
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
		'styles'       => 'yoga',
		'label'        => 'For a full practice week',
		'meals'        => 'Most meals, organic vegetarian',
	),

	/* ---- Added 2026-10-03: researched on bookretreats.com, prices as the pages showed them (USD). ---- */
	array(
		'title'        => '5 Day Healing Breathwork & Juice Fasting Retreat in Australia',
		'source_url'   => 'https://bookretreats.com/r/5-day-healing-breathwork-juice-fasting-retreat-in-australia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/35k/35767/p_1390865/1000_1710819785.jpg',
		'provider'     => 'Britelle Humfrey, Anapana Ridge',
		'destination'  => 'wa',
		'locality'     => 'Lesmurdie',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '5',
		'nights'       => '4',
		'date_model'   => 'fixed',
		'start'        => '2027-02-18',
		'end'          => '2027-02-22',
		'summary'      => 'Five structured days at Anapana Ridge in the Perth Hills, built around a three-day cold-pressed juice fast rather than a lie-in-a-hammock escape. Mornings open with movement and meditation, the middle of the day moves through breathwork, sessions on fasting and emotional wellbeing, ice baths and sauna, and a sound healing, with sharing circles and quiet time between. The fast is broken with smoothies and soups before you head home.',
		'suits'        => 'Someone who wants a disciplined, facilitator-led programme with a clear daily structure, and is ready for a juice fast and cold exposure rather than a gentle spa stay.',
		'pace'         => 'Structured and intensive',
		'inclusions'   => "4 nights accommodation\nDaily juices, homemade broth and herbal teas\nMorning movement and meditation\nBreathwork sessions\nCold therapy with a certified instructor\nSound healing session",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance\nAdditional activities outside the itinerary",
		'price_mode'   => 'from',
		'price_amount' => '1375',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared twin room (early-bird)',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'solo',
		'label'        => 'For the committed reset',
		'meals'        => 'Juice fast: juices, broth and teas; smoothies and soups to finish',
	),
	array(
		'title'        => "4 Day Put God First Christian Women's Retreat in Perth, Australia",
		'source_url'   => 'https://bookretreats.com/r/4-day-put-god-first-christian-womens-retreat-in-perth-australia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/65k/65050/p_2632805/1000_1779158575.jpg',
		'provider'     => 'Sheree Adams, Ferguson Valley Escape',
		'destination'  => 'wa',
		'locality'     => 'Ferguson Valley',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '4',
		'nights'       => '3',
		'date_model'   => 'fixed',
		'start'        => '2026-11-06',
		'end'          => '2026-11-09',
		'summary'      => 'A faith-based long weekend for women at Ferguson Valley Escape, in the rolling country behind Bunbury. Days are organised around small-group Bible study and guided reflection, with nature walks, a paint-and-praise creative session, optional pampering, and open time for prayer, journaling, the pool and the hammocks, with three cooked meals a day.',
		'suits'        => 'Christian women who want fellowship and structured Scripture study in a country setting, including those coming on their own.',
		'pace'         => 'Gentle, with structured sessions',
		'inclusions'   => "3 nights accommodation\nThree meals a day and snacks\nWater, tea and coffee through the day\nWelcome drink\nWorkshops\nGuided nature hikes",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance",
		'price_mode'   => 'from',
		'price_amount' => '694',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared room (sale price)',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'women,solo',
		'label'        => 'For faith and fellowship',
		'meals'        => 'Breakfast, lunch, dinner and snacks',
	),
	array(
		'title'        => '4 Day Silent Zen Meditation & Yin Yoga Retreat in Bali',
		'source_url'   => 'https://bookretreats.com/r/4-day-silent-zen-meditation-yin-yoga-retreat-in-bali',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/29k/29161/p_925316/1000_1672327827.jpg',
		'provider'     => "Maitri Retreats, Yogi's Garden",
		'destination'  => 'bali',
		'locality'     => 'Payangan, near Ubud',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '4',
		'nights'       => '3',
		'date_model'   => 'multiple',
		'summary'      => 'A short silent retreat in a tucked-away garden venue in the hills above Ubud, built around zazen sitting rather than movement. Days run in four 25-minute meditation blocks with walking meditation between them, one yin yoga class, a meditative nature walk and an evening talk, all held in silence.',
		'suits'        => 'Anyone from first-timer to experienced sitter who wants a genuine silent reset without committing to a full week.',
		'pace'         => 'Slow and structured, held in silence',
		'inclusions'   => "3 nights accommodation\nThree meals a day and snacks\nDaily yin yoga\nDaily meditation sessions\nDaily facilitator talks\nGroup excursions",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance\nVisa fees\nMassage",
		'price_mode'   => 'from',
		'price_amount' => '498',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared dorm',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga,silence,solo',
		'label'        => 'For the quiet mind',
		'meals'        => 'Three meals a day, farm-to-table; vegan and gluten-free options',
	),
	array(
		'title'        => '8 Day "Get It All Pack" Surf and Yoga Retreat, Pelan Pelan, Bali',
		'source_url'   => 'https://bookretreats.com/r/8-day-get-it-all-pack-surf-and-yoga-retreat-pelan-pelan-bali',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/50k/50717/p_1868721/1000_1716876453.jpg',
		'provider'     => 'Pelan Pelan Bali',
		'destination'  => 'bali',
		'locality'     => 'Cemagi, near Canggu',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '8',
		'nights'       => '7',
		'date_model'   => 'check',
		'summary'      => 'A surf camp with yoga folded in, based in Cemagi a short hop from the Canggu beaches. Weekday mornings are spent in the water with one instructor to every two guests and video analysis afterwards; afternoons bring yoga or meditation, three Balinese massages across the week and a half-day trip to Tanah Lot. Weekends are free.',
		'suits'        => 'Solo travellers of any surf level who want an active, sociable week with structure on weekdays and free weekends.',
		'pace'         => 'Active mornings, relaxed afternoons',
		'inclusions'   => "Airport pickup\n7 nights accommodation\nDaily breakfast and weekday lunches\nWeekday surf lessons with video analysis\nThree yoga and one meditation session\nThree one-hour Balinese massages",
		'exclusions'   => "Flights\nTravel insurance\nVisa fees\nDinners\nAdditional activities",
		'price_mode'   => 'from',
		'price_amount' => '835',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared room for three',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga,solo',
		'label'        => 'For the early-morning surfer',
		'meals'        => 'Breakfast daily and weekday lunches; dinners not included',
	),
	array(
		'title'        => '4 Day Sound Healing, Mindful Yoga & Spa Holistic Retreat in Bali',
		'source_url'   => 'https://bookretreats.com/r/4-day-sound-healing-mindful-yoga-spa-holistic-retreat-in-bali',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/42k/42793/p_1508794/1000_1760442995.jpg',
		'provider'     => 'Green Point Retreats',
		'destination'  => 'bali',
		'locality'     => '',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '4',
		'nights'       => '3',
		'date_model'   => 'check',
		'summary'      => 'A compact three-night stay for people who want a gentle introduction to retreat life. Each day pairs a mid-morning yoga and meditation session with a one-hour Balinese massage and a hands-on cultural activity such as a painting or cooking class, a rice-terrace walk or an offering-making workshop, with one sound-healing session during the stay.',
		'suits'        => 'First-time retreat-goers and solo travellers who want yoga, spa time and local culture in a long weekend.',
		'pace'         => 'Gentle, with daily free time',
		'inclusions'   => "Airport pickup\n3 nights accommodation\nDaily mid-morning yoga\nOne sound healing session\nDaily one-hour Balinese massage\nDaily local cultural activity",
		'exclusions'   => "Travel insurance\nPersonal expenses\nAdditional tours around the island",
		'price_mode'   => 'from',
		'price_amount' => '396',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, private ensuite room',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga,solo',
		'label'        => 'For the long-weekend reset',
		'meals'        => 'Breakfast, lunch, dinner and snacks, vegetarian and vegan',
	),
	array(
		'title'        => '5 Day Luxury Breathwork And Yoga Retreat In Byron Bay, Australia',
		'source_url'   => 'https://bookretreats.com/r/5-day-luxury-breathwork-and-yoga-retreat-in-byron-bay-australia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/72k/72381/p_2743472/1000_group-practicing-tai-chi-pond-1784442052.jpg',
		'provider'     => 'Mark Moon, BlueGreen Sanctuary',
		'destination'  => 'aus-east',
		'locality'     => 'Byron Bay hinterland',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '5',
		'nights'       => '4',
		'date_model'   => 'fixed',
		'start'        => '2026-10-29',
		'end'          => '2026-11-02',
		'summary'      => 'Five acres of Byron hinterland bush and a pond-side practice space set the tone. Days move between guided breathwork, Hatha and Yin yoga, Qi Gong and meditation, with deliberate gaps for rest, a massage and wandering the grounds. Meals are cooked on site, so it is a stay-put retreat rather than a sightseeing one.',
		'suits'        => 'Adults who want a structured mix of breathwork and yoga with time built in to absorb it; solo travellers are welcome in private rooms.',
		'pace'         => 'Steady, with long rests between sessions',
		'inclusions'   => "4 nights accommodation\nDaily yoga classes\nDaily meditation\nOne-hour massage\nBreakfast, lunch, dinner and snacks\nWater, tea and coffee through the day",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance\nAdditional activities beyond the itinerary",
		'price_mode'   => 'from',
		'price_amount' => '1979',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared room',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga',
		'label'        => 'Breathwork with room to breathe',
		'meals'        => 'Breakfast, lunch, dinner and snacks',
	),
	array(
		'title'        => '7 Day Coming Home to Your Self: Meditation Retreat in Australia',
		'source_url'   => 'https://bookretreats.com/r/7-day-coming-home-to-your-self-meditation-retreat-in-australia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/72k/72421/p_2745162/1000_7-day-come-home-yourself-1784527912.jpg',
		'provider'     => 'Karl Baker, Maitripa Retreat Centre',
		'destination'  => 'aus-east',
		'locality'     => 'Healesville, Yarra Valley',
		'country'      => 'Australia',
		'length'       => 'longer',
		'days'         => '7',
		'nights'       => '6',
		'date_model'   => 'fixed',
		'start'        => '2027-02-10',
		'end'          => '2027-02-16',
		'summary'      => 'Maitripa sits on 53 forested acres outside Healesville, about an hour from Melbourne, and the week is held in silence. Days follow a meditation rhythm of guided sitting and walking practice, instruction periods and teacher check-ins, with unscheduled time to move at your own speed. Food is vegetarian and cooked for the group.',
		'suits'        => 'Beginners and experienced meditators alike who are comfortable with a week of silence and a trauma-informed, non-dual teaching approach.',
		'pace'         => 'Slow and silent',
		'inclusions'   => "Accommodation in a private single room\nBreakfast, lunch, dinner and snacks\nAll group meditation sessions and instruction\nOne-on-one sessions\nParking and WiFi",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance\nTreatments and activities not in the itinerary",
		'price_mode'   => 'from',
		'price_amount' => '1593',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, private single room',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'silence,solo',
		'label'        => 'A silent week in the forest',
		'meals'        => 'Breakfast, lunch, dinner and snacks, vegetarian',
	),
	array(
		'title'        => "3 Day Women's Solo Deep Rest Retreat in Blackheath, Australia",
		'source_url'   => 'https://bookretreats.com/r/3-day-womens-solo-deep-rest-retreat-in-blackheath-australia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/65k/65992/p_2665897/1000_1780629076.jpg',
		'provider'     => 'Beth Abrahams, Minyago Retreat',
		'destination'  => 'aus-east',
		'locality'     => 'Blackheath, Blue Mountains',
		'country'      => 'Australia',
		'length'       => 'weekend',
		'days'         => '3',
		'nights'       => '2',
		'date_model'   => 'check',
		'summary'      => 'A private, self-contained apartment on a bush-and-garden block in Blackheath, hosted one guest at a time. The programme is light and personal: an evening at a local thermal bathhouse, a morning meditation and breakfast hamper, a one-to-one mind-body session, a Reiki session, then an outdoor bath, a suggested walk or the firepit, with a late checkout so the last morning stays unhurried.',
		'suits'        => 'Women travelling alone who want to be looked after quietly rather than follow a group timetable.',
		'pace'         => 'Very slow and self-directed',
		'inclusions'   => "2 nights in a private apartment\nBreakfast hamper and meals through the stay\n90-minute mind-body session\nOne-hour Reiki session\nOutdoor bath and firepit\nParking and train pick-up option",
		'exclusions'   => "Airport transfers\nFlights\nTravel insurance\nTreatments and activities not in the itinerary",
		'price_mode'   => 'from',
		'price_amount' => '894',
		'price_currency' => 'USD',
		'price_basis'  => 'single guest, private apartment',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'women,solo',
		'label'        => 'For one woman, two nights',
		'meals'        => 'Breakfast hamper, lunch, dinner and snacks',
	),
	array(
		'title'        => "8 Day Renew & Thrive Women's Retreat, Bali",
		'source_url'   => 'https://bookretreats.com/r/8-day-renew-thrive-womens-retreat-bali',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/19k/19114/p_525477/1000_8-day-renew-thrive-womens-1790923083.jpg',
		'provider'     => 'Nourish Move Love Bali, Puri Bagus Lovina',
		'destination'  => 'bali',
		'locality'     => 'Lovina',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '8',
		'nights'       => '7',
		'date_model'   => 'fixed',
		'start'        => '2027-09-12',
		'end'          => '2027-09-19',
		'summary'      => "A week on Bali's quieter north coast at a beachfront resort in Lovina, in a women-only group of four to ten. Days bookend with practice: early-morning yoga and meditation, a mid-morning outing such as snorkelling or a waterfall walk, a long free afternoon, then late-day meditation and evening yoga before a three-course vegetarian dinner.",
		'suits'        => 'Women who want a structured, twice-daily yoga rhythm with a small group and plenty of unscheduled afternoon time.',
		'pace'         => 'Two practice sessions a day, free afternoons',
		'inclusions'   => "7 nights accommodation\nAirport transfers\nDaily yoga, meditation and pranayama\nOne-hour treatment\nWorkshops and excursions\nPre- and post-retreat support",
		'exclusions'   => "Flights\nTravel insurance\nVisa fees\nLunch\nAdditional treatments",
		'price_mode'   => 'from',
		'price_amount' => '1450',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, shared accommodation',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga,women',
		'label'        => 'For the week-long reset',
		'meals'        => 'Breakfast and three-course vegetarian dinners; lunch not included',
	),
	array(
		'title'        => '7 Day Pure Yoga Wellness Escape Retreat in Bali Indonesia',
		'source_url'   => 'https://bookretreats.com/r/7-day-pure-yoga-wellness-escape-retreat-in-bali-indonesia',
		'image'        => 'https://bookretreats.com/assets/photo/retreat/0m/22k/22994/p_864313/1000_1664376080.jpg',
		'provider'     => 'One Fit Wellness',
		'destination'  => 'bali',
		'locality'     => 'Ubud',
		'country'      => 'Indonesia',
		'length'       => 'longer',
		'days'         => '7',
		'nights'       => '6',
		'date_model'   => 'check',
		'summary'      => 'A small-group Ubud stay of one to eight guests built around daily yoga and mindfulness sessions, with one waterfall day and one day exploring Ubud. Three vegetarian-leaning meals and afternoon tea are provided, a full-body Balinese massage is included, and every class, trek and excursion is optional.',
		'suits'        => 'Solo travellers or couples of any level who want their own room, daily yoga on offer and the freedom to skip anything.',
		'pace'         => 'Gentle, everything optional',
		'inclusions'   => "6 nights accommodation\nThree meals a day and afternoon tea\nDaily yoga classes\nFull-body Balinese massage\nGroup excursions and guided hikes\nTransport during the retreat",
		'exclusions'   => "Flights\nVisa fees\nTravel insurance\nAdditional treatments\nActivities not in the itinerary",
		'price_mode'   => 'from',
		'price_amount' => '1358',
		'price_currency' => 'USD',
		'price_basis'  => 'per person, suite, single occupancy',
		'price_checked' => '2026-10-03',
		'reviewed'     => '2026-10-03',
		'styles'       => 'yoga,solo',
		'label'        => 'For the flexible first-timer',
		'meals'        => 'Three meals a day, vegetarian and vegan options, plus afternoon tea',
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
		// Already seeded: only the fields added since (kind of escape, card label, meals), and only when empty.
		$added = 0;
		foreach ( array( 'styles', 'label', 'meals' ) as $k ) {
			if ( isset( $o[ $k ] ) && '' === R\get( (int) $existing[0], $k ) ) {
				update_post_meta( (int) $existing[0], '_ro_' . $k, wp_slash( (string) $o[ $k ] ) );
				++$added;
			}
		}
		echo 'exists: ', $o['title'], $added ? " (filled {$added} new field" . ( 1 === $added ? '' : 's' ) . ')' : '', "\n";
		// A photo that failed to download first time round (host timeout, blocked fetch) is tried again.
		if ( ! has_post_thumbnail( (int) $existing[0] ) ) {
			$att = oria_retreat_image( (int) $existing[0], $o );
			if ( is_wp_error( $att ) ) {
				echo '   image still failed: ', $att->get_error_message(), "\n   download it from ", $o['source_url'], " and set it as the Featured image by hand.\n";
			} else {
				set_post_thumbnail( (int) $existing[0], (int) $att );
				update_post_meta( (int) $att, '_oria_image_source', $o['source_url'] );
				echo "   image added\n";
			}
		}
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
	$att = oria_retreat_image( (int) $id, $o );
	if ( is_wp_error( $att ) ) {
		echo 'image failed for ', $o['title'], ': ', $att->get_error_message(), "\n";
	} else {
		set_post_thumbnail( $id, (int) $att );
		update_post_meta( (int) $att, '_oria_image_source', $o['source_url'] );
	}
	echo 'created draft #', $id, ': ', $o['title'], "\n";
}
