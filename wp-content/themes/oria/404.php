<?php
/**
 * Not found.
 *
 * A directory produces dead links faster than most sites: a practice
 * renames itself and its slug moves, a practice closes and its URL stays
 * in Google for months, someone shares a link with a full stop stuck to
 * the end of it. Until now all of that landed on index.php, which said
 * "Archives" and "Nothing here yet" — technically a 404, practically a
 * dead end.
 *
 * So this page tries to work out what they wanted. The address itself is
 * evidence: /listing/frementle-yoga-centre/ is a misspelling of something
 * we have, and an old slug usually still shares most of its words with
 * the new one. Where the guess is confident enough to be worth showing,
 * the listing is offered directly; where it isn't, the page falls back to
 * the categories, which is the next best thing to a search box.
 *
 * @package Oria
 */

declare(strict_types=1);

use function Oria\Theme\arrow;
use function Oria\Theme\search_index;

/**
 * Listings whose names look like the address that was asked for.
 *
 * Scored two ways because the two failure modes look different. A renamed
 * practice shares whole words with what it used to be called, so words in
 * common carry most of the weight; a typo shares no whole word at all, so
 * overall string similarity is there to catch it. Nothing is shown below
 * the threshold — a wrong guess is worse than no guess, because it sends
 * someone confidently to the wrong practice.
 *
 * @return array<int, array{name: string, url: string, suburb: string, cat: string}>
 */
if ( ! function_exists( 'oria_404_guesses' ) ) :
function oria_404_guesses( string $path ): array {
	$slug = trim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' );
	$slug = (string) substr( strrchr( '/' . $slug, '/' ), 1 );
	if ( strlen( $slug ) < 4 ) {
		return array();
	}

	$asked = array_values( array_filter( preg_split( '/[^a-z0-9]+/', strtolower( $slug ) ) ?: array(), static fn( string $w ): bool => strlen( $w ) > 2 ) );
	if ( ! $asked ) {
		return array();
	}

	$index = function_exists( 'Oria\Theme\search_index' ) ? search_index() : array();
	$rows  = is_array( $index['listings'] ?? null ) ? $index['listings'] : array();

	// Listing rows carry the category as a slug, for filtering; the page
	// needs the name people would recognise.
	$cat_names = array();
	foreach ( ( is_array( $index['categories'] ?? null ) ? $index['categories'] : array() ) as $cat ) {
		$cat_names[ (string) ( $cat['id'] ?? '' ) ] = (string) ( $cat['name'] ?? '' );
	}

	$scored = array();
	foreach ( $rows as $row ) {
		$name  = (string) ( $row['name'] ?? '' );
		$words = array_values( array_filter( preg_split( '/[^a-z0-9]+/', strtolower( $name ) ) ?: array(), static fn( string $w ): bool => strlen( $w ) > 2 ) );
		if ( ! $words ) {
			continue;
		}

		$shared = count( array_intersect( $asked, $words ) ) / max( count( $asked ), count( $words ) );

		$percent = 0.0;
		similar_text( strtolower( $slug ), strtolower( str_replace( ' ', '-', $name ) ), $percent );

		$score = ( $shared * 0.7 ) + ( ( $percent / 100 ) * 0.3 );
		if ( $score < 0.45 ) {
			continue;
		}
		$scored[] = array(
			'score'  => $score,
			'name'   => $name,
			'url'    => (string) ( $row['url'] ?? '' ),
			'suburb' => (string) ( $row['suburb'] ?? '' ),
			'cat'    => $cat_names[ (string) ( $row['cat'] ?? '' ) ] ?? '',
		);
	}

	usort( $scored, static fn( array $a, array $b ): int => $b['score'] <=> $a['score'] );
	return array_slice( $scored, 0, 3 );
}
endif;

$oria_guesses = oria_404_guesses( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

$oria_practices = get_terms(
	array(
		'taxonomy'   => 'practice',
		'hide_empty' => true,
		'number'     => 8,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;

get_header();
?>

<section class="wrap pagehead">
	<span class="micro"><?php esc_html_e( 'Page not found', 'oria' ); ?></span>
	<h1 class="h1 pagehead__title"><?php esc_html_e( 'This one isn\'t here.', 'oria' ); ?></h1>
	<p class="lede pagehead__lede">
		<?php
		echo esc_html(
			$oria_guesses
				? __( 'The address doesn\'t match anything we have — though it looks close to something that does.', 'oria' )
				: __( 'The address might have a typo in it, or the practice may have changed its name or closed since the link was made.', 'oria' )
		);
		?>
	</p>
</section>

<?php if ( $oria_guesses ) : ?>
<section class="wrap section section--top-flush">
	<h2 class="h3" style="margin-bottom:var(--s-5)"><?php esc_html_e( 'Did you mean', 'oria' ); ?></h2>
	<div class="notfound__guesses">
		<?php foreach ( $oria_guesses as $oria_guess ) : ?>
			<a class="notfound__guess" href="<?php echo esc_url( $oria_guess['url'] ); ?>" data-oria-event="notfound_guess">
				<span class="notfound__guess__name"><?php echo esc_html( $oria_guess['name'] ); ?></span>
				<?php
				$oria_meta = trim( implode( '  ·  ', array_filter( array( $oria_guess['suburb'], $oria_guess['cat'] ) ) ) );
				?>
				<?php if ( $oria_meta ) : ?>
					<span class="notfound__guess__meta"><?php echo esc_html( $oria_meta ); ?></span>
				<?php endif; ?>
				<?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<section class="wrap section<?php echo $oria_guesses ? '' : ' section--top-flush'; ?>">
	<h2 class="h3" style="margin-bottom:var(--s-3)">
		<?php echo esc_html( $oria_guesses ? __( 'Or start somewhere else', 'oria' ) : __( 'Where would you like to go?', 'oria' ) ); ?>
	</h2>
	<p class="muted" style="margin-bottom:var(--s-6);max-width:52ch">
		<?php esc_html_e( 'Every practice in the directory is checked by hand, with real timetables and prices.', 'oria' ); ?>
	</p>

	<div class="row" style="gap:var(--s-3);flex-wrap:wrap;margin-bottom:var(--s-7)">
		<a class="btn btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>">
			<?php esc_html_e( 'Browse every practice', 'oria' ); ?><?php echo arrow(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
		<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to the home page', 'oria' ); ?>
		</a>
	</div>

	<?php if ( $oria_practices ) : ?>
		<div class="notfound__cats">
			<?php foreach ( $oria_practices as $oria_p ) : ?>
				<a class="notfound__cat" href="<?php echo esc_url( (string) get_term_link( $oria_p ) ); ?>">
					<?php echo esc_html( $oria_p->name ); ?>
					<span class="notfound__cat__count"><?php echo (int) $oria_p->count; ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
