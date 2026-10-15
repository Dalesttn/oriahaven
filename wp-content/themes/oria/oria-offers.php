<?php
/**
 * /offers/ and /offers/{activity}/ (oria-core/includes/offers-hub.php).
 *
 * Order down the page, per the Oct 2026 brief: crumbs, a compact header,
 * activity navigation, the filter toolbar with a live count, the results
 * (signup after the sixth), then on activity pages a comparison table, and
 * the guide, questions, method and related links below.
 *
 * Every filter is a plain GET form, so the page works without JavaScript:
 * the server marks non-matching cards hidden and sorts. offers.js then does
 * the same in place, moves the toolbar into a bottom sheet on small screens
 * and adds Compare. Cards are template-parts/offers/card.php.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Core\OffersHub as Hub;

$oria_ver = static fn( string $rel ): string => (string) filemtime( get_theme_file_path( $rel ) );
wp_enqueue_style( 'oria-offers', get_template_directory_uri() . '/assets/css/offers.css', array(), $oria_ver( 'assets/css/offers.css' ) );
wp_enqueue_script( 'oria-offers', get_template_directory_uri() . '/assets/js/offers.js', array(), $oria_ver( 'assets/js/offers.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );

$oria_act   = Hub\current_activity();
$oria_page  = Hub\page( $oria_act );
$oria_all   = Hub\page_rows( $oria_act );
$oria_f     = Hub\filters();
$oria_sort  = '' !== $oria_f['sort'] ? $oria_f['sort'] : 'recommended';
$oria_rows  = Hub\sorted( $oria_all, $oria_f['sort'] );
$oria_shown = array_values( array_filter( $oria_rows, static fn( array $r ): bool => Hub\matches( $r, $oria_f ) ) );
$oria_opts  = Hub\options( $oria_all );
$oria_acts  = Hub\activities();
$oria_total = count( Hub\rows() );
$oria_self  = Hub\self_url();
$oria_place = '' !== $oria_act ? 'offers_' . $oria_act : 'hub';
$oria_label = '' !== $oria_act ? Hub\label( $oria_act ) : '';
$oria_noun  = (string) ( $oria_page['noun'] ?? __( 'offers', 'oria' ) );
$oria_any   = (bool) array_filter( array_diff_key( $oria_f, array( 'sort' => 1 ) ) );
$oria_picks = Hub\picks( $oria_all );
$oria_intro = Hub\fill( (string) ( $oria_page['intro'] ?? '' ), $oria_all );

$oria_filter_names = array(
	'area'   => __( 'Area', 'oria' ),
	'budget' => __( 'Budget', 'oria' ),
	'who'    => __( 'Who can buy', 'oria' ),
	'format' => __( 'Offer type', 'oria' ),
	'when'   => __( 'When', 'oria' ),
);
$oria_active = array();
foreach ( $oria_filter_names as $oria_k => $oria_n ) {
	if ( '' !== $oria_f[ $oria_k ] && isset( $oria_opts[ $oria_k ][ $oria_f[ $oria_k ] ] ) ) {
		$oria_active[ $oria_k ] = $oria_opts[ $oria_k ][ $oria_f[ $oria_k ] ];
	}
}
$oria_without = static function ( string $k ) use ( $oria_f, $oria_self ): string {
	$q = array_filter( $oria_f );
	unset( $q[ $k ] );
	return add_query_arg( $q, $oria_self );
};

/* Everything offers.js needs to build the comparison, once, not per card. */
$oria_data = array();
foreach ( $oria_all as $oria_r ) {
	$oria_x = (array) ( $oria_r['offer']['extra'] ?? array() );
	$oria_data[ (string) $oria_r['id'] ] = array(
		'name'     => \Oria\Theme\ptitle( get_post( $oria_r['id'] ) ),
		'url'      => (string) get_permalink( $oria_r['id'] ),
		'suburb'   => (string) $oria_r['suburb'],
		'title'    => (string) $oria_r['offer']['title'],
		'price'    => (string) ( $oria_x['price'] ?? '' ),
		'basis'    => (string) ( $oria_x['basis'] ?? '' ),
		'fixed'    => null !== $oria_r['amount'],
		'includes' => array_values( (array) ( $oria_x['includes'] ?? array() ) ),
		'who'      => (string) ( $oria_x['eligibility'] ?? '' ),
		'until'    => '' !== (string) $oria_r['offer']['until'] ? mysql2date( 'j M Y', (string) $oria_r['offer']['until'] ) : '',
		'renewal'  => (string) ( $oria_x['renewal'] ?? '' ),
	);
}

