<?php
/**
 * One wellness app.
 *
 * Ordered the way somebody decides: what it is, the facts at a glance, our
 * verdict, then the detail, then what it costs, then where else to go —
 * including, deliberately, back out into the directory. An app page that
 * only sends people to an app store has failed at the thing that makes it
 * worth having here.
 *
 * Every claim on the page traces to a field an editor filled in from a
 * source, and the date of that check is printed at the bottom rather than
 * hidden. Nothing is scored out of ten.
 */

declare(strict_types=1);

use Oria\Apps\Data;
use Oria\Apps\Engine;

get_header();

while ( have_posts() ) :
	the_post();

	$oria_id  = (int) get_the_ID();
	$oria_app = Engine\row( $oria_id );
	$oria_cat = $oria_app['cats'][0] ?? null;
	?>

	<section class="wrap pagehead">
		<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( (string) get_post_type_archive_link( Data\CPT ) ); ?>"><?php esc_html_e( 'Apps', 'oria' ); ?></a>
			<?php if ( $oria_cat instanceof WP_Term ) : ?>
				<span aria-hidden="true">/</span>
				<a href="<?php echo esc_url( (string) get_term_link( $oria_cat ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $oria_cat->name ) ); ?></a>
			<?php endif; ?>
			<span aria-hidden="true">/</span><span><?php echo esc_html( $oria_app['title'] ); ?></span>
		</nav>
	</section>

	<section class="wrap apphero">
		<?php if ( '' !== $oria_app['logo'] ) : ?>
			<img class="apphero__logo" src="<?php echo esc_url( $oria_app['logo'] ); ?>" alt="" width="88" height="88" decoding="async">
		<?php endif; ?>
		<div class="apphero__copy">
			<h1 class="h1 apphero__title"><?php echo esc_html( $oria_app['title'] ); ?></h1>
			<?php if ( '' !== $oria_app['tagline'] ) : ?>
				<p class="lede apphero__lede"><?php echo esc_html( $oria_app['tagline'] ); ?></p>
			<?php endif; ?>

			<?php if ( $oria_app['cats'] ) : ?>
				<div class="apphero__cats">
					<?php foreach ( $oria_app['cats'] as $oria_term ) : ?>
						<a class="pill" href="<?php echo esc_url( (string) get_term_link( $oria_term ) ); ?>"><?php echo esc_html( wp_specialchars_decode( $oria_term->name ) ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * The outbound buttons. Every one is tagged for the click
			 * counter, and rel="sponsored" goes on only when the link is
			 * genuinely commercial — putting it on an ordinary link would
			 * be as dishonest as leaving it off a paid one.
			 */
			$oria_rel = $oria_app['affiliate'] ? 'sponsored nofollow noopener' : 'nofollow noopener';
			?>
			<div class="apphero__acts">
				<?php if ( '' !== $oria_app['outbound'] ) : ?>
					<a class="btn btn--dark" href="<?php echo esc_url( $oria_app['outbound'] ); ?>" target="_blank" rel="<?php echo esc_attr( $oria_rel ); ?>"
						data-oapp-click="<?php echo esc_attr( (string) $oria_id ); ?>" data-oapp-kind="visit"
						data-oapp-name="<?php echo esc_attr( $oria_app['title'] ); ?>" data-oapp-cat="<?php echo esc_attr( (string) ( $oria_app['cat_slugs'][0] ?? '' ) ); ?>"
						data-oapp-aff="<?php echo $oria_app['affiliate'] ? '1' : '0'; ?>">
						<?php printf( esc_html__( 'Visit %s', 'oria' ), esc_html( $oria_app['title'] ) ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				<?php endif; ?>
				<?php if ( '' !== $oria_app['ios'] ) : ?>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $oria_app['ios'] ); ?>" target="_blank" rel="nofollow noopener"
						data-oapp-click="<?php echo esc_attr( (string) $oria_id ); ?>" data-oapp-kind="ios" data-oapp-name="<?php echo esc_attr( $oria_app['title'] ); ?>">
						<?php esc_html_e( 'App Store', 'oria' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( '' !== $oria_app['android'] ) : ?>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $oria_app['android'] ); ?>" target="_blank" rel="nofollow noopener"
						data-oapp-click="<?php echo esc_attr( (string) $oria_id ); ?>" data-oapp-kind="android" data-oapp-name="<?php echo esc_attr( $oria_app['title'] ); ?>">
						<?php esc_html_e( 'Google Play', 'oria' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php if ( $oria_app['affiliate'] ) : ?>
				<p class="apphero__disclosure"><?php echo esc_html( Data\disclosure() ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<?php get_template_part( 'template-parts/app-snapshot', null, array( 'app' => $oria_app ) ); ?>

	<?php if ( '' !== $oria_app['take'] ) : ?>
		<section class="wrap section section--top-flush">
			<div class="apptake reveal">
				<span class="micro apptake__label"><?php esc_html_e( 'The Oria take', 'oria' ); ?></span>
				<p class="apptake__text"><?php echo esc_html( $oria_app['take'] ); ?></p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== get_the_excerpt() || $oria_app['features'] ) : ?>
		<section class="wrap section section--top-flush appbody">
			<div class="appbody__main">
				<?php if ( '' !== get_the_excerpt() ) : ?>
					<h2 class="h3"><?php printf( esc_html__( 'What is %s?', 'oria' ), esc_html( $oria_app['title'] ) ); ?></h2>
					<div class="prose"><p><?php echo esc_html( get_the_excerpt() ); ?></p></div>
				<?php endif; ?>

				<?php if ( $oria_app['features'] ) : ?>
					<h2 class="h3"><?php esc_html_e( 'What it offers', 'oria' ); ?></h2>
					<ul class="applist">
						<?php foreach ( $oria_app['features'] as $oria_feature ) : ?>
							<li><?php echo esc_html( $oria_feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $oria_app['pros'] || $oria_app['cons'] ) : ?>
					<div class="appsides">
						<?php if ( $oria_app['pros'] ) : ?>
							<div class="appsides__col">
								<h3 class="appsides__head"><?php esc_html_e( 'What we like', 'oria' ); ?></h3>
								<ul class="applist applist--pro">
									<?php foreach ( $oria_app['pros'] as $oria_pro ) : ?>
										<li><?php echo esc_html( $oria_pro ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( $oria_app['cons'] ) : ?>
							<div class="appsides__col">
								<h3 class="appsides__head"><?php esc_html_e( 'Things to consider', 'oria' ); ?></h3>
								<ul class="applist applist--con">
									<?php foreach ( $oria_app['cons'] as $oria_con ) : ?>
										<li><?php echo esc_html( $oria_con ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( 'unknown' !== $oria_app['pricing'] || '' !== $oria_app['price'] ) : ?>
					<h2 class="h3"><?php esc_html_e( 'What it costs', 'oria' ); ?></h2>
					<div class="prose">
						<p>
							<?php
							echo esc_html( Engine\price_line( $oria_app ) );
							if ( '' !== $oria_app['price'] ) {
								printf( ' — %s', esc_html( $oria_app['price'] ) );
							}
							if ( '' !== $oria_app['trial'] ) {
								printf( esc_html__( '. Free trial: %s', 'oria' ), esc_html( $oria_app['trial'] ) );
							}
							echo '.';
							?>
						</p>
						<?php if ( '' !== $oria_app['price_notes'] ) : ?>
							<p><?php echo esc_html( $oria_app['price_notes'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $oria_app['verified'] ) : ?>
							<p class="appbody__checked">
								<?php printf( esc_html__( 'Pricing last checked %s. Apps change their plans, so check before you subscribe.', 'oria' ), esc_html( date_i18n( 'F Y', (int) strtotime( $oria_app['verified'] ) ) ) ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $oria_app['faq'] ) : ?>
					<h2 class="h3"><?php esc_html_e( 'Questions people ask', 'oria' ); ?></h2>
					<div class="appfaq">
						<?php foreach ( $oria_app['faq'] as $oria_q ) : ?>
							<details class="appfaq__item">
								<summary><?php echo esc_html( $oria_q['question'] ); ?></summary>
								<p><?php echo esc_html( $oria_q['answer'] ); ?></p>
							</details>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<aside class="appbody__aside">
				<div class="appmethod">
					<h2 class="h3 appmethod__head"><?php esc_html_e( 'How we review apps', 'oria' ); ?></h2>
					<p><?php echo esc_html( Data\method() ); ?></p>
					<?php if ( '' !== $oria_app['verified'] ) : ?>
						<p class="appmethod__date">
							<?php printf( esc_html__( 'Last checked: %s', 'oria' ), esc_html( date_i18n( 'j F Y', (int) strtotime( $oria_app['verified'] ) ) ) ); ?>
						</p>
					<?php endif; ?>
					<?php if ( $oria_app['sources'] ) : ?>
						<p class="appmethod__sources">
							<?php esc_html_e( 'Checked against:', 'oria' ); ?>
							<?php foreach ( $oria_app['sources'] as $oria_i => $oria_src ) : ?>
								<a href="<?php echo esc_url( $oria_src ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( (string) wp_parse_url( $oria_src, PHP_URL_HOST ) ); ?></a><?php echo $oria_i < count( $oria_app['sources'] ) - 1 ? ', ' : ''; ?>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
				</div>
			</aside>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Back into the directory. This is the whole reason apps live on a
	 * wellness directory rather than a review blog: an app is a companion
	 * to a practice, and the practice is the thing you can actually go to.
	 */
	$oria_practices = Engine\practices_for(
		(array) $oria_app['cat_slugs'],
		(array) get_post_meta( $oria_id, 'practices', true )
	);
	?>
	<?php if ( $oria_practices ) : ?>
		<section class="wrap section section--top-flush">
			<div class="appoffline reveal">
				<div class="appoffline__copy">
					<span class="micro"><?php esc_html_e( 'Take it offline', 'oria' ); ?></span>
					<h2 class="h3"><?php esc_html_e( 'Prefer practising with other people?', 'oria' ); ?></h2>
					<p class="muted"><?php esc_html_e( 'An app is a good companion between sessions. These are the places in Perth doing the same thing in a room.', 'oria' ); ?></p>
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
	$oria_related = Engine\related( $oria_id, 3 );
	if ( $oria_related ) {
		echo \Oria\Apps\Render\band( $oria_related, __( 'You might also like', 'oria' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>

<?php
endwhile;

get_footer();
