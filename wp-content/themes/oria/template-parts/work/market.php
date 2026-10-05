<?php
/**
 * /corporate-wellness/ and /retreat-staff/ (brief sections 49-51).
 *
 * Practitioners who said they are available for this kind of work, and an
 * enquiry that comes to Oria Haven -- we introduce people by hand, with the
 * practitioner's agreement, rather than handing a form submission to
 * strangers.
 *
 * $args: market (corporate|retreat)
 */

declare(strict_types=1);

use Oria\Core\Work;
use Oria\Core\Work\Market;

$oria_m    = 'retreat' === ( $args['market'] ?? '' ) ? 'retreat' : 'corporate';
$oria_pros = Market\talent_for( $oria_m, 12 );
$oria_note = Work\notice();
$oria_copy = 'retreat' === $oria_m
	? array(
		'kicker' => __( 'Retreat staff', 'oria' ),
		'lede'   => __( 'Running a retreat? Find yoga teachers, facilitators, massage therapists, cooks and photographers who are free for retreat work — or tell us what you need and we will introduce people who suit.', 'oria' ),
		'for'    => __( 'Available for retreats', 'oria' ),
		'ask'    => __( 'Tell us about your retreat', 'oria' ),
		'need'   => __( 'Who do you need, and for how long? (e.g. a yoga teacher and a cook for a 5-day retreat)', 'oria' ),
	)
	: array(
		'kicker' => __( 'Workplace wellness', 'oria' ),
		'title'  => __( 'Planning a wellness session for your Perth team?', 'oria' ),
		'lede'   => __( 'Tell us your group size, preferred date and budget. We’ll review your request and explore suitable options with local providers.', 'oria' ),
		'for'    => __( 'Practitioners available for workplaces', 'oria' ),
		'ask'    => __( 'Tell us what you’re planning', 'oria' ),
		'need'   => __( 'Anything else we should know? (optional)', 'oria' ),
	);
?>
<header class="wkhero wkhero--land">
	<div class="wrap">
		<p class="micro wkhero__kicker"><?php echo esc_html( $oria_copy['kicker'] ); ?></p>
		<h1 class="wkhero__title"><?php echo esc_html( $oria_copy['title'] ?? Work\heading() ); ?></h1>
		<p class="lede wkhero__lede"><?php echo esc_html( $oria_copy['lede'] ); ?></p>
		<p class="wkhero__alt">
			<a class="btn btn--light" href="#enquire"><?php echo esc_html( $oria_copy['ask'] ); ?></a>
			<a class="btn btn--ghost-on-deep" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>"><?php esc_html_e( 'Practitioner? Add yourself', 'oria' ); ?></a>
		</p>
	</div>
</header>

<?php if ( 'retreat' !== $oria_m ) : ?>
	<?php
	/*
	 * How a workplace request works, said plainly: nothing is booked or paid
	 * through this form, and the formats are examples, not stock or signed
	 * partnerships. Availability and price are the provider's to confirm.
	 */
	?>
	<section class="wrap wksec wksteps" aria-labelledby="wkStepsTitle">
		<h2 class="h3" id="wkStepsTitle"><?php esc_html_e( 'How it works', 'oria' ); ?></h2>
		<ol class="wksteps__list">
			<li><strong><?php esc_html_e( 'Send your request', 'oria' ); ?></strong><span><?php esc_html_e( 'Group size, a date or a window, a rough total budget and the kind of session you have in mind.', 'oria' ); ?></span></li>
			<li><strong><?php esc_html_e( 'We look at the options', 'oria' ); ?></strong><span><?php esc_html_e( 'A person at Oria Haven checks what suitable local providers can offer, without passing on your details.', 'oria' ); ?></span></li>
			<li><strong><?php esc_html_e( 'You decide', 'oria' ); ?></strong><span><?php esc_html_e( 'You get the options, including any fees, before anything is booked. Nothing is confirmed until you say yes.', 'oria' ); ?></span></li>
		</ol>
		<p class="hint"><?php esc_html_e( 'Examples people ask for: a gentle movement class at lunchtime, a mindfulness workshop, a breathwork session, chair massage for a wellbeing day or a sound bath. Availability and pricing are always subject to the provider confirming.', 'oria' ); ?></p>
	</section>
