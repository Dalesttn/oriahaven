<?php
/**
 * The page behind a 410 — an aggregated event that has been and gone.
 *
 * The status code is set before this template loads (see Oria\Ingest\Gone),
 * so a crawler already has its answer and this is purely for the person who
 * followed an old link. They wanted a specific evening, and it has passed;
 * the useful reply is what's on now, not an apology.
 */

declare(strict_types=1);

get_header();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>"><?php esc_html_e( 'Workshops/Events', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Finished', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:44rem">
		<span class="micro"><?php esc_html_e( 'This event has finished', 'oria' ); ?></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'That one has been and gone.', 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php esc_html_e( 'The event that lived at this address has already run, so we have taken the page down rather than leave a date that has passed sitting there looking current. Plenty else is on.', 'oria' ); ?>
		</p>
		<div class="row" style="gap:.75rem;margin-top:1.5rem;flex-wrap:wrap">
			<a class="btn btn--dark" href="<?php echo esc_url( home_url( '/this-weekend/' ) ); ?>">
				<?php esc_html_e( 'What’s on this weekend', 'oria' ); ?>
			</a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/whats-on-perth/' ) ); ?>">
				<?php esc_html_e( 'Every upcoming event', 'oria' ); ?>
			</a>
		</div>
	</div>
</section>

<?php
get_footer();
