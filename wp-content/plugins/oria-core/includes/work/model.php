<?php
/**
 * Work in Wellness: the data model.
 *
 * Three post types -- a job, a shift, a work profile -- and three shared
 * vocabularies. The vocabularies are shared on purpose: a Pilates job, a
 * Pilates shift and a Pilates instructor's profile are matched against one
 * another, and three separate "profession" lists would drift apart the first
 * time somebody added a term to one of them.
 *
 * Location is not a new taxonomy. Everything here files under the existing
 * `area` tree (city > region > suburb), so a suburb means the same thing on a
 * job as on a listing, and a second city needs no code.
 *
 * NAMING. The site already has a role called 'practitioner' -- it means the
 * owner of a business listing. The person looking for work is a "work
 * profile" in code (post type oria_practitioner, the key the brief asked
 * for) and a "practitioner" only in public copy.
 *
 * Fields are plain post meta read through meta() / set(), not ACF: ACF
 * resolves fields by name only on an admin screen, and most of what happens
 * here happens on the front end.
 *
 * @package Oria\Core
 */

declare(strict_types=1);

namespace Oria\Core\Work;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JOB   = 'oria_job';
const SHIFT = 'oria_shift';
const PRO   = 'oria_practitioner';

const PROFESSION = 'work_profession';
const EMPLOYMENT = 'work_employment';
const SKILL      = 'work_skill';

/** Seed version: bump when the vocabularies below change. */
const SEED_V = '2';

/** Phase 3 private types: a curated course, and a business's talent enquiry. */
const COURSE  = 'oria_course';
const REQUEST = 'oria_talent_req';

/** Public URL bases. Kept here so routes, rewrites and links agree. */
const BASE_JOBS   = 'jobs';
const BASE_SHIFTS = 'shifts';
const BASE_PROS   = 'practitioners';

/**
 * Slugs a job, shift or profile may never take, because a route owns them.
 * City slugs are added at runtime.
 */
const RESERVED = array( 'category', 'go', 'post', 'careers', 'salary-guide', 'training', 'employers', 'alerts', 'available-practitioners', 'feed', 'page' );

/* ------------------------------------------------------------ vocabularies */

/** Profession groups and their professions (brief section 5). */
function professions(): array {
	return array(
		'Movement & Fitness'            => array( 'Yoga Teacher', 'Pilates Instructor', 'Reformer Pilates Instructor', 'Personal Trainer', 'Group Fitness Instructor', 'Strength & Conditioning Coach', 'Dance Instructor', 'Mobility Coach' ),
		'Bodywork & Recovery'           => array( 'Massage Therapist', 'Remedial Massage Therapist', 'Myotherapist', 'Physiotherapist', 'Osteopath', 'Chiropractor', 'Recovery Coach', 'Sauna / Recovery Attendant' ),
		'Mental & Emotional Wellbeing'  => array( 'Counsellor', 'Psychologist', 'Mental Health Practitioner', 'Meditation Teacher', 'Breathwork Facilitator', 'Mindfulness Coach', 'Life Coach' ),
		'Natural & Holistic Wellness'   => array( 'Naturopath', 'Nutritionist', 'Dietitian', 'Acupuncturist', 'Chinese Medicine Practitioner', 'Reiki Practitioner', 'Sound Healing Practitioner', 'Holistic Therapist' ),
		'Spa & Beauty Wellness'         => array( 'Spa Therapist', 'Beauty Therapist', 'Skin Therapist', 'Facial Therapist', 'Wellness Therapist' ),
		'Operations & Management'       => array( 'Studio Manager', 'Practice Manager', 'Receptionist', 'Customer Service', 'Administration', 'Membership Consultant', 'Sales', 'Marketing', 'Social Media', 'Operations', 'Bookkeeper' ),
		'Retreat & Events'              => array( 'Retreat Facilitator', 'Retreat Host', 'Event Coordinator', 'Workshop Facilitator', 'Casual Retreat Staff', 'Retreat Cook', 'Retreat Manager', 'Photographer', 'Wellness Speaker', 'Corporate Wellness Facilitator' ),
	);
}

