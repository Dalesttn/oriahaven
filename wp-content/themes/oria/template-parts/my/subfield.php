<?php
/**
 * One column inside a repeater row.
 *
 * Split out from field.php once team arrived: a service row is a single
 * text box, but a practitioner is a name, a photo, qualifications, a
 * registration, a consent tick and more. Rendering those inline would
 * have meant a second, thinner copy of the field renderer living inside
 * the first one.
 *
 * Every input's name is built here as thing[INDEX][column]; the index is
 * rewritten by my-listing.js whenever rows are added, removed or moved,
 * so PHP always reads them as a contiguous list.
 *
 * $args: sub (registry array), name (repeater field name), index (int|string),
 *        row (the stored row), listing (int), disabled (string)
 */

declare(strict_types=1);

use Oria\Core\ListingEditor as Ed;

$sub     = (array) ( $args['sub'] ?? array() );
$rname   = (string) ( $args['name'] ?? '' );
$i       = (string) ( $args['index'] ?? '0' );
$row     = (array) ( $args['row'] ?? array() );
$listing = (int) ( $args['listing'] ?? 0 );
$dis     = (string) ( $args['disabled'] ?? '' );

if ( ! $sub || '' === $rname ) {
	return;
}

$type  = (string) $sub['type'];
$col   = (string) $sub['name'];
$val   = $row[ $col ] ?? '';
$input = sprintf( '%s[%s][%s]', $rname, $i, $col );
$id    = sanitize_html_class( $rname . '-' . $i . '-' . $col );
$help  = ! empty( $sub['help'] ) ? $id . '-help' : '';
$span  = ! empty( $sub['span'] ) ? ' myrow__col--' . sanitize_html_class( (string) $sub['span'] ) : '';
?>

<div class="myrow__col myrow__col--<?php echo esc_attr( $type ); ?><?php echo esc_attr( $span ); ?>">

	<?php if ( 'toggle' === $type ) : ?>
		<?php // A single yes/no reads as a statement you agree with, not a labelled box. ?>
		<label class="myopt myopt--wide">
			<input type="checkbox" name="<?php echo esc_attr( $input ); ?>" value="1"
				<?php checked( (bool) $val ); ?>
				<?php echo $help ? ' aria-describedby="' . esc_attr( $help ) . '"' : ''; ?><?php echo $dis; ?>>
			<span><?php echo esc_html( $sub['label'] ); ?></span>
		</label>

	<?php elseif ( 'multi' === $type ) : ?>
		<?php $opts = Ed\choices_for( $sub ); ?>
		<fieldset class="myrow__set"<?php echo $dis; ?>>
			<legend class="myrow__label"><?php echo esc_html( $sub['label'] ); ?></legend>
			<?php if ( $opts ) : ?>
				<?php $on = array_map( 'strval', (array) $val ); ?>
				<div class="myopts myopts--tight">
					<?php foreach ( $opts as $ok => $ov ) : ?>
						<label class="myopt myopt--sm">
							<input type="checkbox" name="<?php echo esc_attr( $input ); ?>[]" value="<?php echo esc_attr( (string) $ok ); ?>"
								<?php checked( in_array( (string) $ok, $on, true ) ); ?><?php echo $dis; ?>>
							<span><?php echo esc_html( (string) $ov ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="myrow__none"><?php esc_html_e( 'Nothing to pick yet — add a service to this listing first.', 'oria' ); ?></p>
			<?php endif; ?>
		</fieldset>

	<?php elseif ( 'image' === $type ) : ?>
		<?php
		$img = (int) $val;
		$src = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
		?>
		<span class="myrow__label" id="<?php echo esc_attr( $id ); ?>-lbl"><?php echo esc_html( $sub['label'] ); ?></span>
		<div class="mypick" data-mypick>
			<input type="hidden" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( (string) $img ); ?>" data-mypick-val>
			<span class="mypick__frame" data-mypick-frame>
				<?php if ( $src ) : ?>
					<img src="<?php echo esc_url( $src ); ?>" alt="" width="64" height="64" decoding="async">
				<?php endif; ?>
			</span>
			<span class="mypick__acts">
				<?php
				/*
				 * The buttons only appear for somebody who can actually
				 * upload. Without the capability the media modal is never
				 * loaded, and a Choose button that opens nothing is worse
				 * than none -- so they get the current photo, the hidden
				 * value so saving does not wipe it, and a line saying who
				 * to ask.
				 */
				if ( current_user_can( 'upload_files' ) ) :
					?>
					<button class="mypick__btn" type="button" data-mypick-choose
						aria-describedby="<?php echo esc_attr( $id ); ?>-lbl"<?php echo $dis; ?>>
						<?php echo $img ? esc_html__( 'Change', 'oria' ) : esc_html__( 'Choose a photo', 'oria' ); ?>
					</button>
					<button class="mypick__btn mypick__btn--x" type="button" data-mypick-clear
						<?php echo $img ? '' : 'hidden'; ?><?php echo $dis; ?>><?php esc_html_e( 'Remove', 'oria' ); ?></button>
				<?php else : ?>
					<span class="mypick__no"><?php esc_html_e( 'Ask Oria Haven to add a photo for this person.', 'oria' ); ?></span>
				<?php endif; ?>
			</span>
		</div>

	<?php else : ?>
		<label class="myrow__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $sub['label'] ); ?></label>

		<?php if ( 'textarea' === $type ) : ?>
			<textarea class="input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>"
				rows="<?php echo esc_attr( (string) ( $sub['rows'] ?? 2 ) ); ?>"
				<?php echo ! empty( $sub['max'] ) ? ' maxlength="' . esc_attr( (string) $sub['max'] ) . '"' : ''; ?>
				<?php echo ! empty( $sub['placeholder'] ) ? ' placeholder="' . esc_attr( $sub['placeholder'] ) . '"' : ''; ?>
				<?php echo $help ? ' aria-describedby="' . esc_attr( $help ) . '"' : ''; ?>
				<?php echo $dis; ?>><?php echo esc_textarea( (string) $val ); ?></textarea>
		<?php else : ?>
			<?php $html_type = in_array( $type, array( 'url', 'email', 'tel', 'number' ), true ) ? $type : 'text'; ?>
			<input class="input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>"
				type="<?php echo esc_attr( $html_type ); ?>" value="<?php echo esc_attr( (string) $val ); ?>"
				<?php echo 'number' === $type ? ' min="0" step="1"' : ''; ?>
				<?php echo ! empty( $sub['max'] ) ? ' maxlength="' . esc_attr( (string) $sub['max'] ) . '"' : ''; ?>
				<?php echo ! empty( $sub['placeholder'] ) ? ' placeholder="' . esc_attr( $sub['placeholder'] ) . '"' : ''; ?>
				<?php echo $help ? ' aria-describedby="' . esc_attr( $help ) . '"' : ''; ?><?php echo $dis; ?>>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $help ) : ?>
		<p class="myrow__help" id="<?php echo esc_attr( $help ); ?>"><?php echo esc_html( $sub['help'] ); ?></p>
	<?php endif; ?>
</div>
