<?php
/**
 * /oria-pass/ — the Oria Pass landing page.
 *
 * Rendered by a route in the oria-pass plugin, not by a WordPress page, so
 * it exists wherever the code does.
 *
 * The order is the brief's, with one deliberate exception: the example
 * experiences come before the explanation. Somebody who lands here wants to
 * know what they could book, and three screens of concept before a single
 * card is how a membership page reads as marketing rather than a product.
 *
 * What the page will NOT do while the mode is Waitlist or Invite: offer a
 * Join button that takes money. There is no billing, no credit and no
 * session behind it yet, and the surest way to burn the launch is to take
 * an email address under a promise the site cannot keep this week.
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

use Oria\Pass\Settings;
use Oria\Pass\Waitlist;

$oria_mode    = Settings\mode();
$oria_live    = Settings\is_live();
$oria_price   = (string) Settings\get( 'price_display' );
$oria_period  = (string) Settings\get( 'price_period' );
$oria_credits = (int) Settings\get( 'credits_per_cycle' );
$oria_city    = (string) Settings\get( 'city' );
$oria_cta     = Settings\cta_label();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$oria_state = sanitize_key( (string) ( $_GET['pass'] ?? '' ) );
$oria_kind  = 'partner' === ( $_GET['kind'] ?? '' ) ? 'partner' : 'member';
// phpcs:enable

$oria_args = array(
	'mode'    => $oria_mode,
	'live'    => $oria_live,
	'price'   => $oria_price,
	'period'  => $oria_period,
	'credits' => $oria_credits,
	'city'    => $oria_city,
	'cta'     => $oria_cta,
	'state'   => $oria_state,
	'kind'    => $oria_kind,
);

get_header();
?>

<div class="pass" data-oria-event="oria_pass_view" data-pass-mode="<?php echo esc_attr( $oria_mode ); ?>">

	<?php
	get_template_part( 'template-parts/pass/hero', null, $oria_args );
	get_template_part( 'template-parts/pass/valuebar', null, $oria_args );
	/*
	 * Inventory this early is the point. In Waitlist mode these are clearly
	 * labelled as examples -- the brief is explicit that production must
	 * never show availability that does not exist.
	 */
	get_template_part( 'template-parts/pass/experiences', null, $oria_args );
	get_template_part( 'template-parts/pass/how', null, $oria_args );
	get_template_part( 'template-parts/pass/mood', null, $oria_args );
	get_template_part( 'template-parts/pass/membership', null, $oria_args );
	get_template_part( 'template-parts/pass/compare', null, $oria_args );
	get_template_part( 'template-parts/pass/join', null, $oria_args );
	get_template_part( 'template-parts/pass/providers', null, $oria_args );
	get_template_part( 'template-parts/pass/faq', null, $oria_args );
	get_template_part( 'template-parts/pass/sticky', null, $oria_args );
	?>

</div>

<?php
get_footer();
