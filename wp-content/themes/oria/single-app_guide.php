<?php
/**
 * An editorial app collection — "10 best wellness apps in Australia".
 *
 * Read the way a shortlist is read: the table first, for somebody who
 * wants the answer and nothing else, then a section per pick for somebody
 * deciding between two of them. The number beside each heading is the
 * editor's order and says so; there is no score anywhere on the page.
 *
 * Every fact in a section — features, considerations, what it costs —
 * comes from the app itself, so this page cannot disagree with the app
 * page it links to.
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Engine;
use Oria\Apps\Guides;

get_header();

while ( have_posts() ) :
	the_post();

	$oria_id    = (int) get_the_ID();
	$oria_guide = Guides\guide( $oria_id );
	$oria_picks = $oria_guide['picks'];
	?>

	<?php
	/*
	 * The guide wears its own featured image, painted by CSS rather than
	 * carried as an <img>: the picture is decoration here, the headline
	 * already says what the page is, and a background is neither announced
	 * to a screen reader nor downloaded where it is never shown.
	 *
	 * No featured image, no custom property, no picture and no reserved
	 * height -- the header falls back to exactly what it was.
	 */
	$oria_hero = get_post_thumbnail_id() ? (string) wp_get_attachment_image_url( get_post_thumbnail_id(), 'oria-wide' ) : '';
	?>
	<div class="heroband heroband--stack<?php echo '' !== $oria_hero ? '' : ' heroband--bare'; ?>"
		<?php if ( '' !== $oria_hero ) : ?>style="--heroband-img:url('<?php echo esc_url( $oria_hero ); ?>')"<?php endif; ?>>
	<section class="wrap pagehead">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'Apps', 'oria' ); ?></a>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_guide['title'] ); ?></span>
		</nav>

		<div class="pagehead__copy">
			<h1 class="h1 pagehead__title"><?php echo esc_html( $oria_guide['title'] ); ?></h1>
			<?php if ( '' !== $oria_guide['subtitle'] ) : ?>
				<p class="lede pagehead__lede"><?php echo esc_html( $oria_guide['subtitle'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $oria_guide['checked'] ) : ?>
				<p class="micro gdchecked">
					<?php
					printf(
						/* translators: %s: a date */
						esc_html__( 'Last checked %s', 'oria' ),
						esc_html( date_i18n( 'j F Y', (int) strtotime( $oria_guide['checked'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</section>
	</div>

	<?php if ( '' !== $oria_guide['intro'] ) : ?>
		<section class="wrap section section--top-flush">
			<div class="prose gdintro">
				<?php echo wp_kses_post( wpautop( $oria_guide['intro'] ) ); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $oria_picks ) : ?>

		<?php
		/*
		 * The table. Wrapped in its own scroller so a narrow screen scrolls
		 * the table sideways instead of the whole page, and the "view"
		 * column jumps down the page rather than straight out to the app —
		 * somebody scanning a comparison has not decided yet.
		 */
		?>
		<section class="wrap section section--top-flush" aria-labelledby="gdtableTitle">
			<h2 class="h3" id="gdtableTitle"><?php esc_html_e( 'At a glance', 'oria' ); ?></h2>
			<div class="gdtable__scroll">
				<table class="gdtable">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'App', 'oria' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Best for', 'oria' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Focus', 'oria' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Free option', 'oria' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Platforms', 'oria' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Read more', 'oria' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $oria_picks as $oria_i => $oria_pick ) : ?>
							<?php $oria_app = $oria_pick['app']; ?>
							<tr>
								<th scope="row">
									<span class="gdtable__n"><?php echo (int) ( $oria_i + 1 ); ?></span>
									<a href="<?php echo esc_url( (string) $oria_app['url'] ); ?>"><?php echo esc_html( (string) $oria_app['title'] ); ?></a>
								</th>
								<td><?php echo esc_html( Guides\pick_line( $oria_pick ) ); ?></td>
								<td><?php echo esc_html( (string) ( $oria_app['cat_names'][0] ?? '—' ) ); ?></td>
								<td>
									<?php
									// "Free" and "a free tier" are different things and
									// the table says which, rather than a bare yes.
									if ( 'free' === $oria_app['pricing'] ) {
										esc_html_e( 'Free app', 'oria' );
									} elseif ( $oria_app['free'] ) {
										esc_html_e( 'Yes', 'oria' );
									} else {
										esc_html_e( 'No', 'oria' );
									}
									?>
								</td>
								<td><?php echo esc_html( Guides\platform_line( $oria_app ) ); ?></td>
								<td class="gdtable__go">
									<a href="#pick-<?php echo (int) ( $oria_i + 1 ); ?>"><?php esc_html_e( 'Read', 'oria' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( Guides\affiliate_in( $oria_picks ) ) : ?>
				<p class="gddisclosure"><?php echo esc_html( Data\disclosure() ); ?></p>
			<?php endif; ?>
		</section>

		<?php foreach ( $oria_picks as $oria_i => $oria_pick ) : ?>
			<?php
			$oria_app  = $oria_pick['app'];
			$oria_n    = (int) ( $oria_i + 1 );
			$oria_rel  = $oria_app['affiliate'] ? 'sponsored nofollow noopener' : 'nofollow noopener';
			$oria_line = Guides\pick_line( $oria_pick );
			?>
			<section class="wrap section section--top-flush gdpick" id="pick-<?php echo (int) $oria_n; ?>">
				<div class="gdpick__head">
					<?php if ( '' !== (string) $oria_app['logo'] ) : ?>
						<img class="gdpick__logo" src="<?php echo esc_url( (string) $oria_app['logo'] ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
					<?php endif; ?>
					<div class="gdpick__title">
						<h2 class="h3">
							<span class="gdpick__n" aria-hidden="true"><?php echo (int) $oria_n; ?>.</span>
							<?php echo esc_html( (string) $oria_app['title'] ); ?>
						</h2>
						<?php if ( '' !== $oria_line ) : ?>
							<p class="gdpick__for"><span><?php esc_html_e( 'Best for', 'oria' ); ?></span> <?php echo esc_html( $oria_line ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="gdpick__body">
					<div class="gdpick__main">
						<?php if ( '' !== (string) $oria_pick['blurb'] ) : ?>
							<div class="prose"><?php echo wp_kses_post( wpautop( (string) $oria_pick['blurb'] ) ); ?></div>
						<?php elseif ( '' !== (string) $oria_app['take'] ) : ?>
							<div class="prose"><p><?php echo esc_html( (string) $oria_app['take'] ); ?></p></div>
						<?php endif; ?>

						<?php $oria_benefits = $oria_app['pros'] ?: $oria_app['features']; ?>
						<?php if ( $oria_benefits ) : ?>
							<h3 class="gdpick__sub"><?php esc_html_e( 'Key benefits', 'oria' ); ?></h3>
							<ul class="applist applist--pro">
								<?php foreach ( array_slice( $oria_benefits, 0, 4 ) as $oria_benefit ) : ?>
									<li><?php echo esc_html( $oria_benefit ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $oria_app['cons'] ) : ?>
							<h3 class="gdpick__sub"><?php esc_html_e( 'Things to consider', 'oria' ); ?></h3>
							<ul class="applist applist--con">
								<?php foreach ( array_slice( $oria_app['cons'], 0, 3 ) as $oria_con ) : ?>
									<li><?php echo esc_html( $oria_con ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<aside class="gdpick__side">
						<p class="gdpick__price"><?php echo esc_html( Engine\price_line( $oria_app ) ); ?></p>
						<?php if ( '' !== (string) $oria_app['price'] ) : ?>
							<p class="micro gdpick__from"><?php echo esc_html( (string) $oria_app['price'] ); ?></p>
						<?php endif; ?>

						<div class="gdpick__acts">
							<?php if ( '' !== (string) $oria_app['outbound'] ) : ?>
								<a class="btn btn--dark btn--sm" href="<?php echo esc_url( (string) $oria_app['outbound'] ); ?>" target="_blank" rel="<?php echo esc_attr( $oria_rel ); ?>"
									data-oapp-click="<?php echo esc_attr( (string) $oria_app['id'] ); ?>" data-oapp-kind="visit"
									data-oapp-name="<?php echo esc_attr( (string) $oria_app['title'] ); ?>"
									data-oapp-cat="<?php echo esc_attr( (string) ( $oria_app['cat_slugs'][0] ?? '' ) ); ?>"
									data-oapp-aff="<?php echo $oria_app['affiliate'] ? '1' : '0'; ?>">
									<?php
									/* translators: %s: app name */
									printf( esc_html__( 'Visit %s', 'oria' ), esc_html( (string) $oria_app['title'] ) );
									?>
								</a>
							<?php endif; ?>
							<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( (string) $oria_app['url'] ); ?>">
								<?php esc_html_e( 'Read our full review', 'oria' ); ?>
							</a>
						</div>
					</aside>
				</div>
			</section>
		<?php endforeach; ?>

	<?php endif; ?>

	<?php if ( $oria_guide['faq'] ) : ?>
		<section class="wrap section section--top-flush" aria-labelledby="gdfaqTitle">
			<h2 class="h3" id="gdfaqTitle"><?php esc_html_e( 'Questions people ask', 'oria' ); ?></h2>
			<div class="appfaq gdfaq">
				<?php foreach ( $oria_guide['faq'] as $oria_q ) : ?>
					<details class="appfaq__item">
						<summary><?php echo esc_html( $oria_q['question'] ); ?></summary>
						<p><?php echo esc_html( $oria_q['answer'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="wrap section section--top-flush">
		<div class="appmethod appmethod--wide">
			<h2 class="h3 appmethod__head"><?php esc_html_e( 'How this list was put together', 'oria' ); ?></h2>
			<p><?php echo esc_html( Data\method() ); ?></p>
			<p><?php esc_html_e( 'The order is an editor’s, not a score. Apps are not ranked by whether they pay us, and an app carrying a commission is marked wherever its link appears.', 'oria' ); ?></p>
		</div>
	</section>

	<?php
	/*
	 * Out of the guide and back into the directory. The same move every
	 * app page makes, and the reason this list sits on a Perth wellness
	 * site rather than a review blog.
	 */
	$oria_cat_slugs = array();
	foreach ( $oria_picks as $oria_pick ) {
		$oria_cat_slugs = array_merge( $oria_cat_slugs, (array) $oria_pick['app']['cat_slugs'] );
	}
	$oria_practices = Engine\practices_for( array_values( array_unique( $oria_cat_slugs ) ), array(), 6 );
	?>
	<?php if ( $oria_practices ) : ?>
		<section class="wrap section section--top-flush">
			<div class="appoffline reveal">
				<div class="appoffline__copy">
					<span class="micro"><?php esc_html_e( 'Take it offline', 'oria' ); ?></span>
					<h2 class="h3"><?php esc_html_e( 'The same thing, in a room with other people', 'oria' ); ?></h2>
					<p class="muted"><?php esc_html_e( 'An app is a good companion between sessions. These are the Perth practices doing the same work in person.', 'oria' ); ?></p>
				</div>
				<div class="appoffline__links">
					<?php foreach ( $oria_practices as $oria_practice ) : ?>
						<a class="fchip" href="<?php echo esc_url( $oria_practice['url'] ); ?>"><?php echo esc_html( $oria_practice['name'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

<?php
endwhile;

get_footer();
