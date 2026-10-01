<?php
/**
 * One practitioner's work profile.
 *
 * Public by the practitioner's choice (routes/guard_singles() has already
 * turned hidden or employers-only profiles into a 404 for anyone they are
 * not for). Nothing private is printed: no email, phone, ABN, certificate
 * or registration numbers, no documents -- only the badges an admin ticked
 * after seeing the evidence (brief sections 10, 13, 90).
 */

declare(strict_types=1);

use Oria\Core\Work;

get_header();

$oria_id     = (int) get_queried_object_id();
$oria_uid    = get_current_user_id();
$oria_own    = $oria_uid && (int) get_post_field( 'post_author', $oria_id ) === $oria_uid;
$oria_title  = (string) Work\meta( $oria_id, 'title' ) ?: Work\profession_name( $oria_id );
$oria_years  = (int) Work\meta( $oria_id, 'years', 0 );
$oria_profs  = wp_get_post_terms( $oria_id, Work\PROFESSION, array( 'fields' => 'names' ) );
$oria_skills = wp_get_post_terms( $oria_id, Work\SKILL, array( 'fields' => 'names' ) );
$oria_for    = array_intersect_key( Work\AVAILABLE_FOR, array_flip( (array) Work\meta( $oria_id, 'available_for', array() ) ) );
$oria_when   = array_intersect_key( Work\AVAILABILITY, array_flip( (array) Work\meta( $oria_id, 'availability', array() ) ) );
$oria_badges = Work\badges( $oria_id );
$oria_works  = (int) Work\meta( $oria_id, 'works_at', 0 );
$oria_links  = array_filter(
	array(
		__( 'Website', 'oria' )   => (string) Work\meta( $oria_id, 'website' ),
		__( 'Instagram', 'oria' ) => (string) Work\meta( $oria_id, 'instagram' ),
		__( 'LinkedIn', 'oria' )  => (string) Work\meta( $oria_id, 'linkedin' ),
	)
);
$oria_contact = (string) Work\meta( $oria_id, 'contact_pref', 'allow' );
$oria_comp    = $oria_own ? Work\completeness( $oria_id ) : null;
?>
<main id="main" class="wk wk--single wk--pro" data-wk-view="practitioner_view" data-wk-id="<?php echo (int) $oria_id; ?>">
	<div class="wrap wrap--narrow">
		<nav class="wkcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'oria' ); ?>">
			<a href="<?php echo esc_url( Work\list_url( 'pros' ) ); ?>"><?php esc_html_e( 'Practitioners', 'oria' ); ?></a>
		</nav>

		<?php if ( $oria_own ) : ?>
			<div class="wkown">
				<p><?php echo esc_html( sprintf( __( 'This is your profile — %d%% complete.', 'oria' ), $oria_comp['score'] ) ); ?>
					<?php if ( $oria_comp['missing'] ) : ?><?php echo esc_html( sprintf( __( 'Add %s to reach more businesses.', 'oria' ), implode( ', ', array_slice( $oria_comp['missing'], 0, 3 ) ) ) ); ?><?php endif; ?>
					<?php if ( 'public' !== Work\visibility( $oria_id ) ) : ?><strong><?php echo esc_html( 'hidden' === Work\visibility( $oria_id ) ? __( 'Hidden — only you can see it.', 'oria' ) : __( 'Visible to Oria employers only.', 'oria' ) ); ?></strong><?php endif; ?>
				</p>
				<a class="btn btn--dark btn--sm" href="<?php echo esc_url( \Oria\Core\MyOria\url( 'work-edit' ) ); ?>"><?php esc_html_e( 'Edit profile', 'oria' ); ?></a>
			</div>
		<?php endif; ?>

		<header class="wkprohead">
			<div class="wkprohead__photo">
				<?php if ( has_post_thumbnail( $oria_id ) ) : ?>
					<?php echo get_the_post_thumbnail( $oria_id, 'medium', array( 'alt' => esc_attr( get_the_title( $oria_id ) ) ) ); ?>
				<?php else : ?>
					<span aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( get_the_title( $oria_id ), 0, 1 ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<div>
				<h1 class="wkjobhead__title"><?php the_title(); ?></h1>
				<p class="wkjobhead__org"><?php echo esc_html( implode( ' · ', array_filter( array( $oria_title, Work\place_label( $oria_id ), $oria_years ? sprintf( _n( '%d year experience', '%d years experience', $oria_years, 'oria' ), $oria_years ) : '' ) ) ) ); ?></p>
				<?php if ( $oria_badges ) : ?>
					<p class="wkpro__badges">
						<?php foreach ( $oria_badges as $oria_b ) : ?>
							<span class="wkverified"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.4 6.6 11.3 12.5 4.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><?php echo esc_html( $oria_b ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( $oria_ex = trim( (string) get_post_field( 'post_excerpt', $oria_id ) ) ) : ?>
			<p class="lede"><?php echo esc_html( $oria_ex ); ?></p>
		<?php endif; ?>

		<?php if ( $oria_for || $oria_when || Work\avail_label( $oria_id ) ) : ?>
			<section class="wkavailbox" aria-labelledby="wk-avail">
				<h2 class="h3" id="wk-avail"><?php esc_html_e( 'Available for', 'oria' ); ?></h2>
				<?php if ( '' !== ( $oria_ap = Work\avail_label( $oria_id ) ) ) : ?>
					<p class="wkavail wkavail--post"><span class="wkavail__dot" aria-hidden="true"></span><?php echo esc_html( sprintf( __( 'Free for cover: %s', 'oria' ), $oria_ap ) ); ?><?php echo '' !== ( $oria_an = (string) Work\meta( $oria_id, 'avail_note' ) ) ? ' — ' . esc_html( $oria_an ) : ''; ?></p>
				<?php endif; ?>
				<p class="wkchips">
					<?php foreach ( $oria_for as $oria_v ) : ?><span class="wkchip wkchip--on"><?php echo esc_html( $oria_v ); ?></span><?php endforeach; ?>
					<?php foreach ( $oria_when as $oria_v ) : ?><span class="wkchip"><?php echo esc_html( $oria_v ); ?></span><?php endforeach; ?>
				</p>
				<?php if ( $oria_r = (string) Work\meta( $oria_id, 'rate' ) ) : ?>
					<p class="hint"><?php echo esc_html( sprintf( __( 'Rate: %s', 'oria' ), $oria_r ) ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<div class="wkprose"><?php echo wp_kses_post( (string) get_post_field( 'post_content', $oria_id ) ); ?></div>

		<dl class="wkfacts">
			<?php if ( count( $oria_profs ) > 1 ) : ?><div><dt><?php esc_html_e( 'Professions', 'oria' ); ?></dt><dd><?php echo esc_html( implode( ', ', $oria_profs ) ); ?></dd></div><?php endif; ?>
			<?php if ( $oria_skills ) : ?><div><dt><?php esc_html_e( 'Skills', 'oria' ); ?></dt><dd><?php echo esc_html( implode( ' • ', $oria_skills ) ); ?></dd></div><?php endif; ?>
			<?php if ( $oria_q = trim( (string) Work\meta( $oria_id, 'quals' ) ) ) : ?><div><dt><?php esc_html_e( 'Qualifications', 'oria' ); ?></dt><dd><?php echo nl2br( esc_html( $oria_q ) ); ?></dd></div><?php endif; ?>
			<?php if ( $oria_s = trim( (string) Work\meta( $oria_id, 'services' ) ) ) : ?><div><dt><?php esc_html_e( 'Services', 'oria' ); ?></dt><dd><?php echo nl2br( esc_html( $oria_s ) ); ?></dd></div><?php endif; ?>
			<?php if ( $oria_works && 'publish' === get_post_status( $oria_works ) ) : ?><div><dt><?php esc_html_e( 'Works at', 'oria' ); ?></dt><dd><a href="<?php echo esc_url( get_permalink( $oria_works ) ); ?>"><?php echo esc_html( html_entity_decode( get_the_title( $oria_works ), ENT_QUOTES, 'UTF-8' ) ); ?></a></dd></div><?php endif; ?>
			<?php if ( $oria_rad = (int) Work\meta( $oria_id, 'radius', 0 ) ) : ?><div><dt><?php esc_html_e( 'Travels', 'oria' ); ?></dt><dd><?php echo esc_html( sprintf( __( 'Up to %d km', 'oria' ), $oria_rad ) ); ?></dd></div><?php endif; ?>
		</dl>

		<?php if ( $oria_links ) : ?>
			<p class="wklinks">
				<?php foreach ( $oria_links as $oria_k => $oria_u ) : ?>
					<a href="<?php echo esc_url( $oria_u ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $oria_k ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php
		/*
		 * Employer tools (brief sections 27, 67-68): save to a talent list
		 * with a private note, and invite to one of their open listings.
		 * Shown only to signed-in employers; the invite only when the
		 * practitioner allows contact.
		 */
		$oria_emp_ok = ! $oria_own && $oria_uid && Work\is_employer( $oria_uid );
		if ( $oria_emp_ok ) :
			$oria_lists   = Work\Store\list_names( $oria_uid );
			$oria_on      = Work\Store\lists_with( $oria_uid, $oria_id );
			$oria_mine    = array_values( array_filter( get_posts( array( 'post_type' => array( Work\JOB, Work\SHIFT ), 'author' => $oria_uid, 'post_status' => 'publish', 'numberposts' => 30, 'fields' => 'ids' ) ), static fn( $p ) => Work\is_open( (int) $p ) ) );
			$oria_invited = Work\Store\invited_to( $oria_uid, $oria_id );
			$oria_note    = Work\notice();
			?>
			<section class="wkemptools" aria-labelledby="wk-emptools">
				<h2 class="h3" id="wk-emptools"><?php esc_html_e( 'For employers', 'oria' ); ?></h2>
				<?php if ( $oria_note ) : ?>
					<div class="notice notice--<?php echo 'ok' === $oria_note['type'] ? 'success' : 'error'; ?> wknotice" role="status"><?php echo esc_html( $oria_note['text'] ); ?></div>
				<?php endif; ?>
				<div class="wkemptools__grid">
					<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php echo Work\form_fields( 'oria_work_talent' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<input type="hidden" name="profile" value="<?php echo (int) $oria_id; ?>">
						<p class="wklabel"><?php esc_html_e( 'Save to a list', 'oria' ); ?></p>
						<?php if ( $oria_on ) : ?><p class="hint"><?php echo esc_html( sprintf( __( 'Already on: %s', 'oria' ), implode( ', ', $oria_on ) ) ); ?></p><?php endif; ?>
						<?php if ( $oria_lists ) : ?>
							<label for="wk-list"><?php esc_html_e( 'List', 'oria' ); ?></label>
							<select class="input" id="wk-list" name="list"><?php foreach ( $oria_lists as $oria_l ) : ?><option><?php echo esc_html( $oria_l ); ?></option><?php endforeach; ?></select>
						<?php endif; ?>
						<label for="wk-newlist"><?php echo esc_html( $oria_lists ? __( 'or a new list', 'oria' ) : __( 'List name', 'oria' ) ); ?></label>
						<input class="input" id="wk-newlist" name="new_list" maxlength="60" <?php echo $oria_lists ? '' : 'value="' . esc_attr__( 'Weekend cover', 'oria' ) . '"'; ?>>
						<label for="wk-note"><?php esc_html_e( 'Private note (only you see it)', 'oria' ); ?></label>
						<textarea class="input" id="wk-note" name="note" rows="2" maxlength="1000"></textarea>
						<button class="btn btn--ghost btn--sm" type="submit"><?php esc_html_e( 'Save practitioner', 'oria' ); ?></button>
					</form>

					<?php if ( 'allow' === $oria_contact ) : ?>
						<form class="wkform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php echo Work\form_fields( 'oria_work_invite' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<input type="hidden" name="profile" value="<?php echo (int) $oria_id; ?>">
							<p class="wklabel"><?php esc_html_e( 'Invite to one of your listings', 'oria' ); ?></p>
							<?php if ( $oria_mine ) : ?>
								<label for="wk-inv-post"><?php esc_html_e( 'Job or shift', 'oria' ); ?></label>
								<select class="input" id="wk-inv-post" name="post" required>
									<?php foreach ( $oria_mine as $oria_p ) : ?>
										<option value="<?php echo (int) $oria_p; ?>" <?php disabled( in_array( (int) $oria_p, $oria_invited, true ) ); ?>><?php echo esc_html( get_the_title( $oria_p ) . ( in_array( (int) $oria_p, $oria_invited, true ) ? ' — ' . __( 'invited', 'oria' ) : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<label for="wk-inv-msg"><?php esc_html_e( 'A line from you (optional)', 'oria' ); ?></label>
								<textarea class="input" id="wk-inv-msg" name="message" rows="2" maxlength="500"></textarea>
								<button class="btn btn--dark btn--sm" type="submit" data-wk-event="employer_contact"><?php esc_html_e( 'Send invitation', 'oria' ); ?></button>
								<p class="hint"><?php esc_html_e( 'We email them; your address and theirs stay private until they respond.', 'oria' ); ?></p>
							<?php else : ?>
								<p class="hint"><?php esc_html_e( 'Post a job or shift first, then invite them to it.', 'oria' ); ?></p>
								<a class="btn btn--dark btn--sm" href="<?php echo esc_url( add_query_arg( 'type', 'shift', \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>"><?php esc_html_e( 'Post a shift', 'oria' ); ?></a>
							<?php endif; ?>
						</form>
					<?php else : ?>
						<p class="hint"><?php esc_html_e( 'This practitioner takes contact through applications only.', 'oria' ); ?></p>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! $oria_own && ! $oria_emp_ok ) : ?>
			<div class="wkcontact">
				<?php if ( 'allow' === $oria_contact ) : ?>
					<p><?php esc_html_e( 'Hiring? Post your job or shift on Oria and invite them to it — they will be told, and their contact details are shared once they respond.', 'oria' ); ?></p>
					<a class="btn btn--dark btn--sm" href="<?php echo esc_url( add_query_arg( 'type', 'shift', \Oria\Core\MyOria\url( 'recruit-post' ) ) ); ?>" data-wk-event="employer_contact"><?php esc_html_e( 'Post a shift', 'oria' ); ?></a>
				<?php else : ?>
					<p class="hint"><?php esc_html_e( 'This practitioner takes contact through applications only.', 'oria' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( ! $oria_own ) : ?>
			<?php get_template_part( 'template-parts/work/report', null, array( 'id' => $oria_id, 'label' => __( 'Report this profile', 'oria' ) ) ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
