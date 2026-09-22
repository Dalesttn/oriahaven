<?php
/**
 * /list-your-practice/ — self-service signup: the details of a free
 * listing plus an account, submitted in one go. The listing arrives
 * pending (approved within 24 hours); the account works immediately.
 */

declare(strict_types=1);

get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_done   = isset( $_GET['signup'] ) && 'done' === $_GET['signup'];
$oria_errors = array_filter( explode( ',', sanitize_text_field( wp_unslash( (string) ( $_GET['e'] ?? '' ) ) ) ) );
$oria_key    = sanitize_key( (string) ( $_GET['k'] ?? '' ) );
// phpcs:enable
$oria_old = $oria_key ? (array) get_transient( 'oria_signup_' . $oria_key ) : array();
$oria_v   = static fn( string $k ): string => esc_attr( (string) ( $oria_old[ $k ] ?? '' ) );

$oria_error_text = array(
	'expired'        => __( 'The form sat open too long — please submit it again.', 'oria' ),
	'spam'           => __( "That submission looked automated. If you're human, try once more a little slower.", 'oria' ),
	'name'           => __( 'Practice name is required.', 'oria' ),
	'category'       => __( 'Pick the practice category that fits best.', 'oria' ),
	'suburb'         => __( 'Pick your suburb.', 'oria' ),
	'description'    => __( 'Tell people a little more about the practice — a couple of sentences at least.', 'oria' ),
	'public_email'   => __( "The practice's public email doesn't look like an email address.", 'oria' ),
	'price_band'     => __( 'Pick a price band from the list.', 'oria' ),
	'format'         => __( 'Pick a format from the list.', 'oria' ),
	'account_name'   => __( 'Your name is required for the account.', 'oria' ),
	'account_email'  => __( "Your account email doesn't look like an email address.", 'oria' ),
	'account_exists' => __( 'There\'s already an account with that email — log in instead, or use the claim form if your practice is already listed.', 'oria' ),
	'authorised'     => __( 'Please confirm you\'re authorised to manage this practice.', 'oria' ),
	'photo_count'    => __( 'Up to four photos on the free plan.', 'oria' ),
	'photo_size'     => __( 'Each photo must be under 5MB.', 'oria' ),
	'photo_type'     => __( 'Photos must be image files — JPEG, PNG or WebP.', 'oria' ),
	'photo_upload'   => __( 'One of the photos failed to upload — please try again.', 'oria' ),
	'server'         => __( 'Something went wrong on our side — please try again in a minute.', 'oria' ),
);

$oria_practices = get_terms( array( 'taxonomy' => 'practice', 'hide_empty' => false ) );
$oria_practices = is_wp_error( $oria_practices ) ? array() : $oria_practices;
$oria_regions   = \Oria\Core\Taxonomies\regions();
$oria_regions   = is_wp_error( $oria_regions ) ? array() : $oria_regions;
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'List your practice', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:56rem">
		<span class="micro"><?php esc_html_e( 'Free listing, two minutes', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'List your practice', 'oria' ); ?></h1>
		<p class="lede pagehead__lede"><?php esc_html_e( 'Four answers and you are in. We email you a password, and you build the rest of the profile in your own dashboard — category, suburb, address, services, prices, hours and photos — a bit at a time, whenever suits. Free, and it stays free.', 'oria' ); ?></p>
		<p style="margin-top:.9rem;max-width:56ch;color:var(--text-soft)"><?php esc_html_e( 'Listed practices receive enquiries two ways: straight from your profile, and through our matching service — when a visitor tells us what they\'re after, we introduce them to up to three practices that fit. Enquiries land in your inbox with the person\'s details, ready to reply. Free, and we never take a cut of bookings.', 'oria' ); ?></p>
		<p style="margin-top:.9rem"><a href="#plans"><?php esc_html_e( 'See what a listing costs', 'oria' ); ?> &darr;</a></p>
	</div>
</section>


<?php
/*
 * What a listing costs, read from the tier registry rather than typed here.
 * A price hard-coded into a template is a price that goes stale the first
 * time somebody changes the plan and forgets this page exists.
 */
