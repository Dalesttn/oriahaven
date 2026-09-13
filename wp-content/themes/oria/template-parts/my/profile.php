<?php
/**
 * Profile: name, what they are after, where, email, password, and the way
 * to have it all deleted. Nothing about health is asked; the preferences
 * are the same chips the directory filters by.
 */

declare(strict_types=1);

use Oria\Core\MyOria;
use Oria\Core\Recommend;

$oria_uid   = get_current_user_id();
$oria_user  = get_userdata( $oria_uid );
$oria_prefs = Recommend\prefs( $oria_uid );
?>
<section class="wrap my my--last">
	<div class="my__hello">
		<span class="micro"><?php esc_html_e( 'Profile', 'oria' ); ?></span>
		<h1 class="h1"><?php esc_html_e( 'Your details and interests', 'oria' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'What you tell us here only shapes what we suggest. We never ask about your health, and we never share it.', 'oria' ); ?></p>
	</div>

	<form class="myform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="oria_my_profile">
		<?php wp_nonce_field( 'oria_my_profile', 'oria_my_nonce' ); ?>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'About you', 'oria' ); ?></legend>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'First name', 'oria' ); ?></span>
				<input class="input" type="text" name="first_name" value="<?php echo esc_attr( MyOria\first_name( $oria_uid ) ); ?>" required autocomplete="given-name">
			</label>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Email', 'oria' ); ?></span>
				<input class="input" type="email" value="<?php echo esc_attr( $oria_user ? $oria_user->user_email : '' ); ?>" readonly>
				<span class="hint"><?php esc_html_e( 'To change your email, write to hello@oriahaven.com.au from this address.', 'oria' ); ?></span>
			</label>
		</fieldset>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'What are you looking for?', 'oria' ); ?></legend>
			<div class="mychips">
				<?php foreach ( Recommend\INTENTS as $oria_k => $oria_l ) : ?>
					<label class="mychip"><input type="checkbox" name="intents[]" value="<?php echo esc_attr( $oria_k ); ?>"<?php checked( in_array( $oria_k, $oria_prefs['intents'], true ) ); ?>><span><?php echo esc_html( $oria_l ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'What would you like to explore?', 'oria' ); ?></legend>
			<div class="mychips">
				<?php foreach ( Recommend\INTERESTS as $oria_k => $oria_i ) : ?>
					<label class="mychip"><input type="checkbox" name="interests[]" value="<?php echo esc_attr( $oria_k ); ?>"<?php checked( in_array( $oria_k, $oria_prefs['interests'], true ) ); ?>><span><?php echo esc_html( $oria_i['label'] ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'Where suits you?', 'oria' ); ?></legend>
			<label class="field">
				<span class="field__label"><?php esc_html_e( 'Preferred area', 'oria' ); ?></span>
				<select class="select" name="area">
					<option value=""><?php esc_html_e( 'No preference', 'oria' ); ?></option>
					<?php foreach ( Recommend\areas() as $oria_k => $oria_l ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>"<?php selected( $oria_k, $oria_prefs['area'] ); ?>><?php echo esc_html( $oria_l ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint"><?php esc_html_e( 'A region, not an address. Suggestions from nearby rise a little.', 'oria' ); ?></span>
			</label>
		</fieldset>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'Email', 'oria' ); ?></legend>
			<label class="mycheck">
				<input type="checkbox" name="email_updates" value="1"<?php checked( $oria_prefs['email'] ); ?>>
				<span><?php esc_html_e( 'Send me an occasional email with things to try, based on my interests. Never more than a few a month; unsubscribe here any time.', 'oria' ); ?></span>
			</label>
			<span class="hint"><?php esc_html_e( 'Account emails — a password reset, say — are sent regardless.', 'oria' ); ?></span>
		</fieldset>

		<fieldset class="myform__group">
			<legend class="h3"><?php esc_html_e( 'Change password', 'oria' ); ?></legend>
			<div class="myform__row">
				<label class="field">
					<span class="field__label"><?php esc_html_e( 'New password', 'oria' ); ?></span>
					<input class="input" type="password" name="password" minlength="8" autocomplete="new-password">
				</label>
				<label class="field">
					<span class="field__label"><?php esc_html_e( 'Repeat it', 'oria' ); ?></span>
					<input class="input" type="password" name="password2" minlength="8" autocomplete="new-password">
				</label>
			</div>
			<span class="hint"><?php esc_html_e( 'Leave blank to keep your current password. At least 8 characters.', 'oria' ); ?></span>
		</fieldset>

		<div class="myform__acts">
			<button class="btn btn--dark" type="submit"><?php esc_html_e( 'Save profile', 'oria' ); ?><?php echo \Oria\Theme\arrow(); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<a class="btn btn--ghost" href="<?php echo esc_url( MyOria\logout_url() ); ?>"><?php esc_html_e( 'Log out', 'oria' ); ?></a>
		</div>
	</form>

	<div class="mydata">
		<span class="micro"><?php esc_html_e( 'Your data', 'oria' ); ?></span>
		<p class="muted"><?php esc_html_e( 'My Oria holds your name, email, these interests, and the places you saved or marked as tried. Nothing else. Remove any place from its list, or ask us to delete the whole account and we will, within a few days.', 'oria' ); ?></p>
		<a class="mydata__link" href="<?php echo esc_url( 'mailto:hello@oriahaven.com.au?subject=' . rawurlencode( 'Delete my My Oria account' ) ); ?>"><?php esc_html_e( 'Ask us to delete my account', 'oria' ); ?></a>
	</div>
</section>
