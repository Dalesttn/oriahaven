<?php
/**
 * Apply wording changes to pages built from ACF "sections".
 *
 * The claim and About pages are flexible-content sections stored in the
 * database, seeded once from Import::claim_sections() / about_sections(). A
 * wording fix in import.php alone changes nothing live, so this writes the
 * same text into the existing sections from data/page-edits.json.
 *
 * It edits one sub-field at a time with update_sub_field(), never by writing
 * a whole "sections" array back. Two of those sections hold their form in a
 * WYSIWYG field; a read-then-write of the full array would store the rendered
 * form HTML in place of its shortcode and break both forms.
 *
 * Each edit names a layout and the heading it expects to find, and matches
 * either the old heading or the new one, so a second run changes nothing.
 * Rows added to a repeater are skipped if one with the same first value is
 * already there.
 *
 * SAFETY
 *   Dry run is the default and writes nothing. --apply writes.
 *
 * USAGE (from the WordPress root, on the server)
 *     php wp-content/plugins/oria-core/tools/apply-page-edits.php
 *     php wp-content/plugins/oria-core/tools/apply-page-edits.php --apply
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "CLI only.\n" );
}

require dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! function_exists( 'update_sub_field' ) || ! function_exists( 'add_sub_row' ) ) {
	fwrite( STDERR, "ACF Pro sub-field functions are not available.\n" );
	exit( 1 );
}

$apply = in_array( '--apply', $argv ?? array(), true );
$file  = ORIA_CORE_DIR . 'data/page-edits.json';
$data  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

if ( ! is_array( $data['pages'] ?? null ) ) {
	fwrite( STDERR, "Cannot read {$file}\n" );
	exit( 1 );
}

$short = static function ( $v ): string {
	$v = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $v ) ) );
	// Cut by character, not byte, or a curly quote at the cut prints as "?".
	return '' === $v ? '(empty)' : ( mb_strlen( $v ) > 100 ? mb_substr( $v, 0, 100 ) . '…' : $v );
};
$same = static fn( $a, $b ): bool => trim( (string) $a ) === trim( (string) $b );

$changes = 0;

foreach ( $data['pages'] as $page ) {
	$slug = (string) ( $page['slug'] ?? '' );
	$post = get_page_by_path( $slug );
	if ( ! $post instanceof WP_Post ) {
		printf( "NOT FOUND  page /%s/\n\n", $slug );
		continue;
	}
	printf( "/%s/  (page %d)\n", $slug, $post->ID );

	$sections = get_field( 'sections', $post->ID );
	$sections = is_array( $sections ) ? array_values( $sections ) : array();
	$removals = array(); // row numbers, deleted after every other edit so indexes stay valid

	foreach ( (array) ( $page['sections'] ?? array() ) as $edit ) {
		$layout = (string) $edit['layout'];
		$want   = (string) ( $edit['match'] ?? '' );
		$new_h  = (string) ( $edit['set']['heading'] ?? '' );

		$i = null;
		foreach ( $sections as $k => $row ) {
			if ( ( $row['acf_fc_layout'] ?? '' ) !== $layout ) {
				continue;
			}
			$h = (string) ( $row['heading'] ?? '' );
			if ( '' === $want || $same( $h, $want ) || ( '' !== $new_h && $same( $h, $new_h ) ) ) {
				$i = (int) $k;
				break;
			}
		}
		if ( null === $i ) {
			printf( "  SKIPPED  %s \"%s\" — not found on this page\n", $layout, $want );
			continue;
		}
		$n    = $i + 1;             // ACF selectors count rows from 1
		$meta = "sections_{$i}_";   // meta keys count from 0

		if ( ! empty( $edit['remove'] ) ) {
			$changes++;
			$removals[] = $n;
			printf( "  %-12s REMOVE section \"%s\"%s\n", $layout, $want, empty( $edit['reason'] ) ? '' : ' — ' . $edit['reason'] );
			continue;
		}

		// --- plain fields on the section ---------------------------------
		foreach ( (array) ( $edit['set'] ?? array() ) as $field => $value ) {
			$before = get_post_meta( $post->ID, $meta . $field, true );
			if ( $same( $before, $value ) ) {
				continue;
			}
			$changes++;
			printf( "  %-12s %s\n      before: %s\n      after:  %s\n", $layout, $field, $short( $before ), $short( $value ) );
			if ( $apply ) {
				update_sub_field( array( 'sections', $n, $field ), $value, $post->ID );
			}
		}

		// --- fields inside existing repeater rows ------------------------
		foreach ( (array) ( $edit['rows'] ?? array() ) as $rep => $rows ) {
			foreach ( (array) $rows as $row_no => $fields ) {
				$j = (int) $row_no - 1;
				foreach ( (array) $fields as $field => $value ) {
					$key    = "{$meta}{$rep}_{$j}_{$field}";
					$before = get_post_meta( $post->ID, $key, true );
					$value  = is_bool( $value ) ? (int) $value : $value;
					if ( $same( $before, $value ) ) {
						continue;
					}
					$changes++;
					printf( "  %-12s %s[%d].%s\n      before: %s\n      after:  %s\n", $layout, $rep, $row_no, $field, $short( $before ), $short( $value ) );
					if ( $apply ) {
						update_sub_field( array( 'sections', $n, $rep, (int) $row_no, $field ), $value, $post->ID );
					}
				}
			}
		}

		// --- new repeater rows -------------------------------------------
		foreach ( (array) ( $edit['add_rows'] ?? array() ) as $rep => $new_rows ) {
			$count = (int) get_post_meta( $post->ID, $meta . $rep, true );
			foreach ( (array) $new_rows as $new ) {
				$first_key = (string) array_key_first( $new );
				$exists    = false;
				for ( $j = 0; $j < $count; $j++ ) {
					if ( $same( get_post_meta( $post->ID, "{$meta}{$rep}_{$j}_{$first_key}", true ), $new[ $first_key ] ) ) {
						$exists = true;
						break;
					}
				}
				if ( $exists ) {
					continue;
				}
				$changes++;
				printf( "  %-12s add to %s: %s\n", $layout, $rep, $short( $new[ $first_key ] ) );
				if ( $apply ) {
					add_sub_row( array( 'sections', $n, $rep ), $new, $post->ID );
				}
			}
		}
	}

	if ( $apply ) {
		// Highest row first, so deleting one never renumbers another still queued.
		rsort( $removals );
		foreach ( $removals as $row_no ) {
			delete_row( 'sections', $row_no, $post->ID );
		}
		clean_post_cache( $post->ID );
	}
	echo "\n";
}

printf( "%d change(s) %s.\n", $changes, $apply ? 'written' : 'would be made' );
echo $apply
	? "Purge the page cache so visitors see them: wp litespeed-purge all\n"
	: "Dry run: nothing written. Re-run with --apply to write.\n";
