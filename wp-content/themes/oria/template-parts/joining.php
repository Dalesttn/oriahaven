<?php
/**
 * At a glance: an activity listing's joining details in one panel.
 *
 * Replaced seven equal-height fact tiles, which gave a two-word format the
 * same box as a four-line timetable. Now: a sage header for the short facts,
 * a timetable beside what to prepare, and -- only when the organiser has
 * said there is none -- a supervision notice that is never folded away.
 *
 * Everything shown comes from Oria\Core\Glance\model(), which decides what
 * the data can honestly support; this file only draws it. The other season
 * sits in a native <details>, so it opens by mouse, touch and keyboard with
 * no script.
 *
 * Args: id (int), sec (int, section number), slug (string, for UTM content).
 *
 * @package Oria
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$oria_gl_id = (int) ( $args['id'] ?? 0 );
$oria_gl    = ( $oria_gl_id && function_exists( '\Oria\Core\Glance\model' ) ) ? \Oria\Core\Glance\model( $oria_gl_id ) : null;
if ( ! $oria_gl ) {
	return;
}
$oria_gl_sec = (int) ( $args['sec'] ?? 0 );

// Outline icons in the site's own style (24 grid, 1.8 stroke, round caps).
$oria_gl_ico = static function ( string $name ): string {
	$paths = array(
		'pin'      => '<path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
		'route'    => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h7.5a3.5 3.5 0 0 0 0-7h-7a3.5 3.5 0 0 1 0-7H16"/>',
		'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'dot'      => '<circle cx="12" cy="12" r="3.2"/>',
		'tag'      => '<path d="M3.5 12.5V4.5h8l9 9-8 8Z"/><circle cx="8" cy="9" r="1.4"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'pack'     => '<path d="M7 8V6a5 5 0 0 1 10 0v2"/><rect x="4.5" y="8" width="15" height="12.5" rx="3"/><path d="M9 13h6"/>',
		'alert'    => '<path d="M12 4 2.8 19.5h18.4Z"/><path d="M12 10v4.5M12 17.3v.2"/>',
	);
	return '<svg class="oh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? $paths['dot'] ) . '</svg>';
};

$oria_gl_rows = static function ( array $rows ): void {
	foreach ( $rows as $r ) {
		printf(
			'<div class="oh-line"><span>%s</span><span class="oh-time">%s</span></div>',
			esc_html( (string) $r['label'] ),
			esc_html( (string) $r['time'] )
		);
	}
};

$oria_gl_sched = $oria_gl['schedule'];
$oria_gl_has_when = $oria_gl_sched['seasons'] || $oria_gl_sched['lines'] || '' !== $oria_gl['join']['url'];
$oria_gl_has_prep = (bool) $oria_gl['prep'];

$oria_gl_url   = '' !== $oria_gl['join']['url'] && function_exists( '\Oria\Theme\outbound' ) ? \Oria\Theme\outbound( $oria_gl['join']['url'], (string) ( $args['slug'] ?? '' ) ) : $oria_gl['join']['url'];
$oria_gl_track = in_array( $oria_gl['join']['method'], array( 'booking', 'register' ), true ) ? 'book' : 'web';
$oria_gl_host  = '' !== $oria_gl['source_url'] && function_exists( '\Oria\Theme\link_label' ) ? \Oria\Theme\link_label( $oria_gl['source_url'] ) : '';
$oria_gl_when  = '' !== $oria_gl['checked'] ? strtotime( $oria_gl['checked'] ) : false;
?>
<section class="xp-sec oh-glance" id="how-to-join" aria-labelledby="xp-s<?php echo (int) $oria_gl_sec; ?>">
	<div class="oh-panel">
		<header class="oh-top">
			<p class="oh-eyebrow"><?php echo esc_html( $oria_gl['eyebrow'] ); ?></p>
			<h2 class="oh-title" id="xp-s<?php echo (int) $oria_gl_sec; ?>"><?php echo esc_html( $oria_gl['heading'] ); ?></h2>
			<?php if ( '' !== $oria_gl['location'] ) : ?>
				<p class="oh-location"><?php echo $oria_gl_ico( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><span><?php echo esc_html( $oria_gl['location'] ); ?></span></p>
			<?php endif; ?>
			<?php if ( $oria_gl['facts'] ) : ?>
				<ul class="oh-metrics">
					<?php foreach ( $oria_gl['facts'] as $oria_gl_f ) : ?>
						<li class="oh-metric">
							<?php echo $oria_gl_ico( $oria_gl_f['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>
							<span>
								<?php if ( '' !== $oria_gl_f['label'] && 'distance' !== strtolower( $oria_gl_f['label'] ) ) : ?>
									<span class="sr-only"><?php echo esc_html( $oria_gl_f['label'] ); ?>: </span>
								<?php endif; ?>
								<?php if ( '' !== $oria_gl_f['strong'] ) : ?>
									<strong><?php echo esc_html( $oria_gl_f['strong'] ); ?></strong><?php echo esc_html( $oria_gl_f['rest'] ); ?>
								<?php else : ?>
									<?php echo esc_html( $oria_gl_f['text'] ); ?>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</header>

		<?php if ( $oria_gl_has_when || $oria_gl_has_prep ) : ?>
			<div class="oh-content<?php echo ( $oria_gl_has_when && $oria_gl_has_prep ) ? '' : ' oh-content--one'; ?>">
				<?php if ( $oria_gl_has_when ) : ?>
					<section class="oh-when" aria-labelledby="oh-when-<?php echo (int) $oria_gl_id; ?>">
						<h3 class="oh-h" id="oh-when-<?php echo (int) $oria_gl_id; ?>"><?php echo $oria_gl_ico( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php echo esc_html( $oria_gl['when_label'] ); ?></h3>

						<?php if ( $oria_gl_sched['seasons'] ) : ?>
							<?php foreach ( $oria_gl_sched['seasons'] as $oria_gl_i => $oria_gl_s ) : ?>
								<?php
								/* translators: 1: season, 2: month range */
								$oria_gl_label = sprintf( __( '%1$s · %2$s', 'oria' ), $oria_gl_s['name'], $oria_gl_s['months'] );
								?>
								<?php if ( 0 === $oria_gl_i || $oria_gl_sched['open_all'] ) : ?>
									<p class="oh-kicker"><?php echo esc_html( $oria_gl_label ); ?></p>
									<div class="oh-rows"><?php $oria_gl_rows( $oria_gl_s['rows'] ); ?></div>
								<?php else : ?>
									<details class="oh-more">
										<?php /* translators: %s: season and months, e.g. Winter · May–August */ ?>
										<summary><?php printf( esc_html__( '%s times', 'oria' ), esc_html( $oria_gl_label ) ); ?></summary>
										<div class="oh-rows"><?php $oria_gl_rows( $oria_gl_s['rows'] ); ?></div>
									</details>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php else : ?>
							<?php foreach ( $oria_gl_sched['lines'] as $oria_gl_l ) : ?>
								<p class="oh-text"><?php echo esc_html( $oria_gl_l ); ?></p>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php foreach ( $oria_gl_sched['notes'] as $oria_gl_n ) : ?>
							<p class="oh-small"><?php echo esc_html( $oria_gl_n ); ?></p>
						<?php endforeach; ?>

						<?php if ( '' !== $oria_gl_url ) : ?>
							<p class="oh-act">
								<a class="oh-link" href="<?php echo esc_url( $oria_gl_url ); ?>" rel="nofollow noopener" target="_blank"
									data-oria-track="<?php echo esc_attr( $oria_gl_track ); ?>" data-oria-id="<?php echo (int) $oria_gl_id; ?>">
									<?php echo esc_html( \Oria\Core\Sources\join_label( $oria_gl['join']['method'], $oria_gl['join']['url'] ) ); ?>
									<span class="sr-only"><?php esc_html_e( '(opens the organiser’s site in a new tab)', 'oria' ); ?></span>
									<span aria-hidden="true">&rarr;</span>
								</a>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php if ( $oria_gl_has_prep ) : ?>
					<section class="oh-prep" aria-labelledby="oh-prep-<?php echo (int) $oria_gl_id; ?>">
						<h3 class="oh-h" id="oh-prep-<?php echo (int) $oria_gl_id; ?>"><?php echo $oria_gl_ico( 'pack' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?><?php esc_html_e( 'Before you join', 'oria' ); ?></h3>
						<dl class="oh-items">
							<?php foreach ( $oria_gl['prep'] as $oria_gl_p ) : ?>
								<div class="oh-item">
									<dt><?php echo esc_html( $oria_gl_p['label'] ); ?></dt>
									<dd><?php echo esc_html( $oria_gl_p['text'] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $oria_gl['notice'] ) : ?>
			<aside class="oh-safety" aria-label="<?php esc_attr_e( 'Supervision', 'oria' ); ?>">
				<?php echo $oria_gl_ico( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?>
				<div>
					<strong><?php echo esc_html( $oria_gl['notice']['heading'] ); ?></strong>
					<?php echo esc_html( $oria_gl['notice']['body'] ); ?>
				</div>
			</aside>
		<?php endif; ?>

		<?php if ( $oria_gl_host && $oria_gl_when ) : ?>
			<p class="oh-src">
				<?php
				printf(
					/* translators: 1: website, 2: date */
					esc_html__( 'Details checked against %1$s on %2$s. Organisers change things — confirm with them before you go.', 'oria' ),
					esc_html( $oria_gl_host ),
					esc_html( wp_date( 'j F Y', (int) $oria_gl_when ) )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</section>