/** Employment types (brief section 6). slug => label. */
function employment_types(): array {
	return array(
		'full-time'  => 'Full-time',
		'part-time'  => 'Part-time',
		'casual'     => 'Casual',
		'contract'   => 'Contract',
		'freelance'  => 'Freelance',
		'fixed-term' => 'Fixed-term',
		'internship' => 'Internship',
		'volunteer'  => 'Volunteer',
		'locum'      => 'Locum',
		'temporary'  => 'Temporary',
		'seasonal'   => 'Seasonal',
	);
}

/** Skill groups and skills (brief section 12). */
function skills(): array {
	return array(
		'Yoga'    => array( 'Hatha', 'Vinyasa', 'Yin', 'Restorative', 'Hot Yoga', 'Pregnancy Yoga', 'Seniors Yoga', 'Kids Yoga' ),
		'Pilates' => array( 'Mat Pilates', 'Reformer', 'Clinical Pilates' ),
		'Massage' => array( 'Remedial', 'Swedish', 'Sports Massage', 'Deep Tissue', 'Pregnancy Massage' ),
		'Other'   => array( 'Meditation', 'Breathwork', 'Sound Healing', 'Strength Training', 'Nutrition', 'Counselling', 'Retreat Facilitation' ),
	);
}

/** Work arrangement (brief section 6). */
const ARRANGEMENTS = array(
	'onsite' => 'On-site',
	'hybrid' => 'Hybrid',
	'remote' => 'Remote',
	'mobile' => 'Mobile / travel required',
);

/** How pay is quoted. The label reads after an amount: "$65 per class". */
const PAY_UNITS = array(
	'hour'       => 'per hour',
	'class'      => 'per class',
	'shift'      => 'per shift',
	'client'     => 'per client',
	'year'       => 'per year',
	'commission' => 'commission',
	'negotiable' => 'negotiable',
);

const EXPERIENCE = array(
	'any'    => 'No experience needed',
	'junior' => '1+ years',
	'mid'    => '3+ years',
	'senior' => '5+ years',
);

/** Shift urgency (brief section 9). */
const URGENCY = array(
	'normal' => 'Normal',
	'week'   => 'Needed within 7 days',
	'48h'    => 'Needed within 48 hours',
	'today'  => 'Urgent — today',
);

/** What a practitioner is available for (brief section 11). */
const AVAILABLE_FOR = array(
	'permanent' => 'Permanent roles',
	'casual'    => 'Casual work',
	'cover'     => 'Shift cover',
	'retreats'  => 'Retreats',
	'events'    => 'Events',
	'private'   => 'Private clients',
	'corporate' => 'Corporate wellness',
);

/** When (brief section 11). 'now' and 'not' are statuses, the rest are times. */
const AVAILABILITY = array(
	'now'      => 'Available now',
	'open'     => 'Open to opportunities',
	'weekends' => 'Weekends',
	'evenings' => 'Evenings',
	'mornings' => 'Mornings',
	'not'      => 'Not currently available',
);

/** Admin-set verification badges (brief section 13). Documents are never shown. */
const VERIFICATIONS = array(
	'identity'      => 'Identity verified',
	'qualification' => 'Qualification verified',
	'registration'  => 'Registration verified',
	'insurance'     => 'Insurance verified',
	'first_aid'     => 'First Aid verified',
	'wwcc'          => 'WWCC verified',
);

/** Employer benefits (brief section 31). */
const BENEFITS = array(
	'free-classes'  => 'Free classes',
	'discounts'     => 'Staff discounts',
	'flexible'      => 'Flexible roster',
	'development'   => 'Professional development',
	'mentoring'     => 'Mentoring',
	'paid-training' => 'Paid training',
	'parking'       => 'Free parking',
	'commission'    => 'Commission',
	'bonuses'       => 'Bonuses',
	'hybrid'        => 'Hybrid work',
	'allowance'     => 'Wellness allowance',
);

