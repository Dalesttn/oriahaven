<?php
/**
 * The five launch Trends to Try, as DRAFTS.
 *
 * Usage (from the WordPress root):
 *   php wp-content/plugins/oria-core/tools/seed-trends.php            # report only
 *   php wp-content/plugins/oria-core/tools/seed-trends.php --apply    # create the drafts
 *
 * What this writes, and what it deliberately does not:
 *
 * - Writes the descriptive sections an editor can check by reading them:
 *   what it is, why people are seeing it, what a session is like, beginner
 *   tips, questions to ask before booking, the goals it may suit, and the
 *   call to action pointing at a real directory page.
 * - Leaves EMPTY every section that needs research and a source: possible
 *   benefits, limitations, safety, who should seek advice first, evidence
 *   position, prices, sources, and the Reel itself. Each draft's private
 *   "Editor to-do" field lists them. Inventing any of that here is exactly
 *   what the brief forbids.
 *
 * Every post is created as a draft and cannot be published until its
 * checklist is complete (Core\Trends\guard). A slug that already exists is
 * skipped, never overwritten, so this is safe to run twice.
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

use Oria\Core\PracticesIndex;
use Oria\Core\Trends;

$apply = in_array( '--apply', $argv ?? array(), true );

$reel_todo = 'REEL URL REQUIRED — add an approved public Reel before publishing (Reel & creator tab), or tick “Publish without a Reel” as a deliberate decision.';
$research  = 'RESEARCH REQUIRED before publishing: possible benefits, what remains uncertain, safety considerations and who should talk to a health professional first — each claim with a linked source (Evidence & review tab); the evidence position and the date it was reviewed; a Perth price range with the date checked; and the Perth listings you have confirmed actually offer this (never inferred from a business name).';

/** The URL of a category or of a specialty's own page, only if it resolves. */
$cat_url = static function ( string $slug ): string {
	$t = get_term_by( 'slug', $slug, 'practice' );
	return ( $t instanceof WP_Term && function_exists( '\Oria\Core\PracticesIndex\category_url' ) ) ? PracticesIndex\category_url( $t ) : '';
};
$spec_url = static function ( string $slug ): string {
	$t = get_term_by( 'slug', $slug, 'specialty' );
	if ( ! $t instanceof WP_Term || ! function_exists( '\Oria\Core\PracticesIndex\specialty_url' ) ) {
		return '';
	}
	$u = PracticesIndex\specialty_url( $t );
	return is_string( $u ) ? $u : '';
};
$cat_ids = static function ( array $slugs ): array {
	$out = array();
	foreach ( $slugs as $s ) {
		$t = get_term_by( 'slug', $s, 'practice' );
		if ( $t instanceof WP_Term ) {
			$out[] = (int) $t->term_id;
		}
	}
	return $out;
};
$p = static fn( array $paras ): string => '<p>' . implode( "</p>\n<p>", $paras ) . '</p>';

