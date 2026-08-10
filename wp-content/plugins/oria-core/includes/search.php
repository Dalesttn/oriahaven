<?php
/**
 * Site search support: the words people actually type, and what they
 * failed to find.
 *
 * The suggestion list itself is built in the browser from ORIA_DATA,
 * which every page already carries — so typing is instant. This file
 * supplies the two things the browser can't work out for itself: a map
 * of everyday words onto our taxonomy, and somewhere to record searches
 * that returned nothing.
 *
 * Deliberately absent from the synonym map: symptoms and conditions.
 * Sending "anxiety" or "back pain" to a modality would amount to saying
 * that modality treats it, which is a therapeutic claim the directory
 * does not make on anyone's behalf. Modalities and services only.
 */

declare(strict_types=1);

namespace Oria\Core\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MISS_OPTION = 'oria_search_misses';
const MISS_MAX    = 200;

/**
 * Everyday phrasing => the specialty slugs it should find.
 *
 * Only worth an entry when a plain substring match would miss: "sauna"
 * already finds "Infrared sauna" on its own, whereas "ice bath" finds
 * "Ice bath & cold plunge" only because it is listed here.
 *
 * Aliases of three characters or fewer match only on an exact query, so
 * "pt" doesn't fire on every word containing those letters.
 */
const SYNONYMS = array(
	// Recovery
	'ice bath'            => array( 'cold-plunge' ),
	'ice baths'           => array( 'cold-plunge' ),
	'cold water'          => array( 'cold-plunge' ),
	'plunge'              => array( 'cold-plunge' ),
	'contrast therapy'    => array( 'cold-plunge', 'infrared-sauna' ),
	'cryo'                => array( 'cryotherapy' ),
	'hbot'                => array( 'hyperbaric-oxygen' ),
	'normatec'            => array( 'compression' ),
	'recovery'            => array( 'compression', 'cold-plunge', 'infrared-sauna', 'cryotherapy' ),

	// Float & sound
	'floatation'          => array( 'float-therapy' ),
	'flotation'           => array( 'float-therapy' ),
	'sensory deprivation' => array( 'float-therapy' ),
	'tank'                => array( 'float-therapy' ),
	'sound bath'          => array( 'sound-healing' ),
	'gong'                => array( 'sound-healing' ),
	'singing bowls'       => array( 'sound-healing' ),

	// Movement
	'pt'                  => array( 'personal-training' ),
	'personal trainer'    => array( 'personal-training' ),
	'gym'                 => array( 'personal-training', 'functional-fitness' ),
	'reformer'            => array( 'pilates' ),
	'yin'                 => array( 'yoga' ),
	'vinyasa'             => array( 'yoga' ),
	'hatha'               => array( 'yoga' ),
	'qigong'              => array( 'tai-chi' ),
	'chi kung'            => array( 'tai-chi' ),
	'stretching'          => array( 'stretch-therapy' ),
	'aqua aerobics'       => array( 'aqua-fitness' ),
	'ep'                  => array( 'exercise-physiology' ),

	// Bodywork
	'deep tissue'         => array( 'deep-tissue' ),
	'sports massage'      => array( 'sports-massage' ),
	'lymphatic'           => array( 'lymphatic-drainage' ),
	'bowen'               => array( 'bowen-therapy' ),
	'chiro'               => array( 'chiropractic' ),
	'osteo'               => array( 'osteopathy' ),
	'physio'              => array( 'physiotherapy' ),
	'ot'                  => array( 'occupational-therapy' ),

	// Natural therapies
	'tcm'                 => array( 'chinese-medicine' ),
	'herbalist'           => array( 'herbal-medicine' ),
	'naturopath'          => array( 'naturopathy' ),
	'dietitian'           => array( 'nutrition' ),
	'nutritionist'        => array( 'nutrition' ),
	'needling'            => array( 'dry-needling', 'acupuncture' ),
	'hypnosis'            => array( 'hypnotherapy' ),

	// Energy & spiritual
	'crystals'            => array( 'crystal-healing' ),
	'chakras'             => array( 'chakra-balancing' ),
	'shamanic'            => array( 'shamanic-healing' ),
	'aura'                => array( 'aura-clearing' ),

	// Family & women's health
	'breastfeeding'       => array( 'lactation' ),
	'ibclc'               => array( 'lactation' ),
	'prenatal'            => array( 'pregnancy-yoga' ),
	'antenatal'           => array( 'pregnancy-yoga', 'midwifery' ),
	'midwife'             => array( 'midwifery' ),
	'ivf'                 => array( 'fertility-support' ),
	'conception'          => array( 'fertility-support' ),
	'pelvic floor'        => array( 'womens-health' ),
	'birth'               => array( 'doula', 'midwifery' ),
	'baby sleep'          => array( 'parenting-support' ),

	// Nature & outdoors
	'shinrin'             => array( 'forest-bathing' ),
	'shinrin-yoku'        => array( 'forest-bathing' ),
	'horses'              => array( 'equine-therapy' ),
	'horse riding'        => array( 'equine-therapy' ),
	'surfing'             => array( 'surf-therapy' ),
	'bushwalking'         => array( 'hiking' ),
	'bush walk'           => array( 'hiking' ),
	'walking group'       => array( 'wellness-meetups' ),

	// Community
	'aa'                  => array( 'recovery-meetings' ),
	'smart recovery'      => array( 'recovery-meetings' ),
	'12 step'             => array( 'recovery-meetings' ),
	'mens group'          => array( 'mens-groups' ),
	'womens circle'       => array( 'womens-circles' ),
	'support group'       => array( 'support-groups' ),
	'retreat'             => array( 'wellness-retreats' ),
);