$oria_paid_p = function_exists( '\Oria\Core\Tiers\tier' ) ? \Oria\Core\Tiers\PRICES[ \Oria\Core\Tiers\FEATURED ] : 30;
$oria_plans = array(
	array(
		'name'  => __( 'Free', 'oria' ),
		'price' => __( '$0', 'oria' ),
		'note'  => __( 'forever, no card', 'oria' ),
		'lines' => array(
			__( 'Your listing in the directory, its category and your suburb', 'oria' ),
			__( 'Edit every detail yourself, any time', 'oria' ),
			__( 'Address, phone, email, website and booking link', 'oria' ),
			__( 'Your services, prices, timetable and opening hours', 'oria' ),
			__( 'Up to ten photos and four practitioner profiles', 'oria' ),
			__( 'A current offer, Instagram and Facebook', 'oria' ),
			__( 'Performance stats -- views, clicks, calls and enquiries', 'oria' ),
			__( 'Enquiries straight to your inbox, and introductions from our matching service', 'oria' ),
		),
	),
	array(
		'name'  => __( 'Featured', 'oria' ),
		'price' => '$' . $oria_paid_p,
		'note'  => __( 'per month', 'oria' ),
		'lines' => array(
			__( 'Everything in Free, plus:', 'oria' ),
			__( 'Featured placement in your category and on the home page', 'oria' ),
			__( 'A Featured badge on your listing', 'oria' ),
			__( "Publish your workshops and events on What's On", 'oria' ),
			__( 'Unlimited photos', 'oria' ),
		),
	),
);
?>

<section class="wrap section section--top-flush">
	<?php if ( $oria_done ) : ?>
		<div class="card" style="max-width:44rem"><div class="card__body">
			<h2 class="h3"><?php esc_html_e( "You're registered 🎉", 'oria' ); ?></h2>
			<p style="margin-top:.6rem;color:var(--text-soft)"><?php esc_html_e( "Check your inbox — there is a link in there to set your password. Once you are in, your dashboard is where you choose your category and suburb and add the address, services, prices, hours and photos. Your listing is reviewed and published within 24 hours.", 'oria' ); ?></p>
		</div></div>
	<?php else : ?>

		<?php if ( $oria_errors ) : ?>
			<div class="card" style="max-width:44rem;border-color:#c98787;margin-bottom:1.5rem"><div class="card__body">
				<b style="color:#9b2c2c"><?php esc_html_e( "That didn't go through:", 'oria' ); ?></b>
				<ul style="margin:.5rem 0 0 1.1rem;color:#9b2c2c;font-size:.9rem">
					<?php foreach ( $oria_errors as $oria_e ) : ?>
						<?php if ( isset( $oria_error_text[ $oria_e ] ) ) : ?><li><?php echo esc_html( $oria_error_text[ $oria_e ] ); ?></li><?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div></div>
		<?php endif; ?>

		<form class="stack" style="gap:1rem;max-width:44rem" method="post" enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oria_signup">
			<input type="hidden" name="oria_ts" value="<?php echo esc_attr( (string) time() ); ?>">
			<?php wp_nonce_field( 'oria_signup', 'oria_signup_nonce' ); ?>
			<input type="text" name="oform_website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

			<label class="field"><span class="field__label"><?php esc_html_e( 'Practice name', 'oria' ); ?></span>
				<input class="input" type="text" name="practice_name" required autocomplete="organization" value="<?php echo $oria_v( 'practice_name' ); ?>"></label>

			<div class="row2" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
				<label class="field"><span class="field__label"><?php esc_html_e( 'Your name', 'oria' ); ?></span>
					<input class="input" type="text" name="account_name" required autocomplete="name" value="<?php echo $oria_v( 'account_name' ); ?>"></label>
				<label class="field"><span class="field__label"><?php esc_html_e( 'Phone', 'oria' ); ?></span>
					<input class="input" type="tel" name="phone" autocomplete="tel" value="<?php echo $oria_v( 'phone' ); ?>"></label>
			</div>

			<label class="field"><span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
				<input class="input" type="email" name="account_email" required autocomplete="email" value="<?php echo $oria_v( 'account_email' ); ?>">
				<span class="field__help"><?php esc_html_e( 'We send your password here. It is also how you sign in.', 'oria' ); ?></span></label>

			<label class="check" style="align-items:flex-start"><input type="checkbox" name="authorised" value="1" required>
				<span style="font-size:.875rem"><?php esc_html_e( "I'm authorised to manage this practice's information.", 'oria' ); ?></span></label>

			<button class="btn btn--dark btn--block" type="submit"><?php esc_html_e( 'Create my listing', 'oria' ); ?></button>
			<p class="muted" style="font-size:.8125rem"><?php esc_html_e( 'We email your password the moment you submit. The listing is checked by a person before it goes live, usually well inside 24 hours.', 'oria' ); ?></p>
		</form>
	<?php endif; ?>
</section>

<?php
/*
 * The questions a practitioner actually asks before signing up. Rendered
 * through the shared FAQ part, which emits the FAQPage JSON-LD from the same
 * array it prints — so the markup and the structured data cannot disagree.
 */
