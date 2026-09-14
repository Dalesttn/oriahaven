<?php
/**
 * Seed the first three Best Of guides, as drafts, with their copy in place.
 *
 * The picks are left for an editor: which studios make a shortlist is a
 * judgement, not a query. What this does do is print the candidates for each
 * guide -- the listings whose categories and audience tags fit -- so the
 * editor starts from a list of twelve, not four hundred.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply creates the drafts.
 *   A guide whose slug already exists is left alone, so re-running is safe.
 *
 * OPTIONS
 *   --apply          create the three guides (as drafts)
 *   --publish        publish them instead of drafting (with --apply)
 *   --sample-picks   also add the top candidates as picks with placeholder
 *                    reasons. FOR PREVIEW SITES ONLY: the reasons are not
 *                    editorial and must not go live.
 *
 * USAGE (from the WordPress root)
 *     php wp-content/plugins/oria-core/tools/seed-best-of.php
 *     php wp-content/plugins/oria-core/tools/seed-best-of.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! function_exists( 'update_field' ) ) {
	fwrite( STDERR, "ACF is not available.\n" );
	exit( 1 );
}

$argv    = $argv ?? array();
$apply   = in_array( '--apply', $argv, true );
$publish = in_array( '--publish', $argv, true );
$sample  = in_array( '--sample-picks', $argv, true );

/* ------------------------------------------------------------- the guides */

