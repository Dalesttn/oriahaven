<?php
/**
 * The /perth/ hub — the directory's table of contents.
 *
 * Every practice, every specialty with listings behind it, and every
 * suburb we cover, as plain crawlable links. No filters, no JavaScript
 * engine: this page exists to be read top to bottom by a person deciding
 * where to start, and by a crawler deciding what else is worth fetching.
 */

declare(strict_types=1);

get_header();

$oria_practices = \Oria\Core\Hub\practices();
$oria_specs     = \Oria\Core\Hub\specialties( 2 );
$oria_areas     = \Oria\Core\Hub\areas();
$oria_total     = \Oria\Core\Hub\count_listings();

// ItemList of the categories: the hub's main content is the list itself,
// so this is the one page where list markup describes the whole page.
$oria_ld = array(
	'@context'        => 'https://schema.org',
	'@type'           => 'CollectionPage',
	'name'            => __( 'Wellness in Perth', 'oria' ),
	'url'             => home_url( '/perth/' ),
	'mainEntity'      => array(
		'@type'           => 'ItemList',
		'numberOfItems'   => count( $oria_practices ),
		'itemListElement' => array_values(
			array_map(
				static function ( $t, $i ) {
					return array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => \Oria\Theme\tname( $t ),
						'url'      => get_term_link( $t ),
					);
				},
				$oria_practices,
				array_keys( $oria_practices )
			)
		),
	),
);
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Perth', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem">
		<span class="micro"><?php esc_html_e( 'Directory index', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Wellness in Perth', 'oria' ); ?></h1>
		<p class="lede" style="max-width:60ch;margin-top:.75rem">
			<?php
			printf(
				esc_html__( 'Every practice we list, sorted the three ways people actually look: by what they do, by the exact modality, and by where they are. %s practices across the Perth metro, each one checked by hand.', 'oria' ),
				esc_html( number_format_i18n( $oria_total ) )
			);
			?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<h2 class="h3"><?php esc_html_e( 'Browse by practice', 'oria' ); ?></h2>
	<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'The broad doors into the directory.', 'oria' ); ?></p>
	<div class="hubgrid">
		<?php foreach ( $oria_practices as $oria_p ) : ?>
			<a class="hubcard" href="<?php echo esc_url( get_term_link( $oria_p ) ); ?>">
				<span class="hubcard__name"><?php echo esc_html( \Oria\Theme\tname( $oria_p ) ); ?></span>
				<span class="hubcard__n"><?php echo esc_html( number_format_i18n( $oria_p->count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php if ( $oria_specs ) : ?>
<section class="wrap section section--top-flush">
	<h2 class="h3"><?php esc_html_e( 'Browse by modality', 'oria' ); ?></h2>
	<p class="hint" style="margin-bottom:1rem"><?php esc_html_e( 'The precise thing you might be searching for.', 'oria' ); ?></p>
	<div class="chips">
		<?php foreach ( $oria_specs as $oria_s ) : ?>
			<a class="pill" href="<?php echo esc_url( get_term_link( $oria_s ) ); ?>">
				<?php echo esc_html( \Oria\Theme\tname( $oria_s ) ); ?> (<?php echo esc_html( number_format_i18n( $oria_s->count ) ); ?>)
			</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $oria_areas ) : ?>
<section class="wrap section section--top-flush">
	<h2 class="h3"><?php esc_html_e( 'Browse by area', 'oria' ); ?></h2>
	<p class="hint" style="margin-bottom:1.25rem"><?php esc_html_e( 'Perth is big. Start with the side of town you can get to.', 'oria' ); ?></p>
	<div class="hubareas">
		<?php foreach ( $oria_areas as $oria_a ) : ?>
			<div class="hubarea">
				<h3 class="hubarea__h">
					<a href="<?php echo esc_url( get_term_link( $oria_a['region'] ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_a['region'] ) ); ?></a>
				</h3>
				<?php if ( $oria_a['suburbs'] ) : ?>
					<ul class="hubarea__list">
						<?php foreach ( $oria_a['suburbs'] as $oria_sub ) : ?>
							<li>
								<a href="<?php echo esc_url( get_term_link( $oria_sub ) ); ?>"><?php echo esc_html( \Oria\Theme\tname( $oria_sub ) ); ?></a>
								<span class="hubarea__n"><?php echo esc_html( number_format_i18n( $oria_sub->count ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<section class="wrap section section--top-flush">
	<h2 class="h3"><?php esc_html_e( 'Elsewhere on Oria Haven', 'oria' ); ?></h2>
	<div class="chips" style="margin-top:1rem">
		<a class="pill" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>"><?php esc_html_e( 'The full directory', 'oria' ); ?></a>
		<a class="pill" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( "What's on in Perth", 'oria' ); ?></a>
		<a class="pill" href="<?php echo esc_url( home_url( '/journal/' ) ); ?>"><?php esc_html_e( 'The Journal', 'oria' ); ?></a>
		<a class="pill" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your practice', 'oria' ); ?></a>
	</div>
</section>

<script type="application/ld+json"><?php echo wp_json_encode( $oria_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

<?php
get_footer();
