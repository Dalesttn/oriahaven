<?php
/**
 * Small security tightenings for a public directory.
 *
 * The REST user collection is the one WordPress surface that hands an
 * anonymous visitor the login names behind the site. Nothing public
 * reads it — listings are posts, reviews render server-side, journal
 * bylines are printed into the page — so it is auth-only here. A
 * password brute-force needs a username list first; this keeps the
 * list private without touching what logged-in editors can do.
 */

declare(strict_types=1);

namespace Oria\Core\Hardening;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bootstrap(): void {
	add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\users_need_auth', 10, 3 );
}

/**
 * @param mixed            $result  Response to replace the requested version with.
 * @param \WP_REST_Server  $server  Server instance.
 * @param \WP_REST_Request $request Request used to generate the response.
 * @return mixed
 */
function users_need_auth( $result, $server, $request ) {
	if ( null !== $result ) {
		return $result;
	}
	if ( 0 !== strpos( $request->get_route(), '/wp/v2/users' ) ) {
		return $result;
	}
	if ( is_user_logged_in() ) {
		return $result;
	}
	return new \WP_Error(
		'rest_cannot_access',
		__( 'Sorry, you are not allowed to list users.', 'oria' ),
		array( 'status' => 401 )
	);
}