$guides = array(
	array(
		'slug'     => 'yoga-for-beginners-perth',
		'title'    => 'Best yoga for beginners in Perth',
		'excerpt'  => 'Welcoming Perth studios with beginner classes, patient teachers and an easy first booking.',
		'intro'    => "Starting yoga can feel intimidating when you don't know which class to choose or what to expect. These Perth studios were shortlisted for beginner-specific classes, supportive teaching and a clear, easy way to get started.",
		'category' => 'beginners',
		'practice' => 'yoga',
		'featured' => true,
		'award'    => 'best_for_beginners',
		'method'   => "We looked for studios offering dedicated beginner or foundations classes, teachers who explain the basics, mats and props provided, a clear class description on the studio's own website, and an introductory offer or drop-in option so a first visit is low-risk.",
		'faq'      => array(
			array( 'question' => 'What should I wear to my first yoga class?', 'answer' => 'Anything you can move and stretch in comfortably. Most studios provide mats; check the listing or bring your own if you prefer.' ),
			array( 'question' => 'Do I need to be flexible to start yoga?', 'answer' => 'No. Beginner classes are built for people who are not, and teachers offer variations for every pose.' ),
			array( 'question' => 'How early should I arrive?', 'answer' => 'Ten minutes before a first class gives you time to sign in, meet the teacher and mention anything they should know.' ),
		),
		'links'    => array(
			array( 'label' => 'Beginner-friendly yoga, by suburb', 'url' => home_url( '/explore/perth/yoga/beginners/' ) ),
			array( 'label' => 'Compare experiences', 'url' => home_url( '/compare/' ) ),
		),
		'find'     => array( 'practice' => 'yoga', 'audience' => 'beginners' ),
	),
	array(
		'slug'     => 'saunas-perth',
		'title'    => 'Best saunas in Perth',
		'excerpt'  => 'Standout sauna and recovery rooms across Perth, from infrared studios to traditional saunas with an ice bath alongside.',
		'intro'    => "Looking for a sauna in Perth? These are the standout sauna and recovery rooms across the city, from infrared studios to traditional saunas and contrast-therapy venues, with what each one offers and what a session costs.",
		'category' => 'recovery',
		'practice' => 'spa',
		'featured' => true,
		'award'    => 'best_sauna',
		'method'   => "We considered the type of sauna, whether sessions are private or shared, session length, whether an ice bath or cold plunge is available, showers and towels, price, and how clearly the venue publishes its hours and booking.",
		'faq'      => array(
			array( 'question' => 'What is the difference between an infrared and a traditional sauna?', 'answer' => 'A traditional sauna heats the air, usually to 70–90°C. An infrared sauna heats you directly at a lower air temperature, around 45–60°C, so it feels gentler for the same session length.' ),
			array( 'question' => 'How long is a typical sauna session?', 'answer' => 'Most Perth venues book sessions of 30 to 60 minutes. Contrast sessions alternate a few minutes of cold with time in the heat.' ),
			array( 'question' => 'Do I need to book?', 'answer' => 'Nearly always, and private rooms especially. Each listing links to the venue’s own booking page.' ),
		),
		'links'    => array(
			array( 'label' => 'Ice baths and cold plunges in Perth', 'url' => home_url( '/explore/perth/spa/cold-plunge/' ) ),
			array( 'label' => 'Compare experiences', 'url' => home_url( '/compare/' ) ),
		),
		'find'     => array( 'service' => array( 'traditional-sauna', 'infrared-sauna' ) ),
	),
	array(
		'slug'     => 'pilates-for-beginners-perth',
		'title'    => 'Best Pilates for beginners in Perth',
		'excerpt'  => 'Perth studios with beginner Pilates classes, reformer inductions and instructors who explain the basics.',
		'intro'    => "New to Pilates? These Perth studios offer a friendly way to start, with beginner classes, reformer inductions and small groups for people still learning the basics, so you don't need to know the terminology before you walk in.",
		'category' => 'beginners',
		'practice' => 'fitness',
		'featured' => false,
		'award'    => 'best_for_beginners',
		'method'   => "We looked for a dedicated beginner or foundations class, a reformer induction for first-timers, small group sizes, clear level descriptions on the studio's own timetable, equipment supplied, and an introductory offer.",
		'faq'      => array(
			array( 'question' => 'Should I start with mat or reformer Pilates?', 'answer' => 'Either works. Mat classes need nothing but you; reformer classes use a sprung machine and most studios run a short induction first. Beginners often find the reformer’s support helpful.' ),
			array( 'question' => 'What is a reformer induction?', 'answer' => 'A short first session, sometimes one-to-one, that shows you how the machine works and its safety points before you join a group class.' ),
		),
		'links'    => array(
			array( 'label' => 'Reformer Pilates in Perth', 'url' => home_url( '/explore/perth/fitness/reformer/' ) ),
			array( 'label' => 'Beginner-friendly yoga in Perth', 'url' => home_url( '/best/yoga-for-beginners-perth/' ) ),
		),
		'find'     => array( 'specialty' => array( 'reformer-pilates', 'pilates' ), 'audience' => 'beginners' ),
	),

	/* ---- the second five --------------------------------------------- */
	array(
		'slug'         => 'sound-baths-perth',
		'title'        => 'Best sound baths in Perth',
		'excerpt'      => 'Standout sound healing sessions across Perth, from beginner-friendly weekly sound journeys to small rooms and sound folded into yoga nidra.',
		'intro'        => "Looking for a sound bath in Perth? These are the standout sound healing sessions across the city: weekly sound journeys that welcome first-timers, small-room sessions capped at a handful of people, and classes that fold live crystal bowls into yoga nidra, so you can slow down and switch off for an hour.",
		'quick_answer' => "For a first sound bath, Sound Healing Perth's weekly crystal-bowl sessions in Trigg, Fremantle and Victoria Park are $44 casual with a three-session intro pack, and each opens with a guided meditation. Loop Yoga in North Fremantle folds live bowls into a 45-minute yoga nidra class for $32. For a smaller room, Holistic Bliss in Booragoon caps its group sessions at ten.",
		'category'     => 'relax',
		'practice'     => 'sound',
		'featured'     => false,
		'award'        => 'best_sound_bath',
		'reviewed'     => '2026-09-14',
		'method'       => "We looked for sessions that run on a regular timetable rather than as one-off events, a published price, mats and blankets provided, group size, a format that suits a first visit, and how clearly the practice explains what happens in the room. Nothing here claims a sound bath treats anything; it is an hour of lying down while somebody plays.",
		'faq'          => array(
			array( 'question' => 'What is a sound bath?', 'answer' => 'You lie down, usually on a mat with a blanket, while a practitioner plays singing bowls, gongs or chimes for somewhere between 45 and 90 minutes. There is nothing to do and nothing to get right. Many sessions open with a short guided relaxation.' ),
			array( 'question' => 'How much does a sound bath cost in Perth?', 'answer' => 'The sessions in this guide run from $32 to $49 for a casual visit, with intro packs and memberships bringing that down for regulars. Each pick shows the price and the month we checked it.' ),
			array( 'question' => 'Are sound baths suitable for beginners?', 'answer' => 'Yes. No experience, flexibility or belief is needed. If you find it hard to lie still for an hour, a session that combines sound with yoga nidra or yin gives the body something to do first.' ),
			array( 'question' => 'What should I bring?', 'answer' => 'Warm, loose clothes and socks — you cool down lying still. Most sessions provide mats, bolsters and blankets; the listing says if you should bring your own. An eye mask is a nice extra.' ),
			array( 'question' => 'How long does a sound bath last?', 'answer' => 'Most run 60 minutes. Yoga classes that end in a sound bath are usually 45 to 75 minutes; some sound journeys run to 90.' ),
			array( 'question' => 'Where can I find sound baths around Perth?', 'answer' => 'Regular sessions run in Trigg, Cottesloe, Claremont, North and East Fremantle, Victoria Park, Booragoon and the Perth Hills. Many yoga studios also hold occasional sound bath evenings; the directory lists them under Sound & float.' ),
		),
		'links'        => array(
			array( 'label' => 'All sound healing in Perth', 'url' => home_url( '/explore/perth/spa/sound-healing/' ) ),
			array( 'label' => 'Choosing a singing bowl', 'url' => home_url( '/singing-bowls/' ) ),
			array( 'label' => 'Best places to relax in Perth', 'url' => home_url( '/best/places-to-relax-perth/' ) ),
			array( 'label' => 'Wellness under $50 in Perth', 'url' => home_url( '/best/wellness-under-50-perth/' ) ),
		),
		'find'         => array( 'specialty' => array( 'sound-healing' ) ),
	),
	array(
		'slug'         => 'wellness-under-50-perth',
		'title'        => 'Best wellness experiences under $50 in Perth',
		'excerpt'      => 'Good wellness experiences around Perth you can have for $50 or less — meditation, sauna, a sound class, a swim — with the price and the month we checked it.',
		'intro'        => "Good wellness experiences around Perth that can be enjoyed for less than $50. Not the cheapest businesses — the real thing at a price you can try without thinking twice: a free meditation class, a sauna on the beach, thirty minutes of sauna and cold plunge, a sound class, two weeks of unlimited yoga. Every price comes from the practice's own list, with the month we checked it.",
		'quick_answer' => "There is more under $50 than you would think: free guided meditation at Dhammaloka in Nollamara, $15 drop-in classes at Kadampa Meditation Centre, a $20 sauna on the beach with Alchemy Saunas, thirty minutes of sauna and cold plunge for $25 at Melt in Subiaco, a $32 yoga nidra sound class at Loop Yoga, and fourteen days of unlimited hot yoga for $39 at The Yoga Garage. Prices checked September 2026.",
		'category'     => 'budget',
		'practice'     => '',
		'featured'     => false,
		'award'        => 'best_value',
		'reviewed'     => '2026-09-14',
		'method'       => "Only places with a genuine option at $50 or under made the list — a casual class, a drop-in, a single session or an intro pass — with the price taken from the practice's own published list and the month we checked it shown beside each pick. We also weighed how easy it is to just turn up, and whether the cheap option is the real experience rather than a taster.",
		'faq'          => array(
			array( 'question' => 'What wellness activities can you do in Perth for under $50?', 'answer' => 'Meditation classes (some free), sauna sessions on the beach or in a studio, a short sauna-and-cold-plunge session, sound baths, casual yoga and Pilates classes, aqua classes at a public pool, and intro passes that give a week or two of unlimited classes. All of those are in this guide with current prices.' ),
			array( 'question' => 'Are there affordable yoga classes in Perth?', 'answer' => 'Yes. Casual classes at the studios here run $32 to $45, and intro passes are the bargain: The Yoga Garage offers fourteen days unlimited for $39 and several studios run two- or three-week unlimited introductions.' ),
			array( 'question' => 'Can you try a sauna or cold plunge in Perth for under $50?', 'answer' => 'Yes. Alchemy Saunas run beach saunas from $20, Melt in Subiaco does thirty minutes of sauna and cold plunge for $25 and an hour for $35, and Zen Zone in Beldon offers infrared sauna and ice bath sessions from $25.' ),
			array( 'question' => 'What are good low-cost ways to relax in Perth?', 'answer' => 'A free guided meditation at Dhammaloka, a $15 drop-in at Kadampa, a $20 beach sauna, or a $32 yoga nidra class with live sound at Loop Yoga. None of them needs equipment or experience.' ),
			array( 'question' => 'What are good beginner wellness activities on a budget?', 'answer' => 'Drop-in meditation classes assume no background at all, aqua classes at a public pool are gentle and cheap, and an unlimited intro pass lets you try several styles of yoga for one low price before choosing.' ),
		),
		'links'        => array(
			array( 'label' => 'Best places to relax in Perth', 'url' => home_url( '/best/places-to-relax-perth/' ) ),
			array( 'label' => 'Best yoga for beginners in Perth', 'url' => home_url( '/best/yoga-for-beginners-perth/' ) ),
			array( 'label' => 'Best saunas in Perth', 'url' => home_url( '/best/saunas-perth/' ) ),
			array( 'label' => 'Explore everything in Perth', 'url' => home_url( '/explore/perth/' ) ),
		),
		'find'         => array( 'price' => 50 ),
	),
	array(
		'slug'         => 'breathwork-perth',
		'title'        => 'Best breathwork classes in Perth',
		'excerpt'      => 'Guided breathwork sessions across Perth, from small groups of six and monthly two-hour sessions to private one-to-one work and online options.',
		'intro'        => "Curious about breathwork? These Perth sessions offer guided ways to explore breathing practices in supportive group or one-to-one settings, with options for complete beginners as well as people who already have experience: monthly groups with mats and blankets provided, rooms capped at six, longer private sessions, and classes you can join online.",
		'quick_answer' => "For a first breathwork session, Unearth Remedy's monthly two-hour group in Fremantle is capped at ten, provides mats and blankets, and follows up by email the next day. For a smaller room, Paula Flinn's Fremantle groups cap at six. For one-to-one work, Graeme Salvetti in South Fremantle runs two-hour private sessions, with a Sunday group at $80 and online sessions at $65.",
		'category'     => 'relax',
		'practice'     => 'breathwork',
		'featured'     => false,
		'award'        => 'best_breathwork',
		'reviewed'     => '2026-09-14',
		'method'       => "We looked for facilitators who publish what a session involves and how long it runs, the group size, whether private and online options exist, mats and equipment provided, a published price, and membership of a professional body where the practice states one. We describe what each session offers; we make no claims about what it does for you.",
		'faq'          => array(
			array( 'question' => 'What is breathwork?', 'answer' => 'A guided session of deliberate breathing patterns, usually lying down, in a group or one-to-one. A facilitator sets the pace and the structure; sessions run from an hour to two hours and often end with a quiet period to settle.' ),
			array( 'question' => 'Are breathwork classes suitable for beginners?', 'answer' => 'Yes. Every session in this guide takes first-timers, and the facilitator explains the practice before it starts. If you are pregnant or have a health condition, tell the facilitator when you book so they can advise whether the session suits you.' ),
			array( 'question' => 'How long is a class?', 'answer' => 'Group sessions here run from 90 minutes to two hours. Private sessions are usually 90 to 120 minutes.' ),
			array( 'question' => 'What should I expect at my first session?', 'answer' => 'An explanation of the breathing pattern, then a guided period of breathing lying down, often with music, followed by rest. You can slow down or stop at any point. Some practices send a follow-up email the next day.' ),
			array( 'question' => 'How much does breathwork cost in Perth?', 'answer' => 'Group sessions in this guide are $65 to $80; private sessions run higher, with Graeme Salvetti’s published at $180. Each pick shows the price and the month we checked it.' ),
			array( 'question' => 'What should I wear or bring?', 'answer' => 'Loose, warm clothes and a water bottle. Mats, bolsters and blankets are provided at the sessions here unless the listing says otherwise. Eat lightly beforehand.' ),
		),
		'links'        => array(
			array( 'label' => 'All breathwork in Perth', 'url' => home_url( '/explore/perth/breathwork/' ) ),
			array( 'label' => 'Best sound baths in Perth', 'url' => home_url( '/best/sound-baths-perth/' ) ),
			array( 'label' => 'Best places to relax in Perth', 'url' => home_url( '/best/places-to-relax-perth/' ) ),
			array( 'label' => 'Best yoga for beginners in Perth', 'url' => home_url( '/best/yoga-for-beginners-perth/' ) ),
		),
		'find'         => array( 'specialty' => array( 'breathwork' ) ),
	),
	array(
		'slug'         => 'places-to-relax-perth',
		'title'        => 'Best places to relax in Perth',
		'excerpt'      => 'For when you know you want to switch off but not what to book: floats, bathhouses, silent days in the bush, candlelit yin and free meditation, by how much time you have.',
		'intro'        => "You don't know which wellness practice you want. You just want to relax. This guide is built for that: places across Perth where the whole visit is about slowing down, sorted by how much time you have, who you're with and what you want to spend — an hour in a float tank, a Sunday in a Swan Valley bathhouse, a silent day in the hills, or a free evening meditation.",
		'quick_answer' => "If you have an hour, a float at Beyond Rest in East Perth is the quietest sixty minutes in the inner city. If you have a Sunday, Swan Valley Retreat's Oasis — sauna, ice bath and spa — sits inside a day spa with floats and massage. For two people, Ganesha Wellness Spa in East Perth has couples rooms and a cedar hot tub cabin. For nothing at all, Dhammaloka runs free guided meditation several times a week.",
		'category'     => 'relax',
		'practice'     => 'spa',
		'featured'     => true,
		'award'        => 'best_for_relaxation',
		'reviewed'     => '2026-09-14',
		'method'       => "We chose places where the whole visit is built around slowing down — a float, a bathhouse circuit, a candlelit yin class, a silent day in the bush — rather than a treatment with relaxation as a side effect, and where the practice publishes what a session costs and how long it takes. Each pick answers a different situation: an hour alone, a Sunday, two people, after work, no money.",
		'faq'          => array(
			array( 'question' => 'Where can I go to relax in Perth?', 'answer' => 'A float tank for an hour of quiet, a bathhouse where sauna, steam and a cold bath run as a circuit, a day spa in the Swan Valley, a silent retreat day in the hills, or a slow yoga class that ends in sound. All of those are in this guide with prices and where they are.' ),
			array( 'question' => 'What are relaxing wellness activities in Perth?', 'answer' => 'Floating, sauna and bathhouse sessions, sound baths, yin and restorative yoga, yoga nidra, guided meditation and day retreats. The directory lists each under its own category; this guide picks one or two of each.' ),
			array( 'question' => 'What can I do when I need quiet time?', 'answer' => 'A float is the most complete quiet available — no light, no sound, an hour. A guided meditation class or a candlelit yin class are quieter than a sauna, which tends to be social.' ),
			array( 'question' => 'What are good relaxing experiences for couples?', 'answer' => 'Ganesha Wellness Spa has couples rooms and a private hot tub cabin; Swan Valley Retreat runs Oasis sessions for two; float centres such as The Life Spring in Rockingham offer couples floats.' ),
			array( 'question' => 'What are affordable ways to unwind in Perth?', 'answer' => 'Free guided meditation at Dhammaloka, a $20 beach sauna with Alchemy Saunas, or a $32 yoga nidra class with live sound at Loop Yoga. Our under-$50 guide has the full list.' ),
		),
		'links'        => array(
			array( 'label' => 'Best saunas in Perth', 'url' => home_url( '/best/saunas-perth/' ) ),
			array( 'label' => 'Best sound baths in Perth', 'url' => home_url( '/best/sound-baths-perth/' ) ),
			array( 'label' => 'Best breathwork classes in Perth', 'url' => home_url( '/best/breathwork-perth/' ) ),
			array( 'label' => 'Best ice baths and cold plunges in Perth', 'url' => home_url( '/best/ice-baths-perth/' ) ),
			array( 'label' => 'Wellness under $50 in Perth', 'url' => home_url( '/best/wellness-under-50-perth/' ) ),
		),
		'find'         => array( 'specialty' => array( 'float-therapy' ) ),
	),
	array(
		'slug'         => 'ice-baths-perth',
		'title'        => 'Best ice baths & cold plunges in Perth',
		'excerpt'      => 'Where to try an ice bath or cold plunge in Perth: dedicated recovery centres, bathhouse circuits, private sauna-and-plunge rooms and the cheapest way in.',
		'intro'        => "Looking for an ice bath in Perth? These are the recovery rooms, bathhouses and studios where a cold plunge is a bookable session rather than an afterthought — most with a sauna alongside for hot-and-cold — with what each offers, whether the room is private or shared, and what a session costs.",
		'quick_answer' => "Reclab in Wembley is the most complete set-up: cold plunge pools next to a magnesium hot plunge, a Finnish sauna and steam room, with casual dips as well as memberships. Ember Bath House in Osborne Park runs sauna, steam, mineral bath and ice bath as one circuit on a day pass. For the cheapest way in, Melt in Subiaco does thirty minutes of sauna and cold plunge for $25.",
		'category'     => 'recovery',
		'practice'     => 'recovery',
		'featured'     => false,
		'award'        => 'best_ice_bath',
		'reviewed'     => '2026-09-14',
		'note'         => "Cold-water immersion isn't suitable for everyone. If you have a medical condition, or you're unsure whether cold exposure is appropriate for you, seek professional medical advice before trying it.",
		'method'       => "We considered whether the ice bath or plunge is a bookable session in its own right, whether a sauna sits alongside for contrast, private versus shared rooms, session length, a published price, a first-timer option, and how clearly the venue explains booking and what to bring. We give no guidance on how long to stay in; the venue will.",
		'faq'          => array(
			array( 'question' => 'Where can I try an ice bath in Perth?', 'answer' => 'Dedicated recovery centres in Wembley, Jolimont and Stirling, bathhouses in Osborne Park and Subiaco, and studios in Beldon and Willetton, all listed in this guide. Several day spas and gyms also have one; the directory lists them under Ice bath & cold plunge.' ),
			array( 'question' => 'How much does an ice bath session cost?', 'answer' => 'Entry-level sessions in this guide run $25 to $45, with day passes and memberships above that. Each pick shows the price and the month we checked it.' ),
			array( 'question' => 'Are there sauna and ice bath venues in Perth?', 'answer' => 'Yes — most of the picks here pair the two. Reclab, Ember Bath House, Löyly, Melt and Zen Zone all put a sauna next to the plunge so you can alternate.' ),
			array( 'question' => 'Are cold plunges suitable for beginners?', 'answer' => 'Venues here take first-timers and will talk you through it; Recovery Lab in Jolimont runs a ten-day intro. Cold-water immersion is not for everyone, though — if you have a medical condition or are unsure, seek professional medical advice first.' ),
			array( 'question' => 'What should I bring?', 'answer' => 'Swimwear, a towel unless the venue provides one, thongs and a water bottle. Most rooms have showers and change rooms; the listing says which.' ),
			array( 'question' => 'How long does a session take?', 'answer' => 'Bookings run 30 to 60 minutes and cover the sauna, the plunge and the rest between. The time in the cold itself is short, and the venue will guide you.' ),
		),
		'links'        => array(
			array( 'label' => 'All ice baths and cold plunges in Perth', 'url' => home_url( '/explore/perth/spa/cold-plunge/' ) ),
			array( 'label' => 'Best saunas in Perth', 'url' => home_url( '/best/saunas-perth/' ) ),
			array( 'label' => 'Sauna, ice bath or float?', 'url' => home_url( '/sauna-ice-bath-or-float/' ) ),
			array( 'label' => 'Wellness under $50 in Perth', 'url' => home_url( '/best/wellness-under-50-perth/' ) ),
		),
		'find'         => array( 'specialty' => array( 'cold-plunge' ) ),
	),
);