<?php endif; ?>

<section class="wrap wksec">
	<h2 class="h3"><?php echo esc_html( $oria_copy['for'] ); ?></h2>
	<?php if ( $oria_pros ) : ?>
		<div class="wkpros">
			<?php foreach ( $oria_pros as $oria_p ) : ?>
				<?php get_template_part( 'template-parts/work/card-pro', null, array( 'id' => (int) $oria_p ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="wkempty"><p><?php esc_html_e( 'We are building this list now. Tell us what you need below and we will find the right people for you.', 'oria' ); ?></p></div>
	<?php endif; ?>
</section>

<section class="wrap wksec" id="enquire">
	<div class="wkpanel wkenquire">
		<h2 class="h3"><?php echo esc_html( $oria_copy['ask'] ); ?></h2>
		<?php if ( 'retreat' === $oria_m ) : ?>
			<p class="hint"><?php esc_html_e( 'This comes to a person at Oria Haven, not to a list. We reply within one business day, and only share your details with practitioners you choose.', 'oria' ); ?></p>
		<?php else : ?>
			<p class="hint"><?php esc_html_e( 'This comes to a person at Oria Haven, not to a list. Your details are not passed to any provider without asking you first. Please do not include health or medical information.', 'oria' ); ?></p>
		<?php endif; ?>
		<?php if ( $oria_note && 'ok' === $oria_note['type'] && 'retreat' !== $oria_m && 'group_stored' === sanitize_key( (string) ( $_GET['wk'] ?? '' ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php // State marker: only rendered after the server stored a genuine request, so it is the conversion. ?>
			<span hidden data-oria-event="group_enquiry_submit" data-oria-placement="corporate_wellness"></span>
		<?php endif; ?>
		<?php if ( $oria_note ) : ?>
			<div class="notice notice--<?php echo 'ok' === $oria_note['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_note['text'] ); ?></div>
		<?php endif; ?>
		<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo Work\form_fields( 'oria_work_talent_request' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="hidden" name="market" value="<?php echo esc_attr( $oria_m ); ?>">
			<input type="hidden" name="wk_ts" value="<?php echo (int) time(); ?>">
			<div class="wk-trap" aria-hidden="true"><label>Website <input type="text" name="wk_website" tabindex="-1" autocomplete="off"></label></div>
			<?php if ( 'retreat' === $oria_m ) : ?>
				<div class="wkform__row">
					<div><label for="tr-org"><?php esc_html_e( 'Retreat or organiser', 'oria' ); ?></label><input class="input" id="tr-org" name="org" required maxlength="120" autocomplete="organization"></div>
					<div><label for="tr-name"><?php esc_html_e( 'Your name', 'oria' ); ?></label><input class="input" id="tr-name" name="name" required maxlength="80" autocomplete="name"></div>
				</div>
				<div class="wkform__row">
					<div><label for="tr-email"><?php esc_html_e( 'Email', 'oria' ); ?></label><input class="input" id="tr-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
					<div><label for="tr-phone"><?php esc_html_e( 'Phone (optional)', 'oria' ); ?></label><input class="input" id="tr-phone" name="phone" type="tel" maxlength="40" autocomplete="tel"></div>
				</div>
				<label for="tr-need"><?php echo esc_html( $oria_copy['need'] ); ?></label>
				<textarea class="input" id="tr-need" name="need" rows="4" required minlength="20" maxlength="2000"></textarea>
				<div class="wkform__row">
					<div><label for="tr-when"><?php esc_html_e( 'When', 'oria' ); ?></label><input class="input" id="tr-when" name="when" maxlength="120"></div>
					<div><label for="tr-where"><?php esc_html_e( 'Where', 'oria' ); ?></label><input class="input" id="tr-where" name="where" maxlength="120"></div>
					<div><label for="tr-people"><?php esc_html_e( 'Guests', 'oria' ); ?></label><input class="input" id="tr-people" name="people" maxlength="40" inputmode="numeric"></div>
					<div><label for="tr-budget"><?php esc_html_e( 'Budget (optional)', 'oria' ); ?></label><input class="input" id="tr-budget" name="budget" maxlength="80"></div>
				</div>
				<button class="btn btn--dark" type="submit" data-wk-event="talent_request"><?php esc_html_e( 'Send enquiry', 'oria' ); ?></button>
			<?php else : ?>
				<?php $oria_mk = '\Oria\Core\Work\Market\\'; ?>
				<div class="wkform__row">
					<div><label for="tr-name"><?php esc_html_e( 'Your name', 'oria' ); ?></label><input class="input" id="tr-name" name="name" required maxlength="80" autocomplete="name"></div>
					<div><label for="tr-email"><?php esc_html_e( 'Work email', 'oria' ); ?></label><input class="input" id="tr-email" name="email" type="email" required maxlength="120" autocomplete="email"></div>
				</div>
				<div class="wkform__row">
					<div><label for="tr-org"><?php esc_html_e( 'Company (optional)', 'oria' ); ?></label><input class="input" id="tr-org" name="org" maxlength="120" autocomplete="organization"></div>
					<div><label for="tr-where"><?php esc_html_e( 'Suburb or location in Perth', 'oria' ); ?></label><input class="input" id="tr-where" name="where" maxlength="120"></div>
				</div>
				<div class="wkform__row">
					<div>
						<label for="tr-size"><?php esc_html_e( 'Roughly how many people?', 'oria' ); ?></label>
						<select class="input" id="tr-size" name="group_size" required>
							<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
							<?php foreach ( constant( $oria_mk . 'GROUP_SIZES' ) as $oria_k => $oria_l ) : ?>
								<option value="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_l ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div><label for="tr-when"><?php esc_html_e( 'Preferred date, or how flexible you are', 'oria' ); ?></label><input class="input" id="tr-when" name="when" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. a Thursday in November', 'oria' ); ?>"></div>
				</div>
				<div class="wkform__row">
					<div>
						<label for="tr-budget"><?php esc_html_e( 'Total budget for the whole group', 'oria' ); ?></label>
						<select class="input" id="tr-budget" name="budget_total">
							<?php foreach ( constant( $oria_mk . 'GROUP_BUDGETS' ) as $oria_k => $oria_l ) : ?>
								<option value="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_l ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="tr-format"><?php esc_html_e( 'What kind of session?', 'oria' ); ?></label>
						<select class="input" id="tr-format" name="format" required>
							<option value=""><?php esc_html_e( 'Choose…', 'oria' ); ?></option>
							<?php foreach ( constant( $oria_mk . 'GROUP_FORMATS' ) as $oria_k => $oria_l ) : ?>
								<option value="<?php echo esc_attr( $oria_k ); ?>"><?php echo esc_html( $oria_l ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<label for="tr-need"><?php echo esc_html( $oria_copy['need'] ); ?></label>
				<textarea class="input" id="tr-need" name="need" rows="3" maxlength="2000" placeholder="<?php esc_attr_e( 'Please leave out any health or medical details.', 'oria' ); ?>"></textarea>
				<label class="wkcheck"><input type="checkbox" name="contact_ok" value="1" required> <span><?php esc_html_e( 'You may contact me about this request.', 'oria' ); ?></span></label>
				<label class="wkcheck"><input type="checkbox" name="marketing_ok" value="1"> <span><?php esc_html_e( 'Optional: send me occasional news about workplace wellness in Perth.', 'oria' ); ?></span></label>
				<button class="btn btn--dark" type="submit" data-wk-event="talent_request"><?php esc_html_e( 'Send my request', 'oria' ); ?></button>
			<?php endif; ?>
		</form>
	</div>
</section>