$trends = array(
	array(
		'slug'    => 'sauna-rituals',
		'title'   => 'How to Make the Most of a Sauna Session',
		'excerpt' => 'Heat, cool down, rest, repeat: what a sauna ritual involves, how to pace a first visit, and what to ask before you book one in Perth.',
		'goals'   => array( 'relax', 'reset', 'recover' ),
		'cats'    => array( 'spa', 'recovery' ),
		'cta'     => array( 'Find saunas in Perth', $spec_url( 'infrared-sauna' ) ?: $cat_url( 'spa' ) ),
		'compare' => array( 'Sauna, ice bath or float?', home_url( '/compare/' ) ),
		'short'   => 'A sauna ritual is a simple sequence repeated two or three times: a spell in the heat, a cool-down, then a proper rest before going back in. Most Perth venues offer traditional (hot air) or infrared saunas, often alongside a cold plunge, and sessions are usually booked in time slots rather than by the round.',
		'what'    => $p(
			array(
				'A sauna is a small room heated well above body temperature. In a traditional sauna a stove heats the air, and pouring water on hot stones raises the humidity for a while; an infrared sauna warms you more directly with heating panels, at a lower air temperature. The "ritual" part is the order you do things in rather than any one of them: warm up, cool down, rest, and repeat.',
				'The practice comes from Finland, where it is ordinary and social rather than a treatment, and variations exist across many cultures. What people are sharing online is mostly that routine, filmed in newer bathhouse-style venues.',
			)
		),
		'why'     => $p(
			array(
				'Bathhouse and "social sauna" venues have opened in many cities, including Perth, and they photograph well: timber, steam, cold water. Sauna routines also turn up in fitness and recovery content, often paired with a cold plunge.',
				'Some of those posts make strong promises about detoxing or health. Those are exactly the claims this page checks against the evidence rather than repeating.',
			)
		),
		'expect'  => $p(
			array(
				'Most venues book in time slots, commonly around an hour, with shared or private sauna rooms, showers and a place to rest. Expect to shower first, sit or lie on a towel, and leave when you have had enough rather than when a timer says so.',
				'Rooms are usually quiet or low-talk. Swimwear is the norm in shared Perth venues; check the venue\'s own rules before you go.',
			)
		),
		'tips'    => array( 'Start with shorter rounds than you think you need, and leave early if you feel dizzy or unwell.', 'Drink water before and after, and skip alcohol before or during a session.', 'Shower before your first round and bring a towel to sit on.', 'Rest properly between rounds; the rest is part of the ritual, not a break from it.' ),
		'asks'    => array( 'Is it a traditional or an infrared sauna, and roughly how hot is it kept?', 'How long is a session, and is it shared or private?', 'Are there showers, towels and drinking water on site?', 'Is there staff on hand, and is there a cold plunge — and guidance on using it?' ),
	),
	array(
		'slug'    => 'cold-plunging',
		'title'   => 'Cold Plunging for Beginners: What to Know Before You Try It',
		'excerpt' => 'What an ice bath or cold plunge involves, how venues run a first session, and the questions to ask before you get in.',
		'goals'   => array( 'recharge', 'recover' ),
		'cats'    => array( 'recovery', 'spa' ),
		'cta'     => array( 'Find cold plunges in Perth', $spec_url( 'cold-plunge' ) ?: $cat_url( 'recovery' ) ),
		'compare' => array( 'Sauna, ice bath or float?', home_url( '/compare/' ) ),
		'short'   => 'Cold plunging means getting into very cold water, briefly and on purpose — in a purpose-built plunge tub at a recovery or bathhouse venue, or in the ocean. In Perth it is usually offered as a timed session, often alternating with a sauna, and first-timers are generally guided through short, controlled dips.',
		'what'    => $p(
			array(
				'A cold plunge is a tub of chilled water, typically kept far colder than the ocean off Perth for most of the year. You lower yourself in, usually up to the shoulders, stay for a short time, and get out. An ice bath is the same idea with ice added to ordinary water.',
				'Venues often pair it with a sauna — "contrast" sessions that move between heat and cold. Cold-water swimming at the beach is the outdoor cousin, with its own risks.',
			)
		),
		'why'     => $p(
			array(
				'Cold plunges are dramatic to watch: the gasp, the breathing, the steam off the skin. They feature heavily in fitness and "discipline" content, and in recovery venues that have opened around Perth.',
				'Many posts attach big claims to it — from mood to metabolism. This page separates the experience people describe from what research actually supports.',
			)
		),
		'expect'  => $p(
			array(
				'The first seconds in cold water bring a sharp intake of breath and fast breathing; a good venue will talk you through slowing it down. Sessions are short, and staff usually suggest starting with a brief dip and building up over visits.',
				'You will warm up afterwards with a towel, clothes or a sauna. Expect to feel very awake; how people describe the rest of the feeling varies widely.',
			)
		),
		'tips'    => array( 'Go to a venue with staff for your first time rather than trying it alone.', 'Keep the first dip short and get out if you feel faint, numb or unwell.', 'Enter slowly and focus on slowing your breathing.', 'Have warm clothes and a towel ready for straight afterwards.' ),
		'asks'    => array( 'How cold is the water kept, and how long do beginners usually stay in?', 'Will someone be present during my first plunge?', 'Is there a sauna or warm area to use afterwards?', 'Are there health conditions you ask people about before they plunge?' ),
	),
	array(
		'slug'    => 'japanese-head-spas',
		'title'   => 'What Happens at a Japanese Head Spa?',
		'excerpt' => 'A slow, detailed scalp and head treatment that has taken over social feeds — what a session involves, how long it takes, and what is cosmetic rather than therapeutic.',
		'goals'   => array( 'relax', 'reset' ),
		'cats'    => array( 'beauty', 'spa' ),
		'cta'     => array( 'Explore beauty and spa experiences in Perth', $cat_url( 'beauty' ) ?: $cat_url( 'spa' ) ),
		'compare' => array( '', '' ),
		'short'   => 'A Japanese head spa is a long, unhurried hair and scalp treatment: usually a look at the scalp, careful cleansing, a scalp and head massage, conditioning treatments and a blow-dry. It is a relaxation and grooming experience offered by some salons and spas, and it varies a lot between venues in what is included and how long it takes.',
		'what'    => $p(
			array(
				'The treatment grew out of Japanese salon culture, where a thorough scalp cleanse and massage is part of looking after hair. Salons elsewhere now sell it as a standalone experience, sometimes with a camera to show the scalp close up, layered cleansing, steam, and an extended massage of the head, neck and shoulders.',
				'It is a cosmetic and relaxation service. Some marketing links it to hair growth or scalp conditions; that is a different, medical question for a GP or dermatologist.',
			)
		),
		'why'     => $p(
			array(
				'The videos are soothing to watch: close-up water, foam and slow hands, often filmed as "ASMR". That has made it one of the most shared beauty-wellness treatments online.',
			)
		),
		'expect'  => $p(
			array(
				'You will usually lie back at a basin designed for it. Expect conversation to be kept to a minimum, a scalp check at the start at some venues, several rounds of washing and treatment, and a massage that can take much of the session. Sessions are commonly an hour or longer, and the finish is usually a blow-dry.',
			)
		),
		'tips'    => array( 'Ask exactly what is included; "head spa" can mean anything from a longer wash to a two-hour ritual.', 'Mention any scalp sensitivity or allergies to products before you start.', 'Allow time to have your hair styled afterwards, or bring what you need.' ),
		'asks'    => array( 'What steps does your head spa include, and how long is the whole session?', 'What products do you use, and can you work around allergies or sensitive skin?', 'Is the scalp check a cosmetic look, or do you give any advice about scalp conditions?', 'Is there a shorter version for a first visit?' ),
	),
	array(
		'slug'    => 'red-light-therapy',
		'title'   => 'Red-Light Therapy: Popular Claims, Evidence and What to Expect',
		'excerpt' => 'What red-light therapy sessions involve in Perth, how clinic devices differ from home gadgets, and how to read the claims made about them.',
		'goals'   => array( 'recover', 'recharge' ),
		'cats'    => array( 'spa', 'recovery', 'beauty' ),
		'cta'     => array( 'Find red-light therapy in Perth', $spec_url( 'red-light-therapy' ) ?: $cat_url( 'recovery' ) ),
		'compare' => array( '', '' ),
		'short'   => 'Red-light therapy means sitting or standing in front of panels, or inside a bed, that shine red and near-infrared light at the skin for a set time. In Perth it is offered at recovery centres, some day spas and skin clinics, as well as sold as home devices. The claims made for it range widely, and the evidence behind them does too.',
		'what'    => $p(
			array(
				'The devices use LEDs or lasers that give off light at particular red and near-infrared wavelengths. A session involves exposing an area of skin — or most of the body, in a bed or booth — for a timed period. It is not a tanning bed and does not use ultraviolet light.',
				'Clinic and venue panels are usually larger and more powerful than home masks and wands, and devices differ in wavelength, strength and how long they are used for — which matters when comparing claims.',
			)
		),
		'why'     => $p(
			array(
				'Glowing red rooms and LED face masks are striking on camera, and the devices are easy to sell as a gadget for home. Posts attach claims to them about skin, recovery, sleep and more.',
				'Those claims are not all equal. Some rest on clinical research for specific uses; many do not. This page is where they get sorted.',
			)
		),
		'expect'  => $p(
			array(
				'At a venue you will usually have a private room or booth, be offered eye protection, and sit or stand close to the panels for a set time. It feels warm but not hot. Sessions are short, and venues often sell them in packs or memberships.',
			)
		),
		'tips'    => array( 'Use the eye protection offered, and ask about it if none is.', 'Ask what wavelength and device is used, and what the session length is based on.', 'Be wary of any promise of a guaranteed result.' ),
		'asks'    => array( 'What device do you use, and at what wavelengths?', 'How long is a session, and how was that decided?', 'Do you provide eye protection?', 'Are there medications or conditions you ask about first, such as light sensitivity?' ),
	),
	array(
		'slug'    => 'somatic-movement',
		'title'   => 'What Is Somatic Movement — and Why Is It Trending?',
		'excerpt' => 'Slow, attentive movement focused on how the body feels from the inside: what a class is like, how it differs from yoga or physiotherapy, and what to look for in a teacher.',
		'goals'   => array( 'reset', 'relax', 'move' ),
		'cats'    => array( 'yoga', 'mind', 'fitness' ),
		'cta'     => array( 'Explore gentle movement in Perth', $cat_url( 'yoga' ) ?: $cat_url( 'mind' ) ),
		'compare' => array( '', '' ),
		'short'   => 'Somatic movement is a broad name for slow, low-effort movement where the attention is on how the body feels from the inside rather than on what a pose looks like. Classes borrow from several traditions and vary a lot; in Perth it is often taught within gentle yoga, movement and breathwork classes rather than under its own name.',
		'what'    => $p(
			array(
				'"Somatic" simply means "of the body". Somatic movement classes typically use small, slow movements, often lying down, with the teacher guiding you to notice sensation, tension and ease. Methods people may come across include Feldenkrais, Hanna Somatics and Body-Mind Centering, alongside newer blends.',
				'It is not physiotherapy, and it is not a treatment for injury or a mental health condition — even though some online content presents it that way.',
			)
		),
		'why'     => $p(
			array(
				'Short "somatic exercise" videos have spread widely, often linked to stress, trauma or "releasing" emotions. The gentleness appeals to people who find fast workouts off-putting.',
				'The language around it can run ahead of the evidence, and "trauma-informed" is used loosely. This page looks at what a class really involves and what to ask a teacher.',
			)
		),
		'expect'  => $p(
			array(
				'Expect a quiet class with a lot of lying or sitting, small repeated movements, and pauses to notice how you feel. There is usually little or no sweating. Teachers vary widely in training and approach.',
			)
		),
		'tips'    => array( 'Try a beginner or gentle class first, and tell the teacher about any injuries.', 'Move within comfort; nothing should be forced.', 'Ask about the teacher\'s training — the term is not regulated.' ),
		'asks'    => array( 'What training and method do you teach from?', 'What does a typical class involve, and is it mostly on the floor?', 'How do you adapt for injuries or limited mobility?', 'If you describe classes as trauma-informed, what does that mean in practice?' ),
	),
);