get_template_part(
	'template-parts/faq',
	null,
	array(
		'id'      => 'faq',
		'heading' => __( 'Listing your practice — common questions', 'oria' ),
		'faqs'    => array(
			array(
				'q' => __( 'How do I list my practice on Oria Haven?', 'oria' ),
				'a' => __( 'Four answers on this page: your practice name, your name, your email and a phone number. We email you a password straight away, and everything else — category, suburb, address, services, prices, hours, photos — you fill in from your dashboard whenever you like. The listing is reviewed and published within 24 hours, and no card is needed.', 'oria' ),
			),
			array(
				'q' => __( 'What does a listing cost?', 'oria' ),
				'a' => sprintf(
					/* translators: %s: monthly price of the paid plan */
					__( 'Nothing. Listing is free, claiming it is free, and editing every detail — your services, prices, hours, photos, booking link and offers — is free, with the performance stats included. The only paid plan is Featured at $%s a month, which adds featured placement, a badge, unlimited photos and the ability to publish your events on What\'s On.', 'oria' ),
					$oria_paid_p
				),
			),
			array(
				'q' => __( 'Do you take a commission on bookings?', 'oria' ),
				'a' => __( 'No, and we never have. Enquiries go directly to you with the person\'s details and you deal with them yourself. We are a directory, not a booking platform, so there is nothing for us to take a cut of.', 'oria' ),
			),
			array(
				'q' => __( 'How do I know whether the listing is actually sending me clients?', 'oria' ),
				'a' => __( 'A claimed listing shows its own performance stats: how many people opened your profile, and how many then clicked your website, tapped your phone number, opened your booking link, asked for directions or sent an enquiry. Figures cover the last 30 and 7 days, your own visits are excluded, and the counting is first-party — no cookies and no third-party trackers.', 'oria' ),
			),
			array(
				'q' => __( 'My practice is already listed. How do I take it over?', 'oria' ),
				'a' => __( 'Use the claim form rather than this one. Many listings here were built from information practices publish about themselves and are marked Unclaimed until the owner confirms them — claiming one hands you the keys to what is already there, including its address and any reviews.', 'oria' ),
			),
			array(
				'q' => __( 'What happens if I cancel a paid plan?', 'oria' ),
				'a' => __( 'The listing stays. It drops back to the free plan, still shows as claimed by you, and everything you added is kept — photos, timetable, practitioner profiles, the lot. The paid features simply stop publishing until you restart a plan, and you can still keep your location, contact details and prices current.', 'oria' ),
			),
		),
	)
);
?>

<?php
/*
 * What it costs, at the bottom on purpose.
 *
 * This used to open the page: two plan cards and a full comparison table
 * before a practice had typed anything. Somebody arriving from "List your
 * practice" has already decided to be listed -- the first thing they meet
 * should be the first question of the form, not a price they have to talk
 * themselves past. The plans still have to be here, and easy to find from
 * the top, but they answer a question that comes after the decision.
 */
?>
<section class="wrap section section--top-flush" id="plans">
	<h2 class="h3" style="margin-bottom:.4rem"><?php esc_html_e( 'What a listing costs', 'oria' ); ?></h2>
	<p class="hint" style="max-width:52ch;margin-bottom:1.2rem"><?php esc_html_e( 'Listing is free and stays free. We never take a commission on a booking, and nothing here is a lock-in contract — cancel a paid plan and the listing drops back to Free with everything you added still on it.', 'oria' ); ?></p>
	<div class="plans">
		<?php foreach ( $oria_plans as $oria_i => $oria_plan ) : ?>
			<div class="plans__card<?php echo 0 === $oria_i ? ' plans__card--pick' : ''; ?>">
				<?php if ( 0 === $oria_i ) : ?>
					<span class="plans__flag"><?php esc_html_e( 'Where every practice starts', 'oria' ); ?></span>
				<?php endif; ?>
				<h3 class="plans__name"><?php echo esc_html( $oria_plan['name'] ); ?></h3>
				<p class="plans__price"><?php echo esc_html( $oria_plan['price'] ); ?> <small><?php echo esc_html( $oria_plan['note'] ); ?></small></p>
				<ul class="plans__list">
					<?php foreach ( $oria_plan['lines'] as $oria_line ) : ?>
						<li><?php echo esc_html( $oria_line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="hint" style="margin-top:1rem;max-width:52ch">
		<?php esc_html_e( 'Already listed? You can claim an existing listing rather than creating a second one.', 'oria' ); ?>
		<a href="<?php echo esc_url( home_url( '/claim/' ) ); ?>"><?php esc_html_e( 'Claim your listing', 'oria' ); ?></a>
	</p>
</section>

<?php get_template_part( 'template-parts/sections/tier-table' ); ?>


<?php
get_footer();
