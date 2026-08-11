<?php
/**
 * Specialties: the third browse dimension after practice and area.
 *
 * Practices stay the ~12 broad doors people walk in through; specialties are
 * the precise modalities a practice offers (acupuncture, remedial massage,
 * homeopathy…). Each specialty term is a filter in the directory AND an
 * indexable landing page at /perth/{specialty}/.
 *
 * Terms are assigned deterministically by keyword-matching a listing's
 * services, name and blurb — the same map is used by the importer for new
 * rows and by `wp oria seed_specialties` for the back catalogue, so the two
 * can never disagree. Modalities only, never conditions: "treats X" wording
 * is a therapeutic claim the directory does not make on anyone's behalf.
 */

declare(strict_types=1);

namespace Oria\Core\Specialties;

use Oria\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * slug => [display name, match pattern (regex body, case-insensitive),
 * landing-page description].
 */
const MAP = array(
	'acupuncture'        => array( 'Acupuncture', 'acupunctur', 'AHPRA-registered acupuncturists and clinics offering acupuncture across the Perth metro.' ),
	'naturopathy'        => array( 'Naturopathy', 'naturopath', 'Naturopaths offering consultations in clinic and by telehealth across Perth.' ),
	'herbal-medicine'    => array( 'Herbal medicine', 'herbal|herbalist', 'Practices dispensing Western and traditional herbal medicine in Perth.' ),
	'homeopathy'         => array( 'Homeopathy', 'homeopath|homoeopath', 'Homeopathic consultations from registered practitioners around Perth.' ),
	'chinese-medicine'   => array( 'Chinese medicine', 'chinese (?:herbal )?medicine|\btcm\b', 'Traditional Chinese medicine clinics and registered practitioners in Perth.' ),
	'ayurveda'           => array( 'Ayurveda', 'ayurved', 'Ayurvedic consultations, treatments and panchakarma programs in Perth.' ),
	'remedial-massage'   => array( 'Remedial massage', 'remedial', 'Remedial massage therapists with health-fund rebates across Perth.' ),
	'deep-tissue'        => array( 'Deep tissue massage', 'deep tissue', 'Deep tissue and firm-pressure massage across the Perth metro.' ),
	'sports-massage'     => array( 'Sports massage', 'sports massage', 'Sports and athletic recovery massage in Perth.' ),
	'pregnancy-massage'  => array( 'Pregnancy massage', 'pregnancy massage|prenatal massage', 'Pregnancy-trained massage therapists around Perth.' ),
	'lymphatic-drainage' => array( 'Lymphatic drainage', 'lymphatic', 'Manual lymphatic drainage practitioners in Perth.' ),
	'cupping'            => array( 'Cupping', 'cupping', 'Cupping therapy offered within massage and Chinese medicine clinics in Perth.' ),
	'dry-needling'       => array( 'Dry needling', 'dry needling', 'Dry needling within physiotherapy and massage practices in Perth.' ),
	'bowen-therapy'      => array( 'Bowen therapy', 'bowen', 'Bowen therapy practitioners across the Perth metro.' ),
	'thai-massage'       => array( 'Thai massage', 'thai massage|traditional thai', 'Traditional Thai massage studios across Perth.' ),
	'shiatsu'            => array( 'Shiatsu', '\bshiatsu\b', 'Shiatsu practitioners in Perth.' ),
	'myotherapy'         => array( 'Myotherapy', 'myotherap', 'Myotherapists and clinical massage in Perth.' ),
	'craniosacral'       => array( 'Craniosacral therapy', 'craniosacral|cranio.sacral', 'Craniosacral therapy practitioners around Perth.' ),
	'structural-integration' => array( 'Structural integration', 'rolfing|structural integration|\bkmi\b|anatomy trains', 'Rolfing and structural integration bodywork in Perth.' ),
	'reflexology'        => array( 'Reflexology', 'reflexolog', 'Reflexology within natural-therapy clinics around Perth.' ),
	'kinesiology'        => array( 'Kinesiology', 'kinesiolog', 'Kinesiology practitioners in Perth.' ),
	'hypnotherapy'       => array( 'Hypnotherapy', 'hypnotherap', 'Hypnotherapy within wellness clinics across Perth.' ),
	// \bphysio\b, not bare "physio" — otherwise "exercise physiology" matches.
	'physiotherapy'      => array( 'Physiotherapy', 'physiotherap|\bphysio\b', 'Physiotherapy-led clinics listed in the directory.' ),
	'meditation'         => array( 'Meditation', 'meditat', 'Sitting groups, guided sessions and meditation courses across Perth.' ),
	'breathwork'         => array( 'Breathwork', 'breathwork|pranayama|breathing', 'Breathwork facilitators and pranayama teaching in Perth.' ),
	'yoga'               => array( 'Yoga', '\byoga\b|\byin\b|hatha|vinyasa', 'Yoga studios and teachers with a slow, mindful focus.' ),
	'mindfulness'        => array( 'Mindfulness', 'mindfulness|\bmbsr\b', 'Mindfulness coaching, MBSR courses and secular practice.' ),
	'yoga-nidra'         => array( 'Yoga nidra', 'yoga nidra|yogic sleep|\birest\b|deep restore', 'Yoga nidra and guided deep-rest sessions across Perth.' ),
	'consciousness-coaching' => array( 'Consciousness coaching', 'consciousness coach|transformational coach', 'Consciousness and transformational coaching in Perth.' ),
	'sound-healing'      => array( 'Sound healing', 'sound bath|sound healing|sound journey|\bgong\b', 'Sound baths and gong sessions around Perth.' ),
	'float-therapy'      => array( 'Float therapy', '\bfloat\b', 'Float tanks and sensory-rest rooms in Perth.' ),
	'nutrition'          => array( 'Nutrition', 'nutrition|dietitian|dietetic', 'Dietitians and nutrition consultations, in clinic and online.' ),
	'iridology'          => array( 'Iridology', 'iridolog', 'Iridology offered within naturopathic clinics in Perth.' ),
	'reiki'              => array( 'Reiki', '\breiki\b', 'Reiki practitioners and teachers across the Perth metro.' ),
	'pranic-healing'     => array( 'Pranic healing', 'pranic', 'Pranic healing sessions and courses in Perth.' ),
	'energy-healing'     => array( 'Energy healing', 'energy healing|quantum healing|energy work', 'Energy healing practitioners across Perth.' ),
	'chakra-balancing'   => array( 'Chakra balancing', 'chakra', 'Chakra balancing within energy-healing practices in Perth.' ),
	'crystal-healing'    => array( 'Crystal healing', 'crystal heal|crystal reiki|crystal therapy|crystal light|crystal & sound|crystal and sound', 'Crystal healing sessions around Perth.' ),
	'shamanic-healing'   => array( 'Shamanic healing', 'shamanic', 'Shamanic healing practitioners in Perth.' ),
	'spiritual-healing'  => array( 'Spiritual healing', 'spiritual healing|soul retrieval', 'Spiritual healing practitioners in Perth.' ),
	'aura-clearing'      => array( 'Aura & energy clearing', '\baura\b|energy clearing|space clearing', 'Aura and energy clearing within Perth healing practices.' ),
	'sleep-coaching'     => array( 'Sleep support', 'sleep coach|insomnia|cbt-i|sleep psych|behavioural sleep', 'Sleep programs and behavioural sleep support in Perth.' ),
	'infrared-sauna'     => array( 'Infrared sauna', 'infrared sauna', 'Infrared sauna sessions across Perth recovery studios.' ),
	'cold-plunge'        => array( 'Ice bath & cold plunge', 'ice bath|cold plunge|contrast therapy|nordic cycle', 'Ice baths, cold plunges and contrast therapy in Perth.' ),
	'cryotherapy'        => array( 'Cryotherapy', 'cryotherapy|whole.?body cryo', 'Whole-body and spot cryotherapy in Perth.' ),
	'red-light-therapy'  => array( 'Red light therapy', 'red light|theralight', 'Red light therapy sessions around Perth.' ),
	'hyperbaric-oxygen'  => array( 'Hyperbaric oxygen', 'hyperbaric|hbot', 'Hyperbaric oxygen therapy chambers in Perth.' ),
	'compression'        => array( 'Compression therapy', 'compression boot|normatec|lymphatic compression|body compression', 'Compression recovery within Perth studios.' ),
	'chiropractic'       => array( 'Chiropractic', 'chiropract', 'AHPRA-registered chiropractors across the Perth metro.' ),
	'osteopathy'         => array( 'Osteopathy', 'osteopat', 'Registered osteopaths across Perth.' ),
	'exercise-physiology' => array( 'Exercise physiology', 'exercise physiol|exercise rehabilitation', 'ESSA-accredited exercise physiologists in Perth.' ),
	'speech-pathology'   => array( 'Speech pathology', 'speech path|speech therap', 'Speech pathologists for children and adults across Perth.' ),
	'occupational-therapy' => array( 'Occupational therapy', 'occupational therap', 'Occupational therapists in clinic, home and school settings.' ),
	'forest-bathing'     => array( 'Forest bathing', 'forest bath|shinrin|forest therapy', 'Guided forest bathing and shinrin-yoku walks in bushland around Perth.' ),
	'eco-therapy'        => array( 'Eco therapy', 'eco.?therapy|nature.based therapy|nature therapy|walk and talk|walk & talk|bush adventure', 'Nature-based counselling and walk-and-talk sessions held outdoors across Perth.' ),
	'outdoor-fitness'    => array( 'Outdoor fitness', 'outdoor fitness|boot ?camp|outdoor training|park fitness|beach fitness|boxfit|outdoor workout|outdoor group fitness', 'Group fitness, boot camps and personal training in Perth parks and on the beach.' ),
	'hiking'             => array( 'Hiking & bushwalking', 'hiking|bushwalk|bush walk|trail walk', 'Bushwalking clubs and guided hiking groups walking the Perth hills and coast.' ),
	'surf-therapy'       => array( 'Surf therapy', 'surf therapy|surf experience|surfing program|learn to surf|surf session', 'Surf-based wellbeing programs run on Perth beaches.' ),
	'equine-therapy'     => array( 'Equine assisted therapy', 'equine|hippotherapy|horse.assisted|horsemanship', 'Ground-based and ridden equine assisted sessions on properties around Perth.' ),
	'wellness-retreats'  => array( 'Wellness retreats', 'retreat', 'Half-day, full-day and residential wellness retreats within reach of Perth.' ),
	// (?<!wo) — "women's circle" must not match the men's pattern.
	'mens-groups'        => array( "Men's groups", "(?<!wo)men.?s (?:group|circle|table|shed)|tough guy book", "Regular men's groups, circles and tables meeting around Perth." ),
	'womens-circles'     => array( "Women's circles", "women.?s (?:circle|gathering)|sister circle|red tent", "Women's circles gathering across the Perth metro." ),
	'support-groups'     => array( 'Peer support groups', 'peer support|mutual support|support group', 'Peer-run support groups meeting across the Perth metro.' ),
	'recovery-meetings'  => array( 'Addiction recovery support', 'smart recovery|alcoholics anonymous|twelve.?step|12.?step|recovery meeting|recovery group', 'Free recovery meetings and support groups across Perth.' ),
	'wellness-meetups'   => array( 'Wellness meetups', 'walking group|wellness meetup|social walk|group walk', 'Social wellness meetups and walking groups around Perth.' ),
	'doula'              => array( 'Doulas', '\bdoulas?\b', 'Birth and postpartum doulas supporting families across Perth.' ),
	'lactation'          => array( 'Lactation consultants', 'lactation|ibclc|breastfeeding support|infant feeding', 'IBCLC lactation consultants offering clinic, home and online support in Perth.' ),
	'fertility-support'  => array( 'Fertility support', 'fertility|conception|ivf (?:support|cycle)', 'Practitioners offering fertility-focused care and IVF cycle support in Perth.' ),
	'womens-health'      => array( "Women's health", "women.?s health|pelvic (?:floor|health|physio)", "Women's health practitioners and pelvic health physiotherapy across Perth." ),
	'mens-health'        => array( "Men's health", "(?<!wo)men.?s (?:pelvic )?health", "Men's health practitioners and pelvic health support in Perth." ),
	'midwifery'          => array( 'Midwifery', 'midwife|midwifery', 'Endorsed private midwives offering continuity of care around Perth.' ),
	'pregnancy-yoga'     => array( 'Pregnancy yoga', 'pregnancy yoga|prenatal|antenatal yoga', 'Pregnancy and postnatal yoga classes across the Perth metro.' ),
	'parenting-support'  => array( 'Parenting support', 'parenting coach|sleep consultant|sleep support|parenting support|antenatal class|birth education|birth preparation|childbirth education|hypnobirth', 'Parenting coaches, sleep consultants and birth education in Perth.' ),
	'pilates'            => array( 'Pilates', 'pilates|reformer', 'Mat, reformer and clinical Pilates studios across the Perth metro.' ),
	'barre'              => array( 'Barre', '\bbarre\b', 'Barre classes in studios around Perth.' ),
	'personal-training'  => array( 'Personal training', 'personal train|1:1 coaching|one.on.one training', 'Personal trainers and small-group coaching across Perth.' ),
	'functional-fitness' => array( 'Functional fitness', 'functional (?:fitness|training|strength|movement)|strength and conditioning', 'Functional strength and movement training in Perth.' ),
	'mobility'           => array( 'Mobility coaching', 'mobility|movement coach|movement rehabilitation', 'Mobility and movement coaching around Perth.' ),
	'stretch-therapy'    => array( 'Stretch therapy', 'assisted stretch|fascial stretch|stretch therapy|stretch class', 'Assisted stretching and stretch therapy studios in Perth.' ),
	'aqua-fitness'       => array( 'Aqua fitness', 'aqua (?:aerobics|fitness|cardio|zumba|hiit|balance)|deep aqua', 'Aqua fitness classes at pools around Perth.' ),
	'tai-chi'            => array( 'Tai chi & qigong', 'tai ?chi|taiji|qigong|qi gong', 'Tai chi and qigong schools and classes across Perth.' ),
	'art-therapy'        => array( 'Art therapy', 'art (?:psycho)?therap|arts therap|creative arts therap', 'Registered art therapists and creative arts therapy across Perth.' ),
	'music-therapy'      => array( 'Music therapy', 'music therap', 'Registered music therapists working across the Perth metro.' ),
	'dance-movement'     => array( 'Dance & movement', 'conscious dance|dance movement|open floor|5 ?rhythms|ecstatic dance|dance therap', 'Conscious dance and movement practices around Perth.' ),
	'expressive-therapies' => array( 'Expressive therapies', 'expressive (?:arts|therap)|somatic therap', 'Expressive and somatic therapy practices in Perth.' ),
);

