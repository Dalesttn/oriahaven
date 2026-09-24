<?php
/**
 * "Your first visit" — the block that answers what stops people booking.
 *
 * Four things in the order somebody worries about them: when to turn up,
 * what to wear, what to bring, and then what actually happens once they are
 * inside. The awkward question goes last and on its own, because it is the
 * one people would never ask a receptionist and the one that decides it.
 *
 * The facts row is read from the Compare registry rather than written out
 * here, so how long a session runs is stated in exactly one place on the
 * site. A session the registry says nothing about simply has fewer facts —
 * no row ever says "unknown".
 *
 * It says whose answer this is, in the page, rather than letting a reader
 * assume the studio wrote it. That line is not boilerplate: the whole block
 * is only defensible because it describes the kind of session rather than
 * the business, and the reader has to be told which one they are reading.
 *
 * Args: guide (array from Oria\Core\FirstVisit\for_term), id (string).
 *
 * @package Oria
 */

defined( 'ABSPATH' ) || exit;

$oria_g  = (array) ( $args['guide'] ?? array() );
$oria_id = (string) ( $args['id'] ?? 'first-visit' );

if ( ! $oria_g || '' === (string) ( $oria_g['lede'] ?? '' ) ) {
	return;
}

// When, what to wear, what to bring: three short answers, one row.
$oria_prep = array_values(
	array_filter(
		array(
			array( 'label' => __( 'When to arrive', 'oria' ), 'text' => (string) ( $oria_g['arrive'] ?? '' ) ),
			array( 'label' => __( 'What to wear', 'oria' ), 'text' => (string) ( $oria_g['wear'] ?? '' ) ),
			array( 'label' => __( 'What to bring', 'oria' ), 'text' => (string) ( $oria_g['bring'] ?? '' ) ),
		),
		static fn( array $r ): bool => '' !== $r['text']
	)
);
?>
<section class="wrap section floor fvisit" id="<?php echo esc_attr( $oria_id ); ?>">
	<h2 class="micro floor__label"><?php esc_html_e( 'First visit', 'oria' ); ?></h2>

	<h2 class="h3 fvisit__h">
		<?php
		/* translators: %s: the kind of session, lowercased */
		printf( esc_html__( 'What to expect at your first %s session', 'oria' ), esc_html( strtolower( (string) $oria_g['title'] ) ) );
		?>
	</h2>

	<p class="lede fvisit__lede"><?php echo esc_html( (string) $oria_g['lede'] ); ?></p>

	<?php if ( $oria_g['facts'] ) : ?>
		<ul class="fvisit__facts">
			<?php foreach ( $oria_g['facts'] as $oria_fact ) : ?>
				<li class="fvisit__fact">
					<span class="fvisit__fact-l"><?php echo esc_html( $oria_fact['label'] ); ?></span>
					<b class="fvisit__fact-v"><?php echo esc_html( $oria_fact['value'] ); ?></b>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="fvisit__cols">

		<?php if ( $oria_prep ) : ?>
			<div class="fvisit__prep">
				<?php foreach ( $oria_prep as $oria_row ) : ?>
					<div class="fvisit__prep-row">
						<h3 class="fvisit__sub"><?php echo esc_html( $oria_row['label'] ); ?></h3>
						<p><?php echo esc_html( $oria_row['text'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $oria_g['steps'] ) : ?>
			<div class="fvisit__steps">
				<h3 class="fvisit__sub"><?php esc_html_e( 'How the session goes', 'oria' ); ?></h3>
				<?php // Numbered because these genuinely happen in this order. ?>
				<ol class="fvisit__list">
					<?php foreach ( $oria_g['steps'] as $oria_step ) : ?>
						<li><?php echo esc_html( $oria_step ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>

	</div>

	<?php if ( '' !== $oria_g['worry']['q'] && '' !== $oria_g['worry']['a'] ) : ?>
		<div class="fvisit__worry">
			<h3 class="fvisit__worry-q"><?php echo esc_html( $oria_g['worry']['q'] ); ?></h3>
			<p class="fvisit__worry-a"><?php echo esc_html( $oria_g['worry']['a'] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="fvisit__whose">
		<?php esc_html_e( 'This describes what these sessions are generally like, not any one place on this page. Anything that matters to you — parking, access, how firm, how hot, what happens if you cancel — is worth a quick call to the practice before you book.', 'oria' ); ?>
	</p>
</section>
