<?php
/**
 * Entries: every submission saved as a private post so nothing lives only
 * in email. Read-only in the admin — entries are records, not documents.
 */

declare(strict_types=1);

namespace Oria\Forms\Entries;

use Oria\Forms\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CPT = 'oria_form_entry';

function bootstrap(): void {
	add_action( 'init', __NAMESPACE__ . '\register' );
	add_filter( 'manage_' . CPT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\column_content', 10, 2 );
	add_action( 'add_meta_boxes_' . CPT, __NAMESPACE__ . '\metabox' );
}

function register(): void {
	register_post_type(
		CPT,
		array(
			'labels'       => array(
				'name'          => __( 'Form entries', 'oria' ),
				'singular_name' => __( 'Entry', 'oria' ),
				'menu_name'     => __( 'Form entries', 'oria' ),
				'all_items'     => __( 'Form entries', 'oria' ),
				'edit_item'     => __( 'Entry', 'oria' ),
				'search_items'  => __( 'Search entries', 'oria' ),
				'not_found'     => __( 'No entries yet', 'oria' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-feedback',
			'supports'     => array( 'title' ),
			'capabilities' => array(
				'create_posts' => 'do_not_allow', // Entries arrive; they are not written.
			),
			'map_meta_cap' => true,
		)
	);
}

/** @param array<string, string> $values */
function save( string $form_id, array $form, array $values ): int {
	$who   = $values['name'] ?? ( $values['email'] ?? __( 'Anonymous', 'oria' ) );
	$title = sprintf( '%s — %s', $who, (string) $form['label'] );

	$id = (int) wp_insert_post(
		array(
			'post_type'   => CPT,
			'post_status' => 'private',
			'post_title'  => $title,
		)
	);
	update_post_meta( $id, '_oform_id', $form_id );
	update_post_meta( $id, '_oform_values', $values );
	return $id;
}

/** @param array<string, string> $cols @return array<string, string> */
function columns( array $cols ): array {
	return array(
		'cb'         => $cols['cb'] ?? '',
		'title'      => __( 'Entry', 'oria' ),
		'oria_form'  => __( 'Form', 'oria' ),
		'oria_email' => __( 'Email', 'oria' ),
		'date'       => __( 'Received', 'oria' ),
	);
}

function column_content( string $col, int $post_id ): void {
	$values = (array) get_post_meta( $post_id, '_oform_values', true );
	if ( 'oria_form' === $col ) {
		$form = Registry\form( (string) get_post_meta( $post_id, '_oform_id', true ) );
		echo esc_html( $form ? (string) $form['label'] : '—' );
	}
	if ( 'oria_email' === $col ) {
		$email = (string) ( $values['email'] ?? '' );
		echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

function metabox(): void {
	add_meta_box(
		'oria-entry',
		__( 'Submission', 'oria' ),
		__NAMESPACE__ . '\render_metabox',
		CPT,
		'normal',
		'high'
	);
}

function render_metabox( \WP_Post $post ): void {
	$form   = Registry\form( (string) get_post_meta( $post->ID, '_oform_id', true ) );
	$values = (array) get_post_meta( $post->ID, '_oform_values', true );

	echo '<table class="widefat striped" style="border:none">';
	foreach ( $values as $name => $value ) {
		$label = $form['fields'][ $name ]['label'] ?? $name;
		echo '<tr><td style="width:220px;font-weight:600">' . esc_html( (string) $label ) . '</td><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</table>';
}
