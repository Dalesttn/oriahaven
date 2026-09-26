<?php
/**
 * /about/ — why Oria Haven exists, and what to do next.
 *
 * Replaces the ACF-sections version of this page. That page opened on the
 * directory ("A directory built the slow way"), told a founding anecdote
 * nobody had confirmed, and promised things the site no longer does or
 * never did: "no scraped content" (research batches now collect from
 * organisers' public pages), same-day removals and one-working-day replies
 * (no service standard behind either), and, in its meta description, "no
 * paid rankings" beside a paid Featured plan.
 *
 * Now it leads with the visitor's purpose -- discover, understand, try --
 * then Oria Pass, the person behind it, how to read a listing, a banner for
 * businesses, and the contact form.
 *
 * A theme template rather than new ACF rows, on purpose: the copy ships
 * with the code (a database-only change would never reach production on a
 * git pull), and the page's working contact form is the same
 * [oria_form form="contact"] shortcode, rendered exactly as before, so its
 * validation, spam protection and #oform-contact anchor are untouched.
 *
 * Oria Pass copy follows the Pass plugin's own mode setting, never a date:
 * waitlist by default, "now open" only when Settings\is_live() says so.
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'oria-about', get_template_directory_uri() . '/assets/css/about.css', array(), (string) filemtime( get_template_directory() . '/assets/css/about.css' ) );

get_header();

$oria_pass_live = function_exists( '\Oria\Pass\Settings\is_live' ) && \Oria\Pass\Settings\is_live();
$oria_img       = get_template_directory_uri() . '/assets/img/about/';
?>
<?php // header.php already opens <main id="main">. ?>
<div class="oria-about">

	<?php /* 1. Purpose ------------------------------------------------------ */ ?>
	<section class="oria-about__section oria-about__section--hero" aria-labelledby="about-h1">
		<div class="oria-about__container oria-about__hero">
			<div class="oria-about__hero-text">
				<p class="oria-about__eyebrow"><?php esc_html_e( 'About Oria Haven', 'oria' ); ?></p>
				<h1 id="about-h1"><?php esc_html_e( 'Discover what wellness could look like for you.', 'oria' ); ?></h1>
				<p class="oria-about__lede"><?php esc_html_e( 'Oria Haven helps you explore different ways to move, unwind, connect and make time for yourself. Discover local experiences, understand what to expect and take a first step towards finding what fits you.', 'oria' ); ?></p>
				<p class="oria-about__copy"><?php esc_html_e( 'From a walking group to a Pilates class or a quiet hour at a sauna, there is more than one place to begin.', 'oria' ); ?></p>
				<div class="oria-about__actions">
					<a class="oria-about__button oria-about__button--primary" href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e( 'Explore wellness', 'oria' ); ?> <span aria-hidden="true">&rarr;</span></a>
					<a class="oria-about__button oria-about__button--secondary" href="<?php echo esc_url( home_url( '/oria-pass/' ) ); ?>"><?php esc_html_e( 'Discover Oria Pass', 'oria' ); ?></a>
				</div>
				<p class="oria-about__where"><?php esc_html_e( 'Based in Perth, with guides to Perth and Margaret River.', 'oria' ); ?></p>
			</div>
			<figure class="oria-about__hero-figure">
				<?php // Decorative: the heading beside it says what the page is. Pexels licence, credited in assets/img/CREDITS.md. ?>
				<img class="oria-about__hero-image"
					src="<?php echo esc_url( $oria_img . 'about-hero-1200.webp' ); ?>"
					srcset="<?php echo esc_url( $oria_img . 'about-hero-720.webp' ); ?> 720w, <?php echo esc_url( $oria_img . 'about-hero-1200.webp' ); ?> 1200w"
					sizes="(max-width: 800px) calc(100vw - 40px), 480px"
					width="1200" height="900" alt="" fetchpriority="high" decoding="async">
			</figure>
		</div>
	</section>

	<?php /* 2. Why we exist ------------------------------------------------- */ ?>
	<section class="oria-about__section" aria-labelledby="about-why">
		<div class="oria-about__container">
			<h2 id="about-why"><?php esc_html_e( 'You do not need to know where to start.', 'oria' ); ?></h2>
			<div class="oria-about__prose">
				<p><?php esc_html_e( 'You might know that you want to feel calmer, move more or meet new people, without knowing which activity to try. Unfamiliar names, scattered information and uncertainty about a first visit can make it harder to take that step.', 'oria' ); ?></p>
				<p><?php esc_html_e( 'Oria Haven brings the options into view. Our purpose is to make wellness easier to explore: what is available, what an experience involves and how you can take part. You can learn, compare and decide at your own pace.', 'oria' ); ?></p>
			</div>
		</div>
	</section>

	<?php /* 3. Discover, understand, try ------------------------------------ */ ?>
	<section class="oria-about__section oria-about__section--tight" aria-labelledby="about-how">
		<div class="oria-about__container">
			<h2 id="about-how"><?php esc_html_e( 'Discover. Understand. Try.', 'oria' ); ?></h2>
			<ol class="oria-about__steps">
				<li class="oria-about__step">
					<span class="oria-about__num" aria-hidden="true">01</span>
					<h3><?php esc_html_e( 'Discover what is out there', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Explore local studios, practitioners, classes and community activities. Find familiar favourites and possibilities you may not have considered, starting with what interests you or how you want to feel.', 'oria' ); ?></p>
				</li>
				<li class="oria-about__step">
					<span class="oria-about__num" aria-hidden="true">02</span>
					<h3><?php esc_html_e( 'Understand the experience', 'oria' ); ?></h3>
					<p><?php esc_html_e( 'Get a clearer picture before you go. Read plain-language introductions, compare activities and look for practical details such as location, session format, price and how to join, where that information is available.', 'oria' ); ?></p>
				</li>
				<li class="oria-about__step">
					<span class="oria-about__num" aria-hidden="true">03</span>
					<h3><?php esc_html_e( 'Take the first step', 'oria' ); ?></h3>
					<p>
						<?php
						echo esc_html(
							$oria_pass_live
								? __( 'Visit an organiser’s website, enquire about a class or join a local group. Oria Pass offers another way to try selected experiences with participating businesses.', 'oria' )
								: __( 'Visit an organiser’s website, enquire about a class or join a local group. As Oria Pass develops, it will offer another way to explore selected experiences with participating businesses.', 'oria' )
						);
						?>
					</p>
				</li>
			</ol>
		</div>
	</section>

	<?php /* 4. Oria Pass: copy and actions follow the plugin's mode together -- */ ?>
	<section class="oria-about__section oria-about__section--tight" aria-labelledby="about-pass">
		<div class="oria-about__container">
			<div class="oria-about__pass">
				<div class="oria-about__pass-text">
					<p class="oria-about__status">
						<?php echo esc_html( $oria_pass_live ? __( 'Now open · Participating businesses only', 'oria' ) : __( 'In development · Join the waitlist', 'oria' ) ); ?>
					</p>
					<h2 id="about-pass"><?php esc_html_e( 'Get a feel for something new.', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Reading about an experience is a useful start. Trying it can help you decide whether it belongs in your routine.', 'oria' ); ?></p>
					<p>
						<?php
						echo esc_html(
							$oria_pass_live
								? __( 'Oria Pass lets you explore eligible experiences across participating wellness businesses in Perth — room to try different activities and places, and discover what you would like to return to.', 'oria' )
								: __( 'We are developing Oria Pass to help people explore eligible experiences across participating wellness businesses in Perth. The idea is to give you room to try different activities and places, and discover what you would like to return to.', 'oria' )
						);
						?>
					</p>
					<div class="oria-about__actions">
						<a class="oria-about__button oria-about__button--primary" href="<?php echo esc_url( home_url( '/oria-pass/#pass-join' ) ); ?>">
							<?php echo esc_html( $oria_pass_live ? __( 'Start your Oria Pass', 'oria' ) : __( 'Join the Oria Pass waitlist', 'oria' ) ); ?> <span aria-hidden="true">&rarr;</span>
						</a>
						<a class="oria-about__textlink" href="<?php echo esc_url( home_url( '/oria-pass/' ) ); ?>">
							<?php echo esc_html( $oria_pass_live ? __( 'How Oria Pass works', 'oria' ) : __( 'How Oria Pass will work', 'oria' ) ); ?>
						</a>
					</div>
					<p class="oria-about__note">
						<?php
						echo esc_html(
							$oria_pass_live
								? __( 'Only participating businesses accept Oria Pass, and only for the experiences they choose to offer. Being listed on Oria Haven does not mean a business takes part.', 'oria' )
								: __( 'Oria Pass is not open yet. Participation and eligible experiences will be limited to businesses taking part.', 'oria' )
						);
						?>
					</p>
				</div>
				<?php // A brand mark and a status, nothing more: no member name, balance, booking or partner badge. ?>
				<div class="oria-about__pass-card" aria-hidden="true">
					<span class="oria-about__pass-brand">Oria <em>Pass</em></span>
					<span class="oria-about__pass-state"><?php echo esc_html( $oria_pass_live ? __( 'Now open', 'oria' ) : __( 'Coming soon', 'oria' ) ); ?></span>
				</div>
			</div>
		</div>
	</section>

	<?php /* 5. The people behind it ----------------------------------------- */ ?>
	<section class="oria-about__section" aria-labelledby="about-who">
		<div class="oria-about__container">
			<h2 id="about-who"><?php esc_html_e( 'A local project with a simple purpose.', 'oria' ); ?></h2>
			<div class="oria-about__prose">
				<p><?php esc_html_e( 'Oria Haven is run by Dale in Perth. The aim is to help more people discover the wellness experiences around them, while giving local businesses and community groups a clearer place to share what they offer.', 'oria' ); ?></p>
				<p><?php esc_html_e( 'We are building it around useful information and real participation: helping you understand the options, try something that interests you and find the places you want to return to.', 'oria' ); ?></p>
			</div>
		</div>
	</section>

	<?php /* 6. How to read a listing ---------------------------------------- */ ?>
	<section class="oria-about__section oria-about__section--rule" aria-labelledby="about-info">
		<div class="oria-about__container">
			<h2 id="about-info"><?php esc_html_e( 'Clear information. Room for questions.', 'oria' ); ?></h2>
			<div class="oria-about__prose oria-about__prose--small">
				<p><?php esc_html_e( 'Some profiles begin with information that businesses and organisers make public. Owners can claim their profile and help keep the details current. Availability, prices and timetables can change, so check important details with the provider before visiting.', 'oria' ); ?></p>
				<p><?php esc_html_e( 'We aim to make it clear where information comes from and whether a profile has been confirmed by its owner. A listing is a starting point for your own questions and choices, rather than a promise that every experience will suit everyone.', 'oria' ); ?></p>
				<p class="oria-about__smalllinks">
					<?php // Corrections go through the form below; every listing also has its own "Suggest an edit". ?>
					<a class="oria-about__textlink" href="#contact"><?php esc_html_e( 'Suggest a correction', 'oria' ); ?></a>
					<span aria-hidden="true">·</span>
					<a class="oria-about__textlink" href="#contact"><?php esc_html_e( 'Get in touch', 'oria' ); ?></a>
				</p>
			</div>
		</div>
	</section>

	<?php /* 7. For businesses ----------------------------------------------- */ ?>
	<section class="oria-about__section oria-about__section--tight" aria-labelledby="about-biz">
		<div class="oria-about__container">
			<div class="oria-about__business">
				<div>
					<p class="oria-about__eyebrow oria-about__eyebrow--light"><?php esc_html_e( 'For wellness businesses & community organisers', 'oria' ); ?></p>
					<h2 id="about-biz"><?php esc_html_e( 'Help people discover what you do.', 'oria' ); ?></h2>
					<p><?php esc_html_e( 'Run a studio, practice, class or local group? Give people a clearer picture of what you offer and how to take the next step.', 'oria' ); ?></p>
					<p><?php esc_html_e( 'Already on Oria Haven? Claim your profile to help keep your basic details accurate. If you are not listed, start with a business listing.', 'oria' ); ?></p>
					<?php // The listing form is built for businesses; a group or club is pointed at the contact form, not a signup that does not fit it. ?>
					<p class="oria-about__biz-more">
						<a href="#contact"><?php esc_html_e( 'Run a community group or club? Tell us about it', 'oria' ); ?></a>
						<span aria-hidden="true"> · </span>
						<a href="<?php echo esc_url( home_url( '/oria-pass/partners/' ) ); ?>"><?php esc_html_e( 'Interested in becoming an Oria Pass partner?', 'oria' ); ?></a>
					</p>
				</div>
				<div class="oria-about__biz-actions">
					<a class="oria-about__button oria-about__button--ivory" href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your profile', 'oria' ); ?></a>
					<a class="oria-about__button oria-about__button--outline" href="<?php echo esc_url( home_url( '/list-your-practice/' ) ); ?>"><?php esc_html_e( 'List your business', 'oria' ); ?></a>
					<?php // Checked against oria-core/includes/tiers.php: the claimed tier costs nothing; Featured is the paid plan. ?>
					<p class="oria-about__reassure"><?php esc_html_e( 'Claiming is free. Optional paid plans offer additional features.', 'oria' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<?php /* 8. Contact: the existing, working form -------------------------- */ ?>
	<section class="oria-about__section" id="contact" aria-labelledby="about-contact">
		<div class="oria-about__container oria-about__contact">
			<div>
				<h2 id="about-contact"><?php esc_html_e( 'Help shape what comes next.', 'oria' ); ?></h2>
				<p class="oria-about__copy"><?php esc_html_e( 'Know a group we should include, spotted something that needs updating or have an idea for Oria Haven? We would like to hear from you.', 'oria' ); ?></p>
				<p class="oria-about__mail">
					<a href="mailto:hello@oriahaven.com.au">hello@oriahaven.com.au</a>
				</p>
			</div>
			<div class="oria-about__form">
				<?php echo do_shortcode( '[oria_form form="contact"]' ); // phpcs:ignore WordPress.Security.EscapeOutput -- the form plugin escapes its own markup ?>
			</div>
		</div>
	</section>

</div>
<?php
get_footer();