/** Create any missing specialty terms. Returns slug => term_id. */
function ensure_terms(): array {
	$ids = array();
	foreach ( MAP as $slug => $def ) {
		$existing = get_term_by( 'slug', $slug, Taxonomies\SPECIALTY );
		if ( $existing instanceof \WP_Term ) {
			$ids[ $slug ] = (int) $existing->term_id;
			continue;
		}
		$made = wp_insert_term(
			$def[0],
			Taxonomies\SPECIALTY,
			array(
				'slug'        => $slug,
				'description' => $def[2],
			)
		);
		if ( ! is_wp_error( $made ) ) {
			$ids[ $slug ] = (int) $made['term_id'];
		}
	}
	return $ids;
}

/** The specialty slugs whose keywords appear in the haystack. */
function tags_for( string $haystack ): array {
	$haystack = strtolower( $haystack );
	$slugs    = array();
	foreach ( MAP as $slug => $def ) {
		if ( preg_match( '/' . $def[1] . '/i', $haystack ) ) {
			$slugs[] = $slug;
		}
	}
	return $slugs;
}

/** Everything about a listing worth matching against, as one string. */
function haystack_for( int $post_id ): string {
	$parts   = array( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
	$parts[] = (string) get_post_field( 'post_excerpt', $post_id, 'raw' );

	$services = function_exists( 'get_field' ) ? (array) get_field( 'services', $post_id ) : array();
	foreach ( $services as $row ) {
		$parts[] = (string) ( $row['name'] ?? '' );
	}
	return implode( ' ', $parts );
}

/**
 * Assign matching specialty terms to one listing. Replaces the previous
 * specialty set so a re-run converges rather than accumulates.
 *
 * @return string[] The slugs assigned.
 */
function tag_post( int $post_id ): array {
	ensure_terms();
	$slugs = tags_for( haystack_for( $post_id ) );
	wp_set_object_terms( $post_id, $slugs, Taxonomies\SPECIALTY );
	return $slugs;
}
