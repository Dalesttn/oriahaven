<?php
/**
 * The search/filter form for a list (brief section 32). A plain GET form:
 * it works without JavaScript, every field has a visible label, and the
 * results are filtered on the server.
 *
 * $args: view (jobs|shifts|pros|available), f (current filters), action (form URL)
 */

declare(strict_types=1);

use Oria\Core\Work;

$oria_view = (string) ( $args['view'] ?? 'jobs' );
$oria_f    = (array) ( $args['f'] ?? array() );
$oria_act  = (string) ( $args['action'] ?? '' );
$oria_prof = (string) ( $oria_f['profession'] ?? '' );
$oria_people = in_array( $oria_view, array( 'pros', 'available' ), true );
// For people the place is a point to measure from ("near Subiaco, within 10 km"), not a filing.
$oria_loc_name = $oria_people ? 'near' : 'area';
$oria_area     = (string) ( $oria_f[ $oria_loc_name ] ?? '' );
$oria_uid      = 'wkf-' . $oria_view;
?>
<form class="wkfilters" method="get" action="<?php echo esc_url( $oria_act ); ?>" role="search" data-wk-search="<?php echo esc_attr( $oria_view ); ?>">
	<div class="wkfilters__main">
		<div class="wkfield wkfield--grow">
			<label for="<?php echo esc_attr( $oria_uid ); ?>-q"><?php echo esc_html( 'pros' === $oria_view || 'available' === $oria_view ? __( 'Name or keyword', 'oria' ) : __( 'Job title or keyword', 'oria' ) ); ?></label>
			<input class="input" type="search" id="<?php echo esc_attr( $oria_uid ); ?>-q" name="q" value="<?php echo esc_attr( (string) ( $oria_f['q'] ?? '' ) ); ?>" autocomplete="off">
		</div>
		<div class="wkfield">
			<label for="<?php echo esc_attr( $oria_uid ); ?>-p"><?php esc_html_e( 'Profession', 'oria' ); ?></label>
			<select class="input" id="<?php echo esc_attr( $oria_uid ); ?>-p" name="profession">
				<option value=""><?php esc_html_e( 'All professions', 'oria' ); ?></option>
				<?php foreach ( Work\profession_tree() as $oria_group => $oria_terms ) : ?>
					<optgroup label="<?php echo esc_attr( $oria_group ); ?>">
						<?php foreach ( $oria_terms as $oria_t ) : ?>
							<option value="<?php echo esc_attr( $oria_t->slug ); ?>" <?php selected( $oria_prof, $oria_t->slug ); ?>><?php echo esc_html( $oria_t->name ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="wkfield">
			<label for="<?php echo esc_attr( $oria_uid ); ?>-a"><?php echo esc_html( $oria_people ? __( 'Near', 'oria' ) : __( 'Location', 'oria' ) ); ?></label>
			<select class="input" id="<?php echo esc_attr( $oria_uid ); ?>-a" name="<?php echo esc_attr( $oria_loc_name ); ?>">
				<option value=""><?php esc_html_e( 'Anywhere', 'oria' ); ?></option>
				<?php foreach ( Work\suburb_choices() as $oria_city => $oria_subs ) : ?>
					<optgroup label="<?php echo esc_attr( $oria_city ); ?>">
						<?php foreach ( $oria_subs as $oria_slug => $oria_name ) : ?>
							<option value="<?php echo esc_attr( $oria_slug ); ?>" <?php selected( $oria_area, $oria_slug ); ?>><?php echo esc_html( $oria_name ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( 'jobs' === $oria_view ) : ?>
			<div class="wkfield">
				<label for="<?php echo esc_attr( $oria_uid ); ?>-t"><?php esc_html_e( 'Employment type', 'oria' ); ?></label>
				<select class="input" id="<?php echo esc_attr( $oria_uid ); ?>-t" name="type">
					<option value=""><?php esc_html_e( 'Any type', 'oria' ); ?></option>
					<?php foreach ( Work\employment_types() as $oria_k => $oria_v ) : ?>
						<option value="<?php echo esc_attr( $oria_k ); ?>" <?php selected( (string) ( $oria_f['type'] ?? '' ), $oria_k ); ?>><?php echo esc_html( $oria_v ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php elseif ( 'shifts' === $oria_view ) : ?>
			<div class="wkfield">
				<label for="<?php echo esc_attr( $oria_uid ); ?>-w"><?php esc_html_e( 'When', 'oria' ); ?></label>
				<select class="input" id="<?php echo esc_attr( $oria_uid ); ?>-w" name="when">
					<option value=""><?php esc_html_e( 'Any time', 'oria' ); ?></option>
					<option value="today" <?php selected( (string) ( $oria_f['when'] ?? '' ), 'today' ); ?>><?php esc_html_e( 'Available today', 'oria' ); ?></option>
					<option value="weekend" <?php selected( (string) ( $oria_f['when'] ?? '' ), 'weekend' ); ?>><?php esc_html_e( 'This weekend', 'oria' ); ?></option>
					<option value="week" <?php selected( (string) ( $oria_f['when'] ?? '' ), 'week' ); ?>><?php esc_html_e( 'Next 7 days', 'oria' ); ?></option>
				</select>
			</div>
		<?php endif; ?>
		<button class="btn btn--dark wkfilters__go" type="submit"><?php echo esc_html( 'jobs' === $oria_view ? __( 'Search wellness jobs', 'oria' ) : ( 'shifts' === $oria_view ? __( 'Find shifts', 'oria' ) : __( 'Search', 'oria' ) ) ); ?></button>
	</div>

	<?php if ( 'jobs' === $oria_view ) : ?>
		<fieldset class="wkfilters__more">
			<legend class="wk-vh"><?php esc_html_e( 'More filters', 'oria' ); ?></legend>
			<label class="wktoggle"><input type="checkbox" name="paid" value="1" <?php checked( ! empty( $oria_f['paid'] ) ); ?>> <?php esc_html_e( 'Pay shown', 'oria' ); ?></label>
			<label class="wktoggle"><input type="checkbox" name="remote" value="1" <?php checked( ! empty( $oria_f['remote'] ) ); ?>> <?php esc_html_e( 'Remote or hybrid', 'oria' ); ?></label>
			<label class="wktoggle"><input type="checkbox" name="weekend" value="1" <?php checked( ! empty( $oria_f['weekend'] ) ); ?>> <?php esc_html_e( 'Weekend work', 'oria' ); ?></label>
			<label class="wktoggle"><input type="checkbox" name="evening" value="1" <?php checked( ! empty( $oria_f['evening'] ) ); ?>> <?php esc_html_e( 'Evening work', 'oria' ); ?></label>
			<label class="wksort"><span><?php esc_html_e( 'Sort', 'oria' ); ?></span>
				<select name="sort">
					<option value=""><?php esc_html_e( 'Most relevant', 'oria' ); ?></option>
					<option value="new" <?php selected( (string) ( $oria_f['sort'] ?? '' ), 'new' ); ?>><?php esc_html_e( 'Newest', 'oria' ); ?></option>
					<option value="pay" <?php selected( (string) ( $oria_f['sort'] ?? '' ), 'pay' ); ?>><?php esc_html_e( 'Highest paying', 'oria' ); ?></option>
				</select>
			</label>
		</fieldset>
	<?php elseif ( $oria_people ) : ?>
		<?php
		$oria_skill_on = (array) ( $oria_f['skills'] ?? array() );
		$oria_times_on = (array) ( $oria_f['times'] ?? array() );
		?>
		<fieldset class="wkfilters__more">
			<legend class="wk-vh"><?php esc_html_e( 'More filters', 'oria' ); ?></legend>
			<label class="wksort"><span><?php esc_html_e( 'Within', 'oria' ); ?></span>
				<select name="radius">
					<option value=""><?php esc_html_e( 'Any distance', 'oria' ); ?></option>
					<?php foreach ( array( 5, 10, 20, 50 ) as $oria_km ) : ?>
						<option value="<?php echo (int) $oria_km; ?>" <?php selected( (int) ( $oria_f['radius'] ?? 0 ), $oria_km ); ?>><?php echo esc_html( sprintf( __( '%d km', 'oria' ), $oria_km ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="wksort"><span><?php esc_html_e( 'Experience', 'oria' ); ?></span>
				<select name="years">
					<option value=""><?php esc_html_e( 'Any', 'oria' ); ?></option>
					<?php foreach ( array( 1, 3, 5, 10 ) as $oria_y ) : ?>
						<option value="<?php echo (int) $oria_y; ?>" <?php selected( (int) ( $oria_f['years'] ?? 0 ), $oria_y ); ?>><?php echo esc_html( sprintf( __( '%d+ years', 'oria' ), $oria_y ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( 'available' === $oria_view ) : ?>
				<label class="wksort"><span><?php esc_html_e( 'Free on', 'oria' ); ?></span>
					<input type="date" name="date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( (string) ( $oria_f['date'] ?? '' ) ); ?>">
				</label>
			<?php else : ?>
				<label class="wktoggle"><input type="checkbox" name="cover" value="1" <?php checked( ! empty( $oria_f['cover'] ) ); ?>> <?php esc_html_e( 'Open to casual cover', 'oria' ); ?></label>
			<?php endif; ?>
			<?php foreach ( array( 'mornings' => __( 'Mornings', 'oria' ), 'evenings' => __( 'Evenings', 'oria' ), 'weekends' => __( 'Weekends', 'oria' ) ) as $oria_k => $oria_v ) : ?>
				<label class="wktoggle"><input type="checkbox" name="times[]" value="<?php echo esc_attr( $oria_k ); ?>" <?php checked( in_array( $oria_k, $oria_times_on, true ) ); ?>> <?php echo esc_html( $oria_v ); ?></label>
			<?php endforeach; ?>
			<label class="wktoggle"><input type="checkbox" name="verified" value="1" <?php checked( ! empty( $oria_f['verified'] ) ); ?>> <?php esc_html_e( 'Verified only', 'oria' ); ?></label>
			<label class="wksort"><span><?php esc_html_e( 'Sort', 'oria' ); ?></span>
				<select name="sort">
					<option value=""><?php esc_html_e( 'Best match', 'oria' ); ?></option>
					<option value="near" <?php selected( (string) ( $oria_f['sort'] ?? '' ), 'near' ); ?>><?php esc_html_e( 'Nearest', 'oria' ); ?></option>
					<option value="years" <?php selected( (string) ( $oria_f['sort'] ?? '' ), 'years' ); ?>><?php esc_html_e( 'Most experienced', 'oria' ); ?></option>
				</select>
			</label>
			<details class="wkfilters__skills"<?php echo $oria_skill_on ? ' open' : ''; ?>>
				<summary><?php echo esc_html( $oria_skill_on ? sprintf( _n( 'Skills (%d)', 'Skills (%d)', count( $oria_skill_on ), 'oria' ), count( $oria_skill_on ) ) : __( 'Skills', 'oria' ) ); ?></summary>
				<?php foreach ( Work\skill_tree() as $oria_g => $oria_ts ) : ?>
					<p class="wkgroup"><?php echo esc_html( $oria_g ); ?></p>
					<div class="wkchecks">
						<?php foreach ( $oria_ts as $oria_t ) : ?>
							<label class="wkcheck"><input type="checkbox" name="skills[]" value="<?php echo esc_attr( $oria_t->slug ); ?>" <?php checked( in_array( $oria_t->slug, $oria_skill_on, true ) ); ?>> <?php echo esc_html( $oria_t->name ); ?></label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</details>
		</fieldset>
	<?php endif; ?>
</form>