/* ------------------------------------------------------------- candidates */

/**
 * Listings that fit a guide, best-evidenced first: those with a photo and
 * more reviews rise. The audience tag narrows when it leaves enough.
 *
 * @return list<int>
 */
function oria_bo_candidates( array $find, int $n = 8 ): array {
	$tax = array( 'relation' => 'AND' );
	foreach ( array( 'practice', 'specialty', 'service' ) as $t ) {
		if ( ! empty( $find[ $t ] ) ) {
			$tax[] = array( 'taxonomy' => $t, 'field' => 'slug', 'terms' => (array) $find[ $t ] );
		}
	}
	$base = array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'tax_query'      => $tax,
	);
	if ( ! empty( $find['price'] ) ) {
		unset( $base['tax_query'] );
		$base['meta_query'] = array( array( 'key' => 'price_from', 'value' => array( 1, (int) $find['price'] ), 'type' => 'NUMERIC', 'compare' => 'BETWEEN' ) );
	}
	$ids = get_posts( $base );
	if ( ! empty( $find['audience'] ) ) {
		$narrow            = $base;
		$narrow['tax_query'][] = array( 'taxonomy' => 'audience', 'field' => 'slug', 'terms' => (array) $find['audience'] );
		$tagged = get_posts( $narrow );
		if ( count( $tagged ) >= 4 ) {
			$ids = $tagged;
		}
	}
	usort(
		$ids,
		static function ( $a, $b ): int {
			$sa = ( has_post_thumbnail( $a ) ? 1000 : 0 ) + (int) \Oria\Theme\effective_rating( (int) $a, false )['count'];
			$sb = ( has_post_thumbnail( $b ) ? 1000 : 0 ) + (int) \Oria\Theme\effective_rating( (int) $b, false )['count'];
			return $sb <=> $sa;
		}
	);
	return array_slice( array_map( 'intval', $ids ), 0, $n );
}

