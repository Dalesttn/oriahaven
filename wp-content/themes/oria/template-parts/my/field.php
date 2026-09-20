<?php
/**
 * One field, drawn from the registry rather than written out by hand.
 *
 * Every control gets a real <label> tied to it, and its help text tied on
 * with aria-describedby, because a hint the screen reader never reaches is
 * a hint only some people get. Inputs are 16px minimum (see my-oria.css):
 * anything smaller and a phone zooms on focus, which moves the page out
 * from under whoever is typing.
 *
 * A field the owner's plan does not include is still drawn -- greyed, with
 * the line that says what it would do for them. A padlock with no
 * explanation tells somebody they are being charged without telling them
 * what for.
 *
 * $args: field (registry array), listing (int)
 */

declare(strict_types=1);

use Oria\Core\ListingEditor as Ed;
use Oria\Core\Tiers;

$f       = (array) ( $args['field'] ?? array() );
$listing = (int) ( $args['listing'] ?? 0 );
if ( ! $f || ! $listing ) {
	return;
}

$name  = (string) $f['name'];
$type  = (string) $f['type'];
$val   = Ed\value( $listing, $name );
$open  = Ed\editable( $listing, $name );
$id    = 'f-' . $name;
$descs = array();

if ( ! empty( $f['help'] ) ) {
	$descs[] = $id . '-help';
}
if ( ! empty( $f['hint'] ) ) {
	$descs[] = $id . '-hint';
}
if ( ! $open ) {
	$descs[] = $id . '-lock';
}
$described = $descs ? implode( ' ', $descs ) : '';
$dis       = $open ? '' : ' disabled';
?>

