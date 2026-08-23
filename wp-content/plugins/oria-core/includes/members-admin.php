<?php
/**
 * Members admin screen.
 *
 * Members never appear usefully on the Users screen — they hold one role,
 * no capabilities and none of the columns WordPress shows there mean
 * anything for them. This is the list that does: who joined, how they
 * verified, how much they have written, and what standing they are in.
 *
 * Deliberately plain. Moderating a member is rare and consequential; the
 * three actions are spelled out rather than hidden behind bulk selects.
 */

declare(strict_types=1);

namespace Oria\Core\MembersAdmin;

use Oria\Core\Db;
use Oria\Core\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SLUG     = 'oria-members';
const PER_PAGE = 50;

function bootstrap(): void {
	add_action( 'admin_menu', __NAMESPACE__ . '\menu' );
	add_action( 'admin_post_oria_member_action', __NAMESPACE__ . '\handle_action' );
}

function menu(): void {
	add_submenu_page(
		'users.php',
		__( 'Members', 'oria' ),
		__( 'Members', 'oria' ),
		'list_users',
		SLUG,
		__NAMESPACE__ . '\screen'
	);
}

/**
 * @return array{rows: array<int, array<string,mixed>>, total: int}
 */
function query( string $search, string $status, int $page ): array {
	global $wpdb;

	$table  = Db\members();
	$where  = array( '1=1' );
	$params = array();

	if ( '' !== $status ) {
		$where[]  = 'status = %s';
		$params[] = $status;
	}

	if ( '' !== $search ) {
		$where[]  = '(display_name LIKE %s OR handle LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$params[] = $like;
		$params[] = $like;
	}

	$clause = implode( ' AND ', $where );
	$offset = max( 0, ( $page - 1 ) * PER_PAGE );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";
	$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) );

	$rows_sql = "SELECT * FROM {$table} WHERE {$clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$rows     = (array) $wpdb->get_results( $wpdb->prepare( $rows_sql, ...array_merge( $params, array( PER_PAGE, $offset ) ) ), ARRAY_A );
	// phpcs:enable

	return array( 'rows' => $rows, 'total' => $total );
}

function screen(): void {
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to view members.', 'oria' ) );
	}

	if ( ! Db\installed() ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Members', 'oria' ) . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html__( 'The members table has not been created. Deactivate and reactivate Oria Core to install it.', 'oria' ) . '</p></div></div>';
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';
	$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	// phpcs:enable

	$statuses = array( Members\STATUS_PENDING, Members\STATUS_ACTIVE, Members\STATUS_MUTED, Members\STATUS_BANNED );
	if ( ! in_array( $status, $statuses, true ) ) {
		$status = '';
	}

	$result = query( $search, $status, $page );
	$pages  = (int) ceil( $result['total'] / PER_PAGE );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Members', 'oria' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'People who review listings. Practices and staff are a separate population and can never post reviews.', 'oria' ); ?>
		</p>

		<ul class="subsubsub">
			<?php
			$views = array( '' => __( 'All', 'oria' ) ) + array_combine( $statuses, array_map( 'ucfirst', $statuses ) );
			$last  = array_key_last( $views );
			foreach ( $views as $key => $label ) :
				$url = add_query_arg(
					array_filter( array( 'page' => SLUG, 'status' => $key, 's' => $search ) ),
					admin_url( 'users.php' )
				);
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>"<?php echo $status === $key ? ' class="current"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php echo $key === $last ? '' : ' |'; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<form method="get" style="margin:1rem 0">
			<input type="hidden" name="page" value="<?php echo esc_attr( SLUG ); ?>">
			<?php if ( '' !== $status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php endif; ?>
			<p class="search-box">
				<label class="screen-reader-text" for="oria-member-search"><?php esc_html_e( 'Search members', 'oria' ); ?></label>
				<input type="search" id="oria-member-search" name="s" value="<?php echo esc_attr( $search ); ?>">
				<?php submit_button( __( 'Search members', 'oria' ), '', '', false ); ?>
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Member', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Email', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Verified', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Reviews', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Joined', 'oria' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'oria' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['rows'] ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No members yet.', 'oria' ); ?></td></tr>
				<?php endif; ?>

				<?php
				foreach ( $result['rows'] as $row ) :
					$user = get_userdata( (int) $row['user_id'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( (string) $row['display_name'] ); ?></strong><br>
							<span class="description">@<?php echo esc_html( (string) $row['handle'] ); ?></span>
						</td>
						<td><?php echo esc_html( $user instanceof \WP_User ? $user->user_email : '—' ); ?></td>
						<td><?php echo esc_html( (string) $row['verified_via'] ); ?></td>
						<td><?php echo esc_html( (string) (int) $row['reviews_count'] ); ?></td>
						<td><?php echo esc_html( ucfirst( (string) $row['status'] ) ); ?></td>
						<td><?php echo esc_html( mysql2date( 'j M Y', (string) $row['created_at'] ) ); ?></td>
						<td>
							<?php
							$actions = array();
							if ( Members\STATUS_ACTIVE !== $row['status'] ) {
								$actions['activate'] = __( 'Activate', 'oria' );
							}
							if ( ! in_array( $row['status'], array( Members\STATUS_MUTED, Members\STATUS_BANNED ), true ) ) {
								$actions['mute'] = __( 'Mute', 'oria' );
							}
							if ( Members\STATUS_BANNED !== $row['status'] ) {
								$actions['ban'] = __( 'Ban', 'oria' );
							}
							$links = array();
							foreach ( $actions as $action => $label ) {
								$url     = wp_nonce_url(
									add_query_arg(
										array(
											'action'    => 'oria_member_action',
											'member'    => (int) $row['member_id'],
											'do'        => $action,
											'_wp_http_referer' => rawurlencode( (string) wp_get_referer() ),
										),
										admin_url( 'admin-post.php' )
									),
									'oria_member_' . (int) $row['member_id']
								);
								$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
							}
							echo wp_kses_post( implode( ' | ', $links ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	</div>
	<?php
}

function handle_action(): void {
	$member_id = isset( $_GET['member'] ) ? (int) $_GET['member'] : 0;
	$do        = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( (string) $_GET['do'] ) ) : '';

	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'oria' ) );
	}

	check_admin_referer( 'oria_member_' . $member_id );

	$map = array(
		'activate' => Members\STATUS_ACTIVE,
		'mute'     => Members\STATUS_MUTED,
		'ban'      => Members\STATUS_BANNED,
	);

	if ( $member_id > 0 && isset( $map[ $do ] ) ) {
		if ( 'activate' === $do ) {
			Members\activate( $member_id );
		} else {
			Members\update( $member_id, array( 'status' => $map[ $do ] ) );
		}
	}

	wp_safe_redirect( wp_get_referer() ?: admin_url( 'users.php?page=' . SLUG ) );
	exit;
}
