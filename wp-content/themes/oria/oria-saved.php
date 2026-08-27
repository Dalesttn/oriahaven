<?php
/**
 * /saved/ — an empty shell, filled by app.js from what the device remembers.
 *
 * Nothing about a visitor's saves reaches this server, so there is nothing
 * here to render. The list, the count and the empty state are all painted by
 * initSavedPage() against window.ORIA_DATA, the payload every page already
 * carries.
 */

declare(strict_types=1);

get_header();
?>

<section class="wrap pagehead">
	<nav class="crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'oria' ); ?></a>
		<span aria-hidden="true">/</span><span><?php esc_html_e( 'Saved', 'oria' ); ?></span>
	</nav>
	<div style="margin-top:1rem;max-width:44rem">
		<span class="micro" data-saved-count></span>
		<h1 class="h1 pagehead__title"><?php esc_html_e( 'Your saved practices', 'oria' ); ?></h1>
		<p class="lede pagehead__lede">
			<?php esc_html_e( 'Kept on this device only — no account, and nothing sent to us. Clearing your browser will clear this list.', 'oria' ); ?>
		</p>
	</div>
</section>

<section class="wrap section section--top-flush">
	<?php
	/*
	 * Shown until the script says otherwise. Rendering the empty state first
	 * and hiding it is the right way round: a visitor with nothing saved sees
	 * the explanation immediately rather than a blank area that fills in.
	 */
	?>
	<div class="dir__empty saved__empty" data-saved-empty>
		<h2 class="h3"><?php esc_html_e( 'Nothing saved yet', 'oria' ); ?></h2>
		<p class="muted" style="margin-top:.5rem">
			<?php esc_html_e( 'Press Save on any practice and it will appear here, so you can come back to a shortlist rather than starting again.', 'oria' ); ?>
		</p>
		<p style="margin-top:var(--s-4)">
			<a class="btn btn--dark" href="<?php echo esc_url( get_post_type_archive_link( 'listing' ) ?: home_url( '/directory/' ) ); ?>">
				<?php esc_html_e( 'Browse the directory', 'oria' ); ?>
				<span class="btn__dot"><svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7h10M8 3l4 4-4 4"/></svg></span>
			</a>
		</p>
	</div>

	<div class="saved__grid" data-saved-list></div>
</section>

<?php
get_footer();
