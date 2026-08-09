<?php
/**
 * Back-of-house branding: the login screen and the listing edit screen
 * carry the same deep-petrol / sage identity as the site itself, so the
 * first thing a paying practitioner sees is not stock WordPress.
 *
 * Colours are mirrored from the theme's tokens.css — the admin has no
 * access to the front-end custom properties, so the handful used here are
 * repeated as literals.
 */

declare(strict_types=1);

namespace Oria\Core\AdminUI;

use Oria\Core\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\login_styles' );
	add_filter( 'login_headerurl', __NAMESPACE__ . '\login_url' );
	add_filter( 'login_headertext', __NAMESPACE__ . '\login_text' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\listing_screen_styles' );

	// Listings and events use the classic editor: with a screenful of ACF
	// detail fields, Gutenberg buries the description canvas behind the
	// meta-box splitter — practitioners couldn't find where to write.
	// Classic puts the description box on top and every field below it.
	add_filter( 'use_block_editor_for_post_type', __NAMESPACE__ . '\classic_for_directory_types', 10, 2 );

	// Yoast's metabox is operator tooling; practitioners never need it.
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\hide_seo_box_for_practitioners', 99 );
}

/** @param bool $use_block_editor */
function classic_for_directory_types( $use_block_editor, string $post_type ): bool {
	if ( in_array( $post_type, array( PostTypes\LISTING, PostTypes\EVENT ), true ) ) {
		return false;
	}
	return (bool) $use_block_editor;
}

function hide_seo_box_for_practitioners(): void {
	if ( \Oria\Core\Ownership\is_practitioner() ) {
		remove_meta_box( 'wpseo_meta', null, 'normal' );
	}
}

function login_url(): string {
	return home_url( '/' );
}

function login_text(): string {
	return get_bloginfo( 'name' );
}

/* ---------------------------------------------------------------- login */

function login_styles(): void {
	wp_enqueue_style( 'oria-login', ORIA_CORE_URL . 'assets/login.css', array(), '1.0' );

	// The ensō mark lives in the theme and paints with currentColor, so it
	// is applied as a CSS mask and coloured from the stylesheet.
	$logo = get_theme_file_uri( 'assets/img/logo-mark.svg' );
	wp_add_inline_style( 'oria-login', ':root{--oria-logo:url("' . esc_url( $logo ) . '")}' );
}

/* -------------------------------------------------- listing edit screen */

function listing_screen_styles( string $hook ): void {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || PostTypes\LISTING !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'oria-admin-listing', ORIA_CORE_URL . 'assets/admin.css', array(), '1.1' );
}
