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
		'kicker' => __( 'Corporate wellness', 'oria' ),
		'lede'   => __( 'Yoga, meditation, breathwork, chair massage and movement sessions for wellbeing days, conferences and ongoing staff programs — delivered by practitioners who already work with Oria Haven.', 'oria' ),
		'for'    => __( 'Available for corporate wellness', 'oria' ),
		'ask'    => __( 'Tell us about your workplace', 'oria' ),
		'need'   => __( 'What would you like? (e.g. a weekly lunchtime yoga class for 20 staff, or chair massage for a wellbeing day)', 'oria' ),
	);
?>
<header class="wkhero wkhero--land">
	<div class="wrap">
		<p class="micro wkhero__kicker"><?php echo esc_html( $oria_copy['kicker'] ); ?></p>
		<h1 class="wkhero__title"><?php echo esc_html( Work\heading() ); ?></h1>
		<p class="lede wkhero__lede"><?php echo esc_html( $oria_copy['lede'] ); ?></p>
		<p class="wkhero__alt">
			<a class="btn btn--light" href="#enquire"><?php echo esc_html( $oria_copy['ask'] ); ?></a>
			<a class="btn btn--ghost-on-deep" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>"><?php esc_html_e( 'Practitioner? Add yourself', 'oria' ); ?></a>
		</p>
	</div>
</header>

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
		<p class="hint"><?php esc_html_e( 'This comes to a person at Oria Haven, not to a list. We reply within one business day, and only share your details with practitioners you choose.', 'oria' ); ?></p>
		<?php if ( $oria_note ) : ?>
			<div class="notice notice--<?php echo 'ok' === $oria_note['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_note['text'] ); ?></div>
		<?php endif; ?>
		<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo Work\form_fields( 'oria_work_talent_request' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<input type="hidden" name="market" value="<?php echo esc_attr( $oria_m ); ?>">
			<input type="hidden" name="wk_ts" value="<?php echo (int) time(); ?>">
			<div class="wk-trap" aria-hidden="true"><label>Website <input type="text" name="wk_website" tabindex="-1" autocomplete="off"></label></div>
			<div class="wkform__row">
				<div><label for="tr-org"><?php echo esc_html( 'retreat' === $oria_m ? __( 'Retreat or organiser', 'oria' ) : __( 'Organisation', 'oria' ) ); ?></label><input class="input" id="tr-org" name="org" required maxlength="120" autocomplete="organization"></div>
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
				<div><label for="tr-people"><?php echo esc_html( 'retreat' === $oria_m ? __( 'Guests', 'oria' ) : __( 'Staff taking part', 'oria' ) ); ?></label><input class="input" id="tr-people" name="people" maxlength="40" inputmode="numeric"></div>
				<div><label for="tr-budget"><?php esc_html_e( 'Budget (optional)', 'oria' ); ?></label><input class="input" id="tr-budget" name="budget" maxlength="80"></div>
			</div>
			<button class="btn btn--dark" type="submit" data-wk-event="talent_request"><?php esc_html_e( 'Send enquiry', 'oria' ); ?></button>
		</form>
	</div>
</section>