<div class="myfield myfield--<?php echo esc_attr( $type ); ?><?php echo $open ? '' : ' is-locked'; ?>">

	<?php if ( in_array( $type, array( 'radios', 'checks', 'repeater', 'gallery' ), true ) ) : ?>
		<?php // A group of controls is labelled by its legend, not by a <label>. ?>
		<fieldset class="myfield__set"<?php echo $dis; ?>>
			<legend class="myfield__label"><?php echo esc_html( $f['label'] ); ?></legend>
	<?php else : ?>
		<label class="myfield__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
	<?php endif; ?>

	<?php if ( ! empty( $f['help'] ) ) : ?>
		<p class="myfield__help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $f['help'] ); ?></p>
	<?php endif; ?>

	<?php
	switch ( $type ) :

		case 'prose':
			/*
			 * Stored as paragraphs of HTML; edited as plain text with blank
			 * lines between paragraphs, which is how the sanitiser reads it
			 * back. Nobody should have to see a <p> to edit a description.
			 */
			$val = trim( wp_strip_all_tags( preg_replace( '#</p>\s*<p[^>]*>#i', "\n\n", (string) $val ) ) );
			// fall through
		case 'textarea':
			?>
			<textarea class="input myfield__area" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				rows="<?php echo esc_attr( (string) ( $f['rows'] ?? 4 ) ); ?>"
				<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
				<?php echo ! empty( $f['max'] ) ? ' maxlength="' . esc_attr( (string) $f['max'] ) . '"' : ''; ?>
				<?php echo ! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : ''; ?>
				<?php echo $dis; ?>><?php echo esc_textarea( (string) $val ); ?></textarea>
			<?php
			break;

		case 'money':
			?>
			<div class="myfield__money">
				<span class="myfield__cur" aria-hidden="true">$</span>
				<input class="input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					type="text" inputmode="decimal" value="<?php echo esc_attr( (string) $val ); ?>"
					<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?><?php echo $dis; ?>>
				<span class="myfield__unit"><?php esc_html_e( 'AUD', 'oria' ); ?></span>
			</div>
			<?php
			break;

		case 'number':
			?>
			<div class="myfield__money">
				<input class="input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
					type="number" min="0" step="1" value="<?php echo esc_attr( (string) $val ); ?>"
					<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?><?php echo $dis; ?>>
				<?php if ( ! empty( $f['unit'] ) ) : ?>
					<span class="myfield__unit"><?php echo esc_html( $f['unit'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php
			break;

		case 'date':
			?>
			<input class="input myfield__date" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				type="date" value="<?php echo esc_attr( preg_match( '/^\d{8}$/', (string) $val ) ? gmdate( 'Y-m-d', (int) strtotime( (string) $val ) ) : (string) $val ); ?>"
				<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?><?php echo $dis; ?>>
			<?php
			break;

		case 'select':
			?>
			<select class="input myfield__select" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?><?php echo $dis; ?>>
				<option value=""><?php echo esc_html( $f['blank'] ?? __( 'Choose one', 'oria' ) ); ?></option>
				<?php foreach ( (array) $f['choices'] as $ck => $cv ) : ?>
					<option value="<?php echo esc_attr( (string) $ck ); ?>" <?php selected( (string) $val, (string) $ck ); ?>><?php echo esc_html( (string) $cv ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			break;

		case 'radios':
			?>
			<div class="myopts">
				<?php foreach ( (array) $f['choices'] as $ck => $cv ) : ?>
					<label class="myopt">
						<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $ck ); ?>"
							<?php checked( (string) $val, (string) $ck ); ?><?php echo $dis; ?>>
						<span><?php echo esc_html( (string) $cv ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
			break;

		case 'checks':
			$on = is_array( $val ) ? array_map( 'strval', $val ) : array();
			?>
			<div class="myopts myopts--grid">
				<?php foreach ( (array) $f['choices'] as $ck => $cv ) : ?>
					<label class="myopt">
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (string) $ck ); ?>"
							<?php checked( in_array( (string) $ck, $on, true ) ); ?><?php echo $dis; ?>>
						<span><?php echo esc_html( (string) $cv ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
			break;

		case 'gallery':
			$shots = array_values( array_filter( array_map( 'intval', (array) $val ) ) );
			$limit = \Oria\Core\Tiers\gallery_limit( $listing );
			?>
			<div class="mygal" data-mygal data-name="<?php echo esc_attr( $name ); ?>" data-max="<?php echo esc_attr( (string) $limit ); ?>">
				<ul class="mygal__grid" data-mygal-grid>
					<?php foreach ( $shots as $pos => $shot ) : ?>
						<?php $thumb = wp_get_attachment_image_url( $shot, 'medium' ); ?>
						<li class="mygal__item" data-mygal-item>
							<input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (string) $shot ); ?>">
							<span class="mygal__shot">
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" decoding="async">
								<?php endif; ?>
							</span>
							<?php // The first photo leads everywhere, so it is named rather than merely first. ?>
							<span class="mygal__lead" data-mygal-lead<?php echo 0 === $pos ? '' : ' hidden'; ?>><?php esc_html_e( 'Cover', 'oria' ); ?></span>
							<span class="mygal__acts">
								<button class="mygal__btn" type="button" data-mygal-up<?php echo $dis; ?>>
									<span aria-hidden="true">&larr;</span><span class="sr-only"><?php esc_html_e( 'Move earlier', 'oria' ); ?></span>
								</button>
								<button class="mygal__btn" type="button" data-mygal-down<?php echo $dis; ?>>
									<span aria-hidden="true">&rarr;</span><span class="sr-only"><?php esc_html_e( 'Move later', 'oria' ); ?></span>
								</button>
								<button class="mygal__btn mygal__btn--x" type="button" data-mygal-del<?php echo $dis; ?>>
									<span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Remove this photo', 'oria' ); ?></span>
								</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( ! $shots ) : ?>
					<p class="mygal__none" data-mygal-none><?php esc_html_e( 'No photos yet.', 'oria' ); ?></p>
				<?php else : ?>
					<p class="mygal__none" data-mygal-none hidden><?php esc_html_e( 'No photos yet.', 'oria' ); ?></p>
				<?php endif; ?>

				<?php if ( $open && current_user_can( 'upload_files' ) ) : ?>
					<div class="mygal__foot">
						<button class="mygal__add" type="button" data-mygal-add>
							<span aria-hidden="true">+</span> <?php esc_html_e( 'Add photos', 'oria' ); ?>
						</button>
						<p class="mygal__count" data-mygal-count aria-live="polite"></p>
					</div>
					<?php
					$note = \Oria\Core\Tiers\gallery_note( $listing );
					if ( '' !== $note ) :
						?>
						<p class="mygal__note"><?php echo esc_html( $note ); ?></p>
					<?php endif; ?>
				<?php elseif ( $open ) : ?>
					<p class="mygal__note"><?php esc_html_e( 'Ask Oria Haven to add photos to your listing.', 'oria' ); ?></p>
				<?php endif; ?>
			</div>
			<?php
			break;

		case 'repeater':
			$rows = is_array( $val ) ? array_values( $val ) : array();
			$cap  = (int) ( $f['max_rows'] ?? 0 );
			$card = 'card' === ( $f['layout'] ?? '' );
			?>
			<div class="myrep<?php echo $card ? ' myrep--card' : ''; ?>" data-myrep
				data-name="<?php echo esc_attr( $name ); ?>"
				data-max="<?php echo esc_attr( (string) $cap ); ?>"
				data-single="<?php echo esc_attr( $f['single'] ?? __( 'row', 'oria' ) ); ?>">
				<div class="myrep__rows" data-myrep-rows>
					<?php foreach ( $rows as $i => $row ) : ?>
						<div class="myrow" data-myrep-row>
							<?php if ( $card ) : ?>
								<p class="myrow__n" aria-hidden="true"><?php echo esc_html( (string) ( (int) $i + 1 ) ); ?></p>
							<?php endif; ?>
							<div class="myrow__cols">
								<?php foreach ( $f['sub'] as $sub ) : ?>
									<?php
									get_template_part(
										'template-parts/my/subfield',
										null,
										array(
											'sub'      => $sub,
											'name'     => $name,
											'index'    => $i,
											'row'      => $row,
											'listing'  => $listing,
											'disabled' => $dis,
										)
									);
									?>
								<?php endforeach; ?>
							</div>
							<?php // Move up/down as well as remove: drag is not the only way to order a list. ?>
							<div class="myrow__acts">
								<button class="myrow__btn" type="button" data-myrep-up<?php echo $dis; ?>>
									<span aria-hidden="true">&uarr;</span><span class="sr-only"><?php esc_html_e( 'Move up', 'oria' ); ?></span>
								</button>
								<button class="myrow__btn" type="button" data-myrep-down<?php echo $dis; ?>>
									<span aria-hidden="true">&darr;</span><span class="sr-only"><?php esc_html_e( 'Move down', 'oria' ); ?></span>
								</button>
								<?php
								/*
								 * A submit, not a plain button. With JavaScript it
								 * is intercepted and the row disappears at once;
								 * without it -- a blocked script, a stale cache,
								 * a listener that never bound -- it posts which row
								 * to drop and the server drops it. Remove is the one
								 * control here that must never quietly do nothing.
								 */
								?>
								<button class="myrow__btn myrow__btn--x" type="submit"
									name="oria_drop" value="<?php echo esc_attr( $name . ':' . $i ); ?>"
									data-myrep-del<?php echo $dis; ?>>
									<span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Remove', 'oria' ); ?></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $open ) : ?>
					<button class="myrep__add" type="button" data-myrep-add>
						<span aria-hidden="true">+</span> <?php echo esc_html( $f['add'] ?? __( 'Add', 'oria' ) ); ?>
					</button>
					<?php if ( $cap ) : ?>
						<p class="myrep__cap" data-myrep-cap hidden>
							<?php
							printf(
								/* translators: %d: the most rows allowed */
								esc_html__( 'That is the most you can add here (%d).', 'oria' ),
								(int) $cap
							);
							?>
						</p>
					<?php endif; ?>

					<?php // The blank row the Add button clones. Its indexes are renumbered on insert. ?>
					<template data-myrep-tpl>
						<div class="myrow" data-myrep-row>
							<?php if ( $card ) : ?>
								<p class="myrow__n" aria-hidden="true"></p>
							<?php endif; ?>
							<div class="myrow__cols">
								<?php foreach ( $f['sub'] as $sub ) : ?>
									<?php
									get_template_part(
										'template-parts/my/subfield',
										null,
										array(
											'sub'      => $sub,
											'name'     => $name,
											'index'    => '__i__',
											'row'      => array(),
											'listing'  => $listing,
											'disabled' => '',
										)
									);
									?>
								<?php endforeach; ?>
							</div>
							<div class="myrow__acts">
								<button class="myrow__btn" type="button" data-myrep-up><span aria-hidden="true">&uarr;</span><span class="sr-only"><?php esc_html_e( 'Move up', 'oria' ); ?></span></button>
								<button class="myrow__btn" type="button" data-myrep-down><span aria-hidden="true">&darr;</span><span class="sr-only"><?php esc_html_e( 'Move down', 'oria' ); ?></span></button>
								<button class="myrow__btn myrow__btn--x" type="submit" name="oria_drop" value="<?php echo esc_attr( $name . ':__i__' ); ?>" data-myrep-del><span aria-hidden="true">&times;</span><span class="sr-only"><?php esc_html_e( 'Remove', 'oria' ); ?></span></button>
							</div>
						</div>
					</template>
				<?php endif; ?>
			</div>
			<?php
			break;

		default:
			$html_type = in_array( $type, array( 'url', 'email', 'tel' ), true ) ? $type : 'text';
			?>
			<input class="input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				type="<?php echo esc_attr( $html_type ); ?>" value="<?php echo esc_attr( (string) $val ); ?>"
				<?php echo ! empty( $f['max'] ) ? ' maxlength="' . esc_attr( (string) $f['max'] ) . '"' : ''; ?>
				<?php echo ! empty( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : ''; ?>
				<?php echo $described ? ' aria-describedby="' . esc_attr( $described ) . '"' : ''; ?><?php echo $dis; ?>>
			<?php
			break;

	endswitch;
	?>

	<?php if ( ! empty( $f['hint'] ) ) : ?>
		<p class="myfield__hint" id="<?php echo esc_attr( $id ); ?>-hint"><?php echo esc_html( $f['hint'] ); ?></p>
	<?php endif; ?>

	<?php
	/*
	 * A cap on how much of this field the plan PUBLISHES -- distinct from
	 * the lock below, which is about whether the field opens at all.
	 */
	$oria_plan_note = Ed\plan_note( $f, $listing );
	if ( '' !== $oria_plan_note ) :
		?>
		<p class="myfield__plan"><?php echo esc_html( $oria_plan_note ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $f['public'] ) ) : ?>
		<p class="myfield__pub"><?php esc_html_e( 'This appears on your public profile.', 'oria' ); ?></p>
	<?php endif; ?>

	<?php if ( ! $open ) : ?>
		<p class="myfield__lock" id="<?php echo esc_attr( $id ); ?>-lock">
			<?php
			$sell = Tiers\field_sell( $name );
			echo esc_html( '' !== $sell ? $sell : __( 'Part of the Claimed plan.', 'oria' ) );
			?>
			<?php if ( function_exists( '\Oria\Core\Billing\configured' ) && \Oria\Core\Billing\configured() ) : ?>
				<a href="<?php echo esc_url( \Oria\Core\Billing\pay_url( 'claimed', $listing, (string) wp_get_current_user()->user_email ) ); ?>">
					<?php esc_html_e( 'Unlock it with Claimed', 'oria' ); ?>
				</a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( in_array( $type, array( 'radios', 'checks', 'repeater', 'gallery' ), true ) ) : ?>
		</fieldset>
	<?php endif; ?>
</div>