/** Report reasons (brief section 57). */
const REPORT_REASONS = array(
	'scam'          => 'Scam',
	'misleading'    => 'Misleading',
	'inappropriate' => 'Inappropriate',
	'filled'        => 'Filled or expired',
	'category'      => 'Wrong category',
	'duplicate'     => 'Duplicate',
);

/** Application statuses (brief section 18), plus the shift offer/accept pair. */
const STATUSES = array(
	'new'         => 'New',
	'viewed'      => 'Viewed',
	'shortlisted' => 'Shortlisted',
	'interview'   => 'Interview',
	'offered'     => 'Offered',
	'hired'       => 'Hired',
	'confirmed'   => 'Confirmed',
	'rejected'    => 'Not progressing',
	'withdrawn'   => 'Withdrawn',
	'cancelled'   => 'Cancelled',
);

/** Days a job stays open unless the employer says otherwise (brief section 58). */
const JOB_DAYS = 30;

/**
 * Launch prices (brief section 37). Nothing is charged at launch: these are
 * shown on the recruitment page and read by the admin, so the day a checkout
 * exists it reads the same numbers.
 */
function prices(): array {
	return (array) apply_filters(
		'oria_work_prices',
		array(
			'job_basic'      => 0,
			'job_featured'   => 49,
			'job_premium'    => 99,
			'job_urgent'     => 29,
			'shift_basic'    => 0,
			'shift_urgent'   => 19,
			'pro_basic'      => 0,
			'recruit_month'  => 49,
			'cover_month'    => 29,
		)
	);
}

/* --------------------------------------------------------------- register */

