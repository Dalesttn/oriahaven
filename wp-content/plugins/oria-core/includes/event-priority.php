<?php
/**
 * Which events are pinned to the top of their category pages, at a glance.
 *
 * The switch itself is the category_priority field on the event (fields.php),
 * read by Theme\category_events(). This file only makes it visible in the
 * events list, so a pin left on from last month is noticed rather than
 * quietly outranking everything until the event ends.
 */

declare(strict_types=1);

namespace Oria\Core\EventPriority;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_filter( 'manage_' . PostTypes\EVENT . '_posts_columns', __NAMESPACE__ . '\columns' );
	add_action( 'manage_' . PostTypes\EVENT . '_posts_custom_column', __NAMESPACE__ . '\column', 10, 2 );
}

/** @param array<string, string> $cols */
function columns( array $cols ): array {
	if ( ! current_user_can( 'manage_options' ) ) {
		return $cols;
	}
	$cols['oria_priority'] = __( 'Category priority', 'oria' );
	return $cols;
}

function column( string $col, int $post_id ): void {
	if ( 'oria_priority' !== $col ) {
		return;
	}
	if ( '1' === (string) get_post_meta( $post_id, 'category_priority', true ) ) {
		echo '<span style="color:#8a5a12;font-weight:600">&#9733; ' . esc_html__( 'Shown first', 'oria' ) . '</span>';
	} else {
		echo '<span aria-hidden="true" style="color:#c3c4c7">&mdash;</span>';
	}
}