function bootstrap(): void {
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\payload', 20 );
	add_action( 'rest_api_init', __NAMESPACE__ . '\routes' );
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_search_log_clear', __NAMESPACE__ . '\clear' );
}

/** Hand the browser the synonym map and where to report a dead end. */
function payload(): void {
	if ( ! wp_script_is( 'oria-app', 'enqueued' ) ) {
		return;
	}
	wp_add_inline_script(
		'oria-app',
		'window.ORIA_SEARCH = ' . wp_json_encode(
			array(
				'synonyms' => SYNONYMS,
				'miss'     => rest_url( 'oria/v1/search-miss' ),
				// Logged-in visitors send a cookie, and WordPress rejects
				// cookie-authenticated REST calls without a nonce even on a
				// public route.
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'directory'=> get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ),
			)
		) . ';',
		'before'
	);
}

function routes(): void {
	register_rest_route(
		'oria/v1',
		'/search-miss',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array( 'q' => array( 'type' => 'string', 'required' => true ) ),
			'callback'            => __NAMESPACE__ . '\record',
		)
	);
}

/** Record a search that found nothing. Counts only — no visitor data. */
function record( \WP_REST_Request $req ): \WP_REST_Response {
	$q = strtolower( trim( sanitize_text_field( (string) $req['q'] ) ) );
	$q = preg_replace( '/\s+/', ' ', $q ) ?? $q;

	// Too short to act on, or long enough to be junk.
	if ( mb_strlen( $q ) < 2 || mb_strlen( $q ) > 60 ) {
		return new \WP_REST_Response( null, 204 );
	}

	$misses = (array) get_option( MISS_OPTION, array() );
	$entry  = $misses[ $q ] ?? array( 'n' => 0, 't' => 0 );
	$misses[ $q ] = array( 'n' => (int) $entry['n'] + 1, 't' => time() );

	// Keep the list bounded: drop the rarest when it grows too long.
	if ( count( $misses ) > MISS_MAX ) {
		uasort( $misses, static fn( $a, $b ) => $b['n'] <=> $a['n'] );
		$misses = array_slice( $misses, 0, MISS_MAX, true );
	}

	update_option( MISS_OPTION, $misses, false );
	return new \WP_REST_Response( null, 204 );
}

function menu(): void {
	add_submenu_page(
		'edit.php?post_type=listing',
		__( 'Search log', 'oria' ),
		__( 'Search log', 'oria' ),
		'manage_options',
		'oria-search-log',
		__NAMESPACE__ . '\screen'
	);
}

function screen(): void {
	$misses = (array) get_option( MISS_OPTION, array() );
	uasort( $misses, static fn( $a, $b ) => array( $b['n'], $b['t'] ) <=> array( $a['n'], $a['t'] ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Searches that found nothing', 'oria' ); ?></h1>
		<p style="max-width:62ch"><?php esc_html_e( 'What visitors typed into the site search and got no results for, in their own words. The top of this list is your best guide to which listings, categories or journal articles to add next.', 'oria' ); ?></p>

		<?php if ( ! $misses ) : ?>
			<p><em><?php esc_html_e( 'Nothing recorded yet — either every search is finding something, or the site is still quiet.', 'oria' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:44rem">
				<thead><tr>
					<th><?php esc_html_e( 'Search term', 'oria' ); ?></th>
					<th style="width:7rem"><?php esc_html_e( 'Times', 'oria' ); ?></th>
					<th style="width:12rem"><?php esc_html_e( 'Last searched', 'oria' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $misses as $q => $d ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $q ); ?></strong></td>
						<td><?php echo (int) $d['n']; ?></td>
						<td><?php echo esc_html( human_time_diff( (int) $d['t'] ) . __( ' ago', 'oria' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1.5rem">
				<?php wp_nonce_field( 'oria_search_log_clear' ); ?>
				<input type="hidden" name="action" value="oria_search_log_clear">
				<button class="button"><?php esc_html_e( 'Clear the log', 'oria' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function clear(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.' );
	}
	check_admin_referer( 'oria_search_log_clear' );
	delete_option( MISS_OPTION );
	wp_safe_redirect( admin_url( 'edit.php?post_type=listing&page=oria-search-log' ) );
	exit;
}