echo "\n" . ( $apply ? "APPLY -- creating drafts.\n" : "DRY RUN -- nothing is written. Add --apply to create the drafts.\n" );
echo str_repeat( '=', 72 ) . "\n";

foreach ( $trends as $t ) {
	$existing = get_page_by_path( $t['slug'], OBJECT, Trends\POST_TYPE );
	if ( $existing ) {
		printf( "SKIP  %-22s already exists (#%d, %s)\n", $t['slug'], $existing->ID, $existing->post_status );
		continue;
	}
	printf( "%s  %-22s %s\n      CTA: %s -> %s\n", $apply ? 'NEW ' : 'WOULD', $t['slug'], $t['title'], $t['cta'][0], $t['cta'][1] ?: '(no page found)' );
	if ( ! $apply ) {
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => Trends\POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => $t['title'],
			'post_name'    => $t['slug'],
			'post_excerpt' => $t['excerpt'],
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		echo '      ERROR: ' . $id->get_error_message() . "\n";
		continue;
	}
	update_field( 'short_answer', $t['short'], $id );
	update_field( 'what_it_is', $t['what'], $id );
	update_field( 'why_trending', $t['why'], $id );
	update_field( 'what_to_expect', $t['expect'], $id );
	update_field( 'beginner_tips', array_map( static fn( $x ) => array( 'tip' => $x ), $t['tips'] ), $id );
	update_field( 'questions_to_ask_provider', array_map( static fn( $x ) => array( 'question' => $x ), $t['asks'] ), $id );
	update_field( 'trend_goals', $t['goals'], $id );
	update_field( 'related_practices', $cat_ids( $t['cats'] ), $id );
	if ( '' !== $t['cta'][1] ) {
		update_field( 'primary_cta_label', $t['cta'][0], $id );
		update_field( 'primary_cta_url', $t['cta'][1], $id );
	}
	if ( '' !== $t['compare'][0] ) {
		update_field( 'compare_label', $t['compare'][0], $id );
		update_field( 'compare_url', $t['compare'][1], $id );
	}
	update_field( 'reel_status', 'unchecked', $id );
	update_field( 'editorial_stage', 'researching', $id );
	update_field( 'editor_todo', $reel_todo . "\n\n" . $research, $id );
	printf( "      created #%d (draft)\n", $id );
}

echo "\nAll five stay drafts until each passes the publishing checklist.\n\n";