get_header();
?>

<div class="ofx" data-ofx data-place="<?php echo esc_attr( $oria_place ); ?>" data-total="<?php echo (int) count( $oria_all ); ?>">
	<div class="ofx__wrap">
		<nav class="crumbs ofx__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<?php if ( '' !== $oria_act ) : ?>
				<a href="<?php echo esc_url( Hub\url() ); ?>"><?php esc_html_e( 'Offers', 'oria' ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( $oria_label ); ?></span>
			<?php else : ?>
				<span aria-current="page"><?php esc_html_e( 'Offers', 'oria' ); ?></span>
			<?php endif; ?>
		</nav>

		<header class="ofx-hero">
			<p class="ofx-hero__eyebrow"><?php esc_html_e( 'The Perth wellness edit', 'oria' ); ?></p>
			<h1 class="ofx-hero__title"><?php echo esc_html( Hub\h1() ); ?></h1>
			<?php if ( '' !== $oria_intro ) : ?>
				<p class="ofx-hero__lede"><?php echo esc_html( $oria_intro ); ?></p>
			<?php endif; ?>
			<p class="ofx-hero__meta">
				<span><?php echo esc_html( sprintf( /* translators: %d: number of offers */ _n( '%d current offer', '%d current offers', count( $oria_all ), 'oria' ), count( $oria_all ) ) ); ?></span>
				<span><?php esc_html_e( 'Checked on each business’s own site', 'oria' ); ?></span>
				<a href="#ofx-method"><?php esc_html_e( 'How we check', 'oria' ); ?></a>
			</p>
		</header>

		<?php if ( count( $oria_acts ) > 1 ) : ?>
			<nav class="ofx-cats" aria-label="<?php esc_attr_e( 'Offer categories', 'oria' ); ?>">
				<ul class="ofx-cats__row">
					<li><a class="ofx-cats__link" href="<?php echo esc_url( Hub\url() ); ?>" data-oria-event="offers_category"<?php echo '' === $oria_act ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All offers', 'oria' ); ?> <span class="ofx-cats__n"><?php echo (int) $oria_total; ?></span></a></li>
					<?php foreach ( $oria_acts as $oria_c ) : ?>
						<li><a class="ofx-cats__link" href="<?php echo esc_url( Hub\url( $oria_c['slug'] ) ); ?>" data-oria-event="offers_category"<?php echo $oria_act === $oria_c['slug'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_c['name'] ); ?> <span class="ofx-cats__n"><?php echo (int) $oria_c['count']; ?></span></a></li>
					<?php endforeach; ?>
				</ul>
				<details class="ofx-cats__all">
					<summary>
						<span class="ofx-cats__now"><?php echo esc_html( '' !== $oria_act ? $oria_label : __( 'All offers', 'oria' ) ); ?></span>
						<span class="ofx-cats__change"><?php esc_html_e( 'All categories', 'oria' ); ?></span>
					</summary>
					<ul>
						<li><a href="<?php echo esc_url( Hub\url() ); ?>" data-oria-event="offers_category"<?php echo '' === $oria_act ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All offers', 'oria' ); ?> <span class="ofx-cats__n"><?php echo (int) $oria_total; ?></span></a></li>
						<?php foreach ( $oria_acts as $oria_c ) : ?>
							<li><a href="<?php echo esc_url( Hub\url( $oria_c['slug'] ) ); ?>" data-oria-event="offers_category"<?php echo $oria_act === $oria_c['slug'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $oria_c['name'] ); ?> <span class="ofx-cats__n"><?php echo (int) $oria_c['count']; ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</details>
			</nav>
		<?php endif; ?>

		<?php if ( $oria_all ) : ?>
		<div class="ofx-bar" data-ofx-bar>
			<button type="button" class="ofx-bar__open" data-ofx-open hidden aria-haspopup="dialog" aria-controls="ofx-sheet">
				<svg aria-hidden="true" viewBox="0 0 20 20" width="18" height="18"><path d="M3 5h14M6 10h8M9 15h2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
				<?php esc_html_e( 'Filters', 'oria' ); ?>
				<span class="ofx-bar__badge" data-ofx-badge<?php echo $oria_active ? '' : ' hidden'; ?>><?php echo (int) count( $oria_active ); ?></span>
			</button>

			<dialog class="ofx-sheet" id="ofx-sheet" open aria-labelledby="ofx-sheet-title" data-ofx-sheet>
				<form class="ofx-form" id="ofx-form" method="get" action="<?php echo esc_url( $oria_self ); ?>" data-ofx-form>
					<div class="ofx-sheet__head">
						<h2 class="ofx-sheet__title" id="ofx-sheet-title"><?php esc_html_e( 'Filter offers', 'oria' ); ?></h2>
						<button type="button" class="ofx-sheet__close" data-ofx-close hidden><span class="sr-only"><?php esc_html_e( 'Close filters', 'oria' ); ?></span><svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><path d="M5 5l10 10M15 5 5 15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
					</div>
					<div class="ofx-form__fields">
						<?php foreach ( $oria_filter_names as $oria_k => $oria_n ) : ?>
							<?php if ( count( $oria_opts[ $oria_k ] ) < ( 'when' === $oria_k ? 1 : 2 ) && '' === $oria_f[ $oria_k ] ) { continue; } ?>
							<div class="ofx-field">
								<label for="ofx-<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_n ); ?></label>
								<select id="ofx-<?php echo esc_attr( $oria_k ); ?>" name="<?php echo esc_attr( $oria_k ); ?>" data-ofx-filter="<?php echo esc_attr( $oria_k ); ?>">
									<option value=""><?php esc_html_e( 'Any', 'oria' ); ?></option>
									<?php foreach ( $oria_opts[ $oria_k ] as $oria_v => $oria_l ) : ?>
										<option value="<?php echo esc_attr( (string) $oria_v ); ?>"<?php selected( $oria_f[ $oria_k ], (string) $oria_v ); ?>><?php echo esc_html( (string) $oria_l ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="ofx-form__actions">
						<a class="ofx-form__reset" href="<?php echo esc_url( $oria_sort === 'recommended' ? $oria_self : add_query_arg( 'sort', $oria_sort, $oria_self ) ); ?>" data-ofx-reset><?php esc_html_e( 'Reset', 'oria' ); ?></a>
						<button type="submit" class="ofx-form__apply" data-ofx-apply><?php esc_html_e( 'Apply filters', 'oria' ); ?></button>
					</div>
				</form>
			</dialog>

			<div class="ofx-field ofx-field--sort">
				<label for="ofx-sort"><?php esc_html_e( 'Sort', 'oria' ); ?></label>
				<select id="ofx-sort" name="sort" form="ofx-form" data-ofx-sort>
					<?php foreach ( Hub\SORTS as $oria_v => $oria_l ) : ?>
						<option value="<?php echo esc_attr( $oria_v ); ?>"<?php selected( $oria_sort, $oria_v ); ?>><?php echo esc_html( $oria_l ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<p class="ofx-bar__count" role="status" aria-live="polite" data-ofx-count>
				<?php
				echo esc_html(
					count( $oria_shown ) === count( $oria_all )
						? sprintf( /* translators: %d: number */ _n( '%d offer', '%d offers', count( $oria_all ), 'oria' ), count( $oria_all ) )
						: sprintf( /* translators: 1: shown, 2: total */ __( '%1$d of %2$d offers', 'oria' ), count( $oria_shown ), count( $oria_all ) )
				);
				?>
			</p>
		</div>

		<ul class="ofx-chips" data-ofx-chips aria-label="<?php esc_attr_e( 'Active filters', 'oria' ); ?>"<?php echo $oria_active ? '' : ' hidden'; ?>>
			<?php foreach ( $oria_active as $oria_k => $oria_l ) : ?>
				<li><a class="ofx-chip" href="<?php echo esc_url( $oria_without( $oria_k ) ); ?>" data-ofx-chip="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_filter_names[ $oria_k ] . ': ' . $oria_l ); ?><span class="sr-only"> <?php esc_html_e( '(remove)', 'oria' ); ?></span><span aria-hidden="true" class="ofx-chip__x">×</span></a></li>
			<?php endforeach; ?>
			<li><a class="ofx-chips__clear" href="<?php echo esc_url( $oria_self ); ?>" data-ofx-clear><?php esc_html_e( 'Clear all', 'oria' ); ?></a></li>
		</ul>
		<p class="ofx-order-note"><?php esc_html_e( 'Recommended order: promoted offers first, always labelled, then the most recently checked, then the soonest to end.', 'oria' ); ?></p>
		<?php endif; ?>

		<?php if ( $oria_picks ) : ?>
			<aside class="ofx-picks" aria-labelledby="ofx-picks-title" data-ofx-picks<?php echo $oria_any ? ' hidden' : ''; ?>>
				<h2 class="ofx-picks__title" id="ofx-picks-title"><?php echo esc_html( 3 === count( $oria_picks ) ? __( 'Three to start with', 'oria' ) : __( 'Two to start with', 'oria' ) ); ?></h2>
				<ol class="ofx-picks__list">
					<?php foreach ( $oria_picks as $oria_p ) : ?>
						<?php $oria_pr = $oria_p['row']; ?>
						<li class="ofx-pick">
							<p class="ofx-pick__label"><?php echo esc_html( $oria_p['label'] ); ?></p>
							<p class="ofx-pick__name"><a href="#offer-<?php echo (int) $oria_pr['id']; ?>"><?php echo esc_html( \Oria\Theme\ptitle( get_post( $oria_pr['id'] ) ) ); ?></a></p>
							<p class="ofx-pick__offer"><?php echo esc_html( (string) $oria_pr['offer']['title'] ); ?></p>
							<p class="ofx-pick__why"><?php echo esc_html( $oria_p['why'] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ol>
			</aside>
		<?php endif; ?>

		<?php if ( $oria_all ) : ?>
			<ul class="ofx-grid" id="ofx-results" data-ofx-grid>
				<?php
				$oria_i       = 0;
				$oria_visible = 0;
				$oria_signed  = false;
				foreach ( $oria_rows as $oria_r ) {
					$oria_match = Hub\matches( $oria_r, $oria_f );
					get_template_part(
						'template-parts/offers/card',
						null,
						array(
							'row'    => $oria_r,
							'place'  => $oria_place,
							'eager'  => $oria_match && $oria_visible < 3,
							'hidden' => ! $oria_match,
						)
					);
					if ( $oria_match ) {
						++$oria_visible;
					}
					if ( 6 === $oria_visible && ! $oria_signed ) {
						get_template_part( 'template-parts/offers/signup', null, array( 'interest' => $oria_act ) );
						$oria_signed = true;
					}
					++$oria_i;
				}
				if ( ! $oria_signed ) {
					get_template_part( 'template-parts/offers/signup', null, array( 'interest' => $oria_act ) );
				}
				?>
			</ul>

			<div class="ofx-empty" data-ofx-empty<?php echo $oria_shown ? ' hidden' : ''; ?>>
				<h2 class="ofx-empty__title"><?php esc_html_e( 'No offers match all of those filters', 'oria' ); ?></h2>
				<p data-ofx-empty-text>
					<?php
					echo esc_html(
						$oria_active
							? sprintf( /* translators: %s: list of filters */ __( 'You’re filtering by %s.', 'oria' ), implode( ', ', array_map( static fn( $k, $l ) => $oria_filter_names[ $k ] . ': ' . $l, array_keys( $oria_active ), $oria_active ) ) )
							: ''
					);
					?>
				</p>
				<p class="ofx-empty__go">
					<a class="ofx-btn" href="<?php echo esc_url( $oria_self ); ?>" data-ofx-clear><?php esc_html_e( 'Clear filters', 'oria' ); ?></a>
					<?php if ( '' !== $oria_act ) : ?>
						<a href="<?php echo esc_url( Hub\url() ); ?>"><?php esc_html_e( 'See every Perth offer', 'oria' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		<?php else : ?>
			<div class="ofx-empty">
				<h2 class="ofx-empty__title"><?php echo esc_html( sprintf( /* translators: %s: e.g. "yoga intro offers" */ __( 'No %s right now', 'oria' ), $oria_noun ) ); ?></h2>
				<p><?php esc_html_e( 'We check offers every month, and this page fills again as soon as a business advertises one. In the meantime, here are the offers running across Perth.', 'oria' ); ?></p>
				<p class="ofx-empty__go"><a class="ofx-btn" href="<?php echo esc_url( Hub\url() ); ?>"><?php esc_html_e( 'See every Perth offer', 'oria' ); ?></a></p>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $oria_act ) : ?>
			<?php get_template_part( 'template-parts/offers/calculator', null, array( 'rows' => $oria_all ) ); ?>
		<?php endif; ?>

		<?php if ( '' !== $oria_act && count( $oria_all ) > 1 ) : ?>
			<?php get_template_part( 'template-parts/offers/compare-table', null, array( 'rows' => $oria_rows, 'noun' => $oria_noun, 'place' => $oria_place ) ); ?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/offers/guide', null, array( 'page' => $oria_page, 'rows' => $oria_all, 'activity' => $oria_act ) ); ?>

		<?php
		// A long page asks once more at the end, where the inline form is far behind.
		if ( count( $oria_all ) > 9 ) {
			get_template_part( 'template-parts/offers/signup', null, array( 'interest' => $oria_act, 'end' => true ) );
		}
		?>
	</div>

	<div class="ofx-tray" data-ofx-tray hidden>
		<p class="ofx-tray__n" data-ofx-tray-n aria-live="polite"></p>
		<button type="button" class="ofx-btn ofx-btn--light" data-ofx-cmp-open><?php esc_html_e( 'Compare', 'oria' ); ?></button>
		<button type="button" class="ofx-tray__clear" data-ofx-cmp-clear><?php esc_html_e( 'Clear', 'oria' ); ?></button>
	</div>
	<dialog class="ofx-cmp" aria-labelledby="ofx-cmp-title" data-ofx-cmp>
		<div class="ofx-cmp__head">
			<h2 id="ofx-cmp-title"><?php esc_html_e( 'Compare offers', 'oria' ); ?></h2>
			<button type="button" class="ofx-sheet__close" data-ofx-cmp-close><span class="sr-only"><?php esc_html_e( 'Close comparison', 'oria' ); ?></span><svg aria-hidden="true" viewBox="0 0 20 20" width="20" height="20"><path d="M5 5l10 10M15 5 5 15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
		</div>
		<div class="ofx-cmp__body" data-ofx-cmp-body></div>
	</dialog>
	<script type="application/json" id="ofx-data"><?php echo wp_json_encode( $oria_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
</div>

<?php
get_footer();