function register(): void {
	$area = defined( '\Oria\Core\Taxonomies\AREA' ) ? \Oria\Core\Taxonomies\AREA : 'area';

	register_taxonomy(
		PROFESSION,
		array( JOB, SHIFT, PRO ),
		array(
			'labels'            => array(
				'name'          => __( 'Professions', 'oria' ),
				'singular_name' => __( 'Profession', 'oria' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'rewrite'           => false,
		)
	);
	register_taxonomy(
		EMPLOYMENT,
		array( JOB ),
		array(
			'labels'            => array(
				'name'          => __( 'Employment types', 'oria' ),
				'singular_name' => __( 'Employment type', 'oria' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		)
	);
	register_taxonomy(
		SKILL,
		array( JOB, SHIFT, PRO ),
		array(
			'labels'       => array(
				'name'          => __( 'Work skills', 'oria' ),
				'singular_name' => __( 'Work skill', 'oria' ),
			),
			'hierarchical' => true,
			'public'       => false,
			'show_ui'      => true,
			'rewrite'      => false,
		)
	);

	$common = array(
		'public'          => true,
		'show_ui'         => true,
		'show_in_menu'    => 'oria-work',
		'has_archive'     => false,
		'show_in_rest'    => false,
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'taxonomies'      => array( PROFESSION, SKILL, $area ),
	);

	register_post_type(
		JOB,
		$common + array(
			'labels'   => array(
				'name'          => __( 'Jobs', 'oria' ),
				'singular_name' => __( 'Job', 'oria' ),
				'add_new_item'  => __( 'Add job', 'oria' ),
				'edit_item'     => __( 'Edit job', 'oria' ),
				'all_items'     => __( 'Jobs', 'oria' ),
			),
			'supports' => array( 'title', 'editor', 'excerpt', 'author', 'revisions' ),
			'rewrite'  => array( 'slug' => BASE_JOBS, 'with_front' => false ),
		)
	);
	register_post_type(
		SHIFT,
		$common + array(
			'labels'   => array(
				'name'          => __( 'Shifts', 'oria' ),
				'singular_name' => __( 'Shift', 'oria' ),
				'add_new_item'  => __( 'Add shift', 'oria' ),
				'edit_item'     => __( 'Edit shift', 'oria' ),
				'all_items'     => __( 'Shifts', 'oria' ),
			),
			'supports' => array( 'title', 'editor', 'author' ),
			'rewrite'  => array( 'slug' => BASE_SHIFTS, 'with_front' => false ),
		)
	);
	register_post_type(
		PRO,
		$common + array(
			'labels'   => array(
				'name'          => __( 'Work profiles', 'oria' ),
				'singular_name' => __( 'Work profile', 'oria' ),
				'edit_item'     => __( 'Edit work profile', 'oria' ),
				'all_items'     => __( 'Work profiles', 'oria' ),
			),
			'supports' => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail' ),
			'rewrite'  => array( 'slug' => BASE_PROS, 'with_front' => false ),
		)
	);

	/*
	 * Phase 3. A course is curated by Oria staff (no public submission, so
	 * nothing listed is invented or unchecked); a talent request is a
	 * business's enquiry for corporate/retreat/event staff -- private, no URL.
	 */
	register_post_type(
		COURSE,
		array(
			'labels'          => array( 'name' => __( 'Courses', 'oria' ), 'singular_name' => __( 'Course', 'oria' ), 'add_new_item' => __( 'Add course', 'oria' ), 'all_items' => __( 'Training & courses', 'oria' ) ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'oria-work',
			'supports'        => array( 'title', 'editor', 'thumbnail' ),
			'taxonomies'      => array( PROFESSION ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => false,
		)
	);
	register_taxonomy_for_object_type( PROFESSION, COURSE );
	register_post_type(
		REQUEST,
		array(
			'labels'          => array( 'name' => __( 'Talent requests', 'oria' ), 'singular_name' => __( 'Talent request', 'oria' ), 'all_items' => __( 'Talent requests', 'oria' ) ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'oria-work',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'rewrite'         => false,
		)
	);

	register_taxonomy_for_object_type( $area, JOB );
	register_taxonomy_for_object_type( $area, SHIFT );
	register_taxonomy_for_object_type( $area, PRO );
}

/** Seed the vocabularies once per SEED_V. Adds, never renames or deletes. */
function seed(): void {
	if ( get_option( 'oria_work_seed_v' ) === SEED_V ) {
		return;
	}
	$tree = static function ( string $tax, array $groups ): void {
		foreach ( $groups as $group => $children ) {
			$parent = term_exists( $group, $tax );
			if ( ! $parent ) {
				$parent = wp_insert_term( $group, $tax );
			}
			if ( is_wp_error( $parent ) ) {
				continue;
			}
			$pid = (int) ( is_array( $parent ) ? $parent['term_id'] : $parent );
			foreach ( $children as $child ) {
				if ( ! term_exists( $child, $tax, $pid ) ) {
					wp_insert_term( $child, $tax, array( 'parent' => $pid ) );
				}
			}
		}
	};
	$tree( PROFESSION, professions() );
	$tree( SKILL, skills() );
	foreach ( employment_types() as $slug => $label ) {
		if ( ! term_exists( $slug, EMPLOYMENT ) ) {
			wp_insert_term( $label, EMPLOYMENT, array( 'slug' => $slug ) );
		}
	}
	update_option( 'oria_work_seed_v', SEED_V, false );
}

/**
 * Keep route words and city slugs out of post slugs, so /jobs/perth/ is
 * always the Perth jobs page and never a job someone titled "Perth".
 */
function reserve_slugs( string $slug, int $post_id, string $status, string $type ): string {
	if ( ! in_array( $type, array( JOB, SHIFT, PRO ), true ) ) {
		return $slug;
	}
	$taken = RESERVED;
	if ( function_exists( '\Oria\Core\Cities\slugs' ) ) {
		$taken = array_merge( $taken, \Oria\Core\Cities\slugs() );
	}
	return in_array( $slug, $taken, true ) ? $slug . '-' . ( JOB === $type ? 'role' : ( SHIFT === $type ? 'shift' : 'profile' ) ) : $slug;
}

/* ---------------------------------------------------------- field access */

/** Post meta key for a field name. One prefix, so nothing collides. */
function key( string $name ): string {
	return '_wk_' . $name;
}

/** @return mixed */
function meta( int $post_id, string $name, $default = '' ) {
	$v = get_post_meta( $post_id, key( $name ), true );
	return ( '' === $v || null === $v ) ? $default : $v;
}

/** @param mixed $value */
function set( int $post_id, string $name, $value ): void {
	if ( '' === $value || null === $value || array() === $value ) {
		delete_post_meta( $post_id, key( $name ) );
		return;
	}
	update_post_meta( $post_id, key( $name ), $value );
}

/** A post's first term in a taxonomy, or null. */
function term( int $post_id, string $tax ): ?\WP_Term {
	$t = get_the_terms( $post_id, $tax );
	return is_array( $t ) && $t ? $t[0] : null;
}

/** Leaf (non-group) profession terms, grouped: group name => [term...]. */
function profession_tree(): array {
	$out    = array();
	$groups = get_terms( array( 'taxonomy' => PROFESSION, 'parent' => 0, 'hide_empty' => false, 'orderby' => 'term_id' ) );
	if ( is_wp_error( $groups ) ) {
		return $out;
	}
	foreach ( $groups as $g ) {
		$kids = get_terms( array( 'taxonomy' => PROFESSION, 'parent' => $g->term_id, 'hide_empty' => false, 'orderby' => 'term_id' ) );
		$out[ $g->name ] = is_wp_error( $kids ) ? array() : $kids;
	}
	return $out;
}

/** Skill tree in the same shape. */
function skill_tree(): array {
	$out    = array();
	$groups = get_terms( array( 'taxonomy' => SKILL, 'parent' => 0, 'hide_empty' => false, 'orderby' => 'term_id' ) );
	if ( is_wp_error( $groups ) ) {
		return $out;
	}
	foreach ( $groups as $g ) {
		$kids = get_terms( array( 'taxonomy' => SKILL, 'parent' => $g->term_id, 'hide_empty' => false, 'orderby' => 'term_id' ) );
		$out[ $g->name ] = is_wp_error( $kids ) ? array() : $kids;
	}
	return $out;
}

/** Suburbs of public cities as slug => "Suburb" for a select, grouped by city. */
function suburb_choices(): array {
	$out   = array();
	$terms = get_terms( array( 'taxonomy' => 'area', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $t ) {
		if ( ! function_exists( '\Oria\Core\Taxonomies\is_suburb' ) || ! \Oria\Core\Taxonomies\is_suburb( $t ) ) {
			continue;
		}
		$city = function_exists( '\Oria\Core\Cities\for_area' ) ? \Oria\Core\Cities\for_area( $t ) : null;
		if ( $city && function_exists( '\Oria\Core\Cities\is_public' ) && ! \Oria\Core\Cities\is_public( $city ) ) {
			continue;
		}
		$out[ (string) ( $city['name'] ?? '' ) ][ $t->slug ] = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
	}
	return $out;
}

/** Set a post's suburb, carrying its region and city so area pages count it. */
function set_suburb( int $post_id, string $slug ): void {
	$term = '' === $slug ? null : get_term_by( 'slug', $slug, 'area' );
	if ( ! $term instanceof \WP_Term ) {
		return;
	}
	$ids = array( (int) $term->term_id );
	foreach ( get_ancestors( (int) $term->term_id, 'area', 'taxonomy' ) as $a ) {
		$ids[] = (int) $a;
	}
	wp_set_object_terms( $post_id, $ids, 'area' );
}

/** The deepest area term on a post (its suburb), or null. */
function suburb( int $post_id ): ?\WP_Term {
	$terms = get_the_terms( $post_id, 'area' );
	if ( ! is_array( $terms ) || ! $terms ) {
		return null;
	}
	usort( $terms, static fn( $a, $b ) => count( get_ancestors( $b->term_id, 'area', 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, 'area', 'taxonomy' ) ) );
	return $terms[0];
}

/** The city array a post belongs to, via its area terms. */
function city_of( int $post_id ): ?array {
	$s = suburb( $post_id );
	if ( ! $s || ! function_exists( '\Oria\Core\Cities\for_area' ) ) {
		return null;
	}
	return \Oria\Core\Cities\for_area( $s );
}