/** Up to three service names, for a sample pick's highlights. */
function oria_bo_highlights( int $listing ): string {
	$names = array();
	foreach ( wp_get_post_terms( $listing, 'service' ) as $t ) {
		$names[] = \Oria\Theme\tname( $t );
	}
	return implode( ', ', array_slice( $names, 0, 3 ) );
}

/* ------------------------------------------------------------------- run */

foreach ( $guides as $g ) {
	echo "{$g['title']}\n  /best/{$g['slug']}/\n";

	$existing = get_page_by_path( $g['slug'], OBJECT, 'best_of' );
	if ( $existing instanceof WP_Post ) {
		printf( "  exists already (post %d, %s) — left alone\n", $existing->ID, $existing->post_status );
	}

	$cands = oria_bo_candidates( $g['find'] );
	printf( "  %d candidate%s:\n", count( $cands ), 1 === count( $cands ) ? '' : 's' );
	foreach ( $cands as $i => $id ) {
		$r = \Oria\Theme\effective_rating( $id, false );
		printf(
			"    %d. %-44s %-22s %s%s\n",
			$i + 1,
			mb_substr( get_the_title( $id ), 0, 44 ),
			mb_substr( \Oria\Core\BestOf\suburb( $id ), 0, 22 ),
			$r['rating'] > 0 ? sprintf( '%.1f (%d)', $r['rating'], $r['count'] ) : 'no rating',
			has_post_thumbnail( $id ) ? '' : '  [no photo]'
		);
	}

	if ( ! $apply || $existing instanceof WP_Post ) {
		echo "\n";
		continue;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'best_of',
			'post_status'  => $publish ? 'publish' : 'draft',
			'post_title'   => $g['title'],
			'post_name'    => $g['slug'],
			'post_excerpt' => $g['excerpt'],
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		printf( "  FAILED: %s\n\n", $post_id->get_error_message() );
		continue;
	}

	update_field( 'guide_intro', $g['intro'], $post_id );
	update_field( 'guide_category', $g['category'], $post_id );
	update_field( 'featured_guide', $g['featured'] ? 1 : 0, $post_id );
	update_field( 'methodology', $g['method'], $post_id );
	update_field( 'guide_faq', $g['faq'], $post_id );
	update_field( 'guide_links', $g['links'], $post_id );
	update_field( 'editorially_reviewed_date', $g['reviewed'] ?? gmdate( 'Y-m-d' ), $post_id );
	if ( ! empty( $g['quick_answer'] ) ) {
		update_field( 'quick_answer', $g['quick_answer'], $post_id );
	}
	if ( ! empty( $g['note'] ) ) {
		update_field( 'editor_note', $g['note'], $post_id );
	}
	$term = '' !== (string) $g['practice'] ? get_term_by( 'slug', $g['practice'], 'practice' ) : false;
	if ( $term instanceof WP_Term ) {
		update_field( 'guide_practice', $term->term_id, $post_id );
	}

	if ( $sample ) {
		$rows = array();
		foreach ( array_slice( $cands, 0, 4 ) as $id ) {
			$rows[] = array(
				'listing'    => $id,
				'award'      => $g['award'],
				'reason'     => 'Sample pick for preview. Replace this with the editorial reason before publishing.',
				'best_for'   => '',
				'highlights' => oria_bo_highlights( $id ),
				'lead_badge' => 0,
			);
		}
		update_field( 'best_of_entries', $rows, $post_id );
		printf( "  added %d SAMPLE picks — preview only\n", count( $rows ) );
	}

	printf( "  created post %d as %s: %s\n\n", $post_id, $publish ? 'published' : 'draft', get_permalink( $post_id ) );
}

if ( ! $apply ) {
	echo "Dry run: nothing written. Re-run with --apply to create the drafts.\n";
} else {
	echo "Done. Open Best Of in wp-admin to add the picks, then publish.\n";
}
