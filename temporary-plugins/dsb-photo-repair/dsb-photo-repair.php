<?php
/**
 * Plugin Name: DSB Photo Repair
 * Description: Temporary admin-only dry-run and repair tool for reconnecting Dating Site Builder profile photos from existing Media Library attachments.
 * Version: 0.1.0
 * Author: Click eCommerce
 * Text Domain: dsb-photo-repair
 *
 * @package DSBPhotoRepair
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSB_Photo_Repair_Tool {

	const MENU_SLUG = 'dsb-photo-repair';
	const LOG_OPTION = 'dsb_photo_repair_log';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_tools_page' ) );
		add_action( 'admin_post_dsb_photo_repair_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	/**
	 * Add the temporary admin page.
	 */
	public static function add_tools_page() {
		add_management_page(
			__( 'DSB Photo Repair', 'dsb-photo-repair' ),
			__( 'DSB Photo Repair', 'dsb-photo-repair' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this repair tool.', 'dsb-photo-repair' ) );
		}

		$report = null;
		$repair_result = null;
		$user_id = isset( $_REQUEST['dsb_repair_user_id'] ) ? absint( $_REQUEST['dsb_repair_user_id'] ) : 42;
		$scope = isset( $_REQUEST['dsb_repair_scope'] ) && 'all' === sanitize_key( wp_unslash( $_REQUEST['dsb_repair_scope'] ) ) ? 'all' : 'single';

		if ( isset( $_POST['dsb_photo_repair_dry_run'] ) ) {
			check_admin_referer( 'dsb_photo_repair_dry_run', 'dsb_photo_repair_nonce' );
			$report = self::build_report( $scope, $user_id, false );
			$report['review_token'] = self::create_review_token( $report );
		}

		if ( isset( $_POST['dsb_photo_repair_run'] ) ) {
			check_admin_referer( 'dsb_photo_repair_run', 'dsb_photo_repair_nonce' );
			$review_token = isset( $_POST['dsb_review_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dsb_review_token'] ) ) : '';
			$confirmed = ! empty( $_POST['dsb_backup_confirmed'] );
			$review = self::decode_review_token( $review_token );

			if ( ! $confirmed ) {
				self::render_notice( __( 'Repair blocked: confirm that the live database and /wp-content/uploads/ have been backed up.', 'dsb-photo-repair' ), 'error' );
			} elseif ( empty( $review ) ) {
				self::render_notice( __( 'Repair blocked: run and review a dry run immediately before running the repair.', 'dsb-photo-repair' ), 'error' );
			} else {
				$repair_scope = isset( $review['scope'] ) ? $review['scope'] : 'single';
				$repair_user_id = isset( $review['user_id'] ) ? absint( $review['user_id'] ) : 0;
				$repair_result = self::build_report( $repair_scope, $repair_user_id, true );
				self::append_persistent_log( $repair_result );
				$report = $repair_result;
			}
		}

		$last_log = get_option( self::LOG_OPTION, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DSB Photo Repair', 'dsb-photo-repair' ); ?></h1>
			<p>
				<?php esc_html_e( 'Temporary admin-only tool to compare Dating Site Builder dsb_photos user meta against existing image attachments uploaded by each user.', 'dsb-photo-repair' ); ?>
			</p>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'Safety warning:', 'dsb-photo-repair' ); ?></strong>
					<?php esc_html_e( 'Run Dry Run first. Before using Run Repair, take a live database backup and a backup of /wp-content/uploads/. This tool never deletes photos, attachments, files, or existing dsb_photos entries.', 'dsb-photo-repair' ); ?>
				</p>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field( 'dsb_photo_repair_dry_run', 'dsb_photo_repair_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Scope', 'dsb-photo-repair' ); ?></th>
						<td>
							<label>
								<input type="radio" name="dsb_repair_scope" value="single" <?php checked( 'single', $scope ); ?>>
								<?php esc_html_e( 'Single user first', 'dsb-photo-repair' ); ?>
							</label>
							<br>
							<label>
								<input type="radio" name="dsb_repair_scope" value="all" <?php checked( 'all', $scope ); ?>>
								<?php esc_html_e( 'All users', 'dsb-photo-repair' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsb_repair_user_id"><?php esc_html_e( 'User ID', 'dsb-photo-repair' ); ?></label></th>
						<td>
							<input type="number" id="dsb_repair_user_id" name="dsb_repair_user_id" value="<?php echo esc_attr( $user_id ); ?>" min="1" class="small-text">
							<p class="description"><?php esc_html_e( 'Use user ID 42 for the first test, then switch to all users after reviewing the dry run.', 'dsb-photo-repair' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" name="dsb_photo_repair_dry_run" value="1" class="button button-primary">
						<?php esc_html_e( 'Run Dry Run', 'dsb-photo-repair' ); ?>
					</button>
				</p>
			</form>

			<?php
			if ( $repair_result ) {
				self::render_notice( __( 'Repair completed. Review the summary and log below.', 'dsb-photo-repair' ), 'success' );
			}
			if ( $report ) {
				self::render_report( $report );
			}
			if ( ! empty( $last_log ) ) {
				self::render_last_log( $last_log );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Build a dry-run or repair report.
	 *
	 * @param string $scope   single|all.
	 * @param int    $user_id User ID for single-user mode.
	 * @param bool   $repair  Whether to write missing photos.
	 * @return array<string,mixed>
	 */
	private static function build_report( $scope, $user_id, $repair ) {
		$scope = 'all' === $scope ? 'all' : 'single';
		$users = self::get_target_users( $scope, $user_id );
		$rows = array();
		$log = array();
		$summary = array(
			'users_scanned'             => 0,
			'users_with_missing_photos' => 0,
			'total_photos_reconnected' => 0,
			'users_skipped'             => 0,
			'errors'                    => 0,
			'warnings'                  => 0,
		);

		foreach ( $users as $user ) {
			$summary['users_scanned']++;

			if ( ! ( $user instanceof WP_User ) ) {
				$summary['users_skipped']++;
				$summary['errors']++;
				$log[] = 'Skipped invalid user record.';
				continue;
			}

			$row = self::inspect_user( $user );
			$rows[] = $row;

			if ( ! empty( $row['warnings'] ) ) {
				$summary['warnings'] += count( $row['warnings'] );
			}

			if ( empty( $row['missing_urls'] ) ) {
				$log[] = sprintf( 'User %d (%s): no missing photo URLs found.', $user->ID, $user->user_login );
				continue;
			}

			$summary['users_with_missing_photos']++;
			$log[] = sprintf(
				'User %d (%s): %d missing photo URL(s) detected.',
				$user->ID,
				$user->user_login,
				count( $row['missing_urls'] )
			);

			if ( ! $repair ) {
				continue;
			}

			if ( empty( $row['safe_to_update'] ) ) {
				$summary['users_skipped']++;
				$summary['warnings']++;
				$log[] = sprintf( 'User %d (%s): repair skipped because existing dsb_photos could not be safely normalized without risking data loss.', $user->ID, $user->user_login );
				continue;
			}

			$updated_photos = $row['normalized_photos'];
			foreach ( $row['missing_urls'] as $missing_url ) {
				$updated_photos[] = array(
					'url'     => $missing_url,
					'privacy' => 'public',
				);
				$summary['total_photos_reconnected']++;
				$log[] = sprintf( 'User %d (%s): queued reconnect %s.', $user->ID, $user->user_login, $missing_url );
			}

			$result = update_user_meta( $user->ID, 'dsb_photos', $updated_photos );
			if ( false === $result ) {
				$summary['errors']++;
				$log[] = sprintf( 'User %d (%s): update_user_meta returned false.', $user->ID, $user->user_login );
			} else {
				$log[] = sprintf( 'User %d (%s): dsb_photos updated safely with %d total photo entries.', $user->ID, $user->user_login, count( $updated_photos ) );
			}
		}

		if ( empty( $users ) ) {
			$summary['users_skipped']++;
			$summary['warnings']++;
			$log[] = 'No users found for selected scope.';
		}

		return array(
			'generated_at' => current_time( 'mysql' ),
			'mode'         => $repair ? 'repair' : 'dry-run',
			'scope'        => $scope,
			'user_id'      => $user_id,
			'summary'      => $summary,
			'rows'         => $rows,
			'log'          => $log,
		);
	}

	/**
	 * Get target users.
	 *
	 * @param string $scope   single|all.
	 * @param int    $user_id User ID.
	 * @return array<int,WP_User>
	 */
	private static function get_target_users( $scope, $user_id ) {
		if ( 'single' === $scope ) {
			$user = get_userdata( $user_id );
			return $user ? array( $user ) : array();
		}

		return get_users(
			array(
				'fields'  => 'all',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => -1,
			)
		);
	}

	/**
	 * Inspect one user.
	 *
	 * @param WP_User $user User.
	 * @return array<string,mixed>
	 */
	private static function inspect_user( $user ) {
		$raw_photos = get_user_meta( $user->ID, 'dsb_photos', true );
		$normalization = self::normalize_photos( $raw_photos );
		$existing_urls = self::extract_urls( $normalization['photos'] );
		$attachment_urls = self::get_user_image_attachment_urls( $user->ID );
		$missing_urls = array_values( array_diff( self::normalize_url_list( $attachment_urls ), self::normalize_url_list( $existing_urls ) ) );
		$display_missing = self::urls_by_normalized_key( $attachment_urls, $missing_urls );

		return array(
			'user_id'          => (int) $user->ID,
			'username'         => $user->user_login,
			'existing_urls'    => $existing_urls,
			'attachment_urls'  => $attachment_urls,
			'missing_urls'     => $display_missing,
			'would_add'        => array_map(
				function ( $url ) {
					return array(
						'url'     => $url,
						'privacy' => 'public',
					);
				},
				$display_missing
			),
			'normalized_photos' => $normalization['photos'],
			'warnings'          => $normalization['warnings'],
			'safe_to_update'    => $normalization['safe_to_update'],
		);
	}

	/**
	 * Normalize dsb_photos without requiring the main plugin class.
	 *
	 * @param mixed $raw Raw user meta.
	 * @return array{photos:array<int,array<string,string>>,warnings:array<int,string>,safe_to_update:bool}
	 */
	private static function normalize_photos( $raw ) {
		$warnings = array();
		$safe_to_update = true;

		if ( is_string( $raw ) ) {
			$maybe = maybe_unserialize( $raw );
			if ( $maybe !== $raw ) {
				$raw = $maybe;
			} elseif ( '' !== trim( $raw ) ) {
				if ( filter_var( $raw, FILTER_VALIDATE_URL ) ) {
					$raw = array( $raw );
				} else {
					$warnings[] = 'Malformed dsb_photos value was not an array or URL string; repair will skip this user to avoid overwriting existing data.';
					$safe_to_update = false;
					$raw = array();
				}
			}
		}

		if ( empty( $raw ) ) {
			return array( 'photos' => array(), 'warnings' => $warnings, 'safe_to_update' => $safe_to_update );
		}

		if ( ! is_array( $raw ) ) {
			$warnings[] = 'Malformed dsb_photos value was not an array; repair will preserve recoverable photo entries only.';
			return array( 'photos' => array(), 'warnings' => $warnings, 'safe_to_update' => false );
		}

		$photos = array();
		$seen = array();
		foreach ( $raw as $entry ) {
			$url = '';
			$privacy = 'public';

			if ( is_array( $entry ) && isset( $entry['url'] ) ) {
				$url = esc_url_raw( $entry['url'] );
				$privacy = isset( $entry['privacy'] ) && 'private' === $entry['privacy'] ? 'private' : 'public';
			} elseif ( is_string( $entry ) ) {
				$url = esc_url_raw( $entry );
			}

			if ( '' === $url ) {
				$warnings[] = 'Skipped an unreadable dsb_photos entry during normalization.';
				$safe_to_update = false;
				continue;
			}

			$key = self::normalize_url_key( $url );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$photos[] = array(
				'url'     => $url,
				'privacy' => $privacy,
			);
		}

		return array( 'photos' => $photos, 'warnings' => $warnings, 'safe_to_update' => $safe_to_update );
	}

	/**
	 * Get image attachment URLs uploaded by a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,string>
	 */
	private static function get_user_image_attachment_urls( $user_id ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		$urls = array();
		foreach ( $attachments as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$urls[] = esc_url_raw( $url );
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Extract URLs from normalized photos.
	 *
	 * @param array<int,array<string,string>> $photos Photos.
	 * @return array<int,string>
	 */
	private static function extract_urls( $photos ) {
		$urls = array();
		foreach ( $photos as $photo ) {
			if ( ! empty( $photo['url'] ) ) {
				$urls[] = $photo['url'];
			}
		}
		return $urls;
	}

	/**
	 * Normalize a URL list to comparison keys.
	 *
	 * @param array<int,string> $urls URLs.
	 * @return array<int,string>
	 */
	private static function normalize_url_list( $urls ) {
		return array_values( array_unique( array_map( array( __CLASS__, 'normalize_url_key' ), $urls ) ) );
	}

	/**
	 * Return original URLs matching normalized keys.
	 *
	 * @param array<int,string> $urls Original URLs.
	 * @param array<int,string> $keys Normalized keys.
	 * @return array<int,string>
	 */
	private static function urls_by_normalized_key( $urls, $keys ) {
		$wanted = array_fill_keys( $keys, true );
		$matched = array();
		foreach ( $urls as $url ) {
			$key = self::normalize_url_key( $url );
			if ( isset( $wanted[ $key ] ) ) {
				$matched[] = $url;
			}
		}
		return array_values( array_unique( $matched ) );
	}

	/**
	 * Normalize URL for duplicate comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function normalize_url_key( $url ) {
		$url = trim( (string) $url );
		$url = preg_replace( '/\?.*$/', '', $url );
		return strtolower( untrailingslashit( $url ) );
	}

	/**
	 * Append persistent repair log.
	 *
	 * @param array<string,mixed> $report Report.
	 */
	private static function append_persistent_log( $report ) {
		$logs = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}
		$logs[] = array(
			'generated_at' => $report['generated_at'],
			'scope'        => $report['scope'],
			'user_id'      => $report['user_id'],
			'summary'      => $report['summary'],
			'log'          => $report['log'],
		);
		$logs = array_slice( $logs, -10 );
		update_option( self::LOG_OPTION, $logs, false );
	}

	/**
	 * Create a signed review token without writing dry-run data to the DB.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return string
	 */
	private static function create_review_token( $report ) {
		$payload = wp_json_encode(
			array(
				'scope'        => $report['scope'],
				'user_id'      => (int) $report['user_id'],
				'generated_at' => $report['generated_at'],
			)
		);
		$encoded = base64_encode( $payload );
		$signature = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );

		return $encoded . '.' . $signature;
	}

	/**
	 * Decode and verify a review token.
	 *
	 * @param string $token Review token.
	 * @return array<string,mixed>|false
	 */
	private static function decode_review_token( $token ) {
		if ( '' === $token || false === strpos( $token, '.' ) ) {
			return false;
		}

		list( $encoded, $signature ) = explode( '.', $token, 2 );
		$expected = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		$payload = json_decode( base64_decode( $encoded ), true );
		if ( ! is_array( $payload ) || empty( $payload['scope'] ) ) {
			return false;
		}

		return array(
			'scope'        => 'all' === $payload['scope'] ? 'all' : 'single',
			'user_id'      => isset( $payload['user_id'] ) ? absint( $payload['user_id'] ) : 0,
			'generated_at' => isset( $payload['generated_at'] ) ? sanitize_text_field( $payload['generated_at'] ) : '',
		);
	}

	/**
	 * Render report.
	 *
	 * @param array<string,mixed> $report Report.
	 */
	private static function render_report( $report ) {
		$summary = $report['summary'];
		?>
		<h2><?php echo 'repair' === $report['mode'] ? esc_html__( 'Repair Report', 'dsb-photo-repair' ) : esc_html__( 'Dry Run Report', 'dsb-photo-repair' ); ?></h2>
		<p><strong><?php esc_html_e( 'Generated:', 'dsb-photo-repair' ); ?></strong> <?php echo esc_html( $report['generated_at'] ); ?></p>
		<ul>
			<li><?php printf( esc_html__( 'Users scanned: %d', 'dsb-photo-repair' ), (int) $summary['users_scanned'] ); ?></li>
			<li><?php printf( esc_html__( 'Users with missing photos: %d', 'dsb-photo-repair' ), (int) $summary['users_with_missing_photos'] ); ?></li>
			<li><?php printf( esc_html__( 'Total photos reconnected: %d', 'dsb-photo-repair' ), (int) $summary['total_photos_reconnected'] ); ?></li>
			<li><?php printf( esc_html__( 'Users skipped: %d', 'dsb-photo-repair' ), (int) $summary['users_skipped'] ); ?></li>
			<li><?php printf( esc_html__( 'Errors/warnings: %d/%d', 'dsb-photo-repair' ), (int) $summary['errors'], (int) $summary['warnings'] ); ?></li>
		</ul>

		<?php if ( 'dry-run' === $report['mode'] ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 8px;">
				<input type="hidden" name="action" value="dsb_photo_repair_export_csv">
				<input type="hidden" name="dsb_review_token" value="<?php echo esc_attr( $report['review_token'] ); ?>">
				<?php wp_nonce_field( 'dsb_photo_repair_export_csv', 'dsb_photo_repair_nonce' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Export Dry Run CSV', 'dsb-photo-repair' ); ?></button>
			</form>

			<form method="post" action="" style="display:inline-block;">
				<?php wp_nonce_field( 'dsb_photo_repair_run', 'dsb_photo_repair_nonce' ); ?>
				<input type="hidden" name="dsb_review_token" value="<?php echo esc_attr( $report['review_token'] ); ?>">
				<label>
					<input type="checkbox" name="dsb_backup_confirmed" value="1" required>
					<?php esc_html_e( 'I have backed up the live database and /wp-content/uploads/.', 'dsb-photo-repair' ); ?>
				</label>
				<button type="submit" name="dsb_photo_repair_run" value="1" class="button button-secondary" onclick="return confirm('Run repair now? This appends missing public photo URLs only. It does not delete files, attachments, or existing dsb_photos entries.');">
					<?php esc_html_e( 'Run Repair From This Dry Run', 'dsb-photo-repair' ); ?>
				</button>
			</form>
		<?php endif; ?>

		<table class="widefat striped" style="margin-top: 16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'User ID', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Username', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Existing dsb_photos URLs', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Media attachment URLs', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Missing URLs', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Would add', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Safe to update', 'dsb-photo-repair' ); ?></th>
					<th><?php esc_html_e( 'Warnings', 'dsb-photo-repair' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['user_id'] ); ?></td>
						<td><?php echo esc_html( $row['username'] ); ?></td>
						<td><?php echo esc_html( implode( "\n", $row['existing_urls'] ) ); ?></td>
						<td><?php echo esc_html( implode( "\n", $row['attachment_urls'] ) ); ?></td>
						<td><?php echo esc_html( implode( "\n", $row['missing_urls'] ) ); ?></td>
						<td><?php echo esc_html( wp_json_encode( $row['would_add'] ) ); ?></td>
						<td><?php echo ! empty( $row['safe_to_update'] ) ? esc_html__( 'Yes', 'dsb-photo-repair' ) : esc_html__( 'No', 'dsb-photo-repair' ); ?></td>
						<td><?php echo esc_html( implode( "\n", $row['warnings'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Action Log', 'dsb-photo-repair' ); ?></h3>
		<pre style="white-space: pre-wrap; background: #fff; border: 1px solid #ccd0d4; padding: 12px;"><?php echo esc_html( implode( "\n", $report['log'] ) ); ?></pre>
		<?php
	}

	/**
	 * Render last repair log.
	 *
	 * @param array<int,array<string,mixed>> $logs Logs.
	 */
	private static function render_last_log( $logs ) {
		$last = end( $logs );
		if ( empty( $last ) || ! is_array( $last ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Last Persistent Repair Log', 'dsb-photo-repair' ); ?></h2>
		<p><?php echo esc_html( $last['generated_at'] ); ?></p>
		<pre style="white-space: pre-wrap; background: #fff; border: 1px solid #ccd0d4; padding: 12px;"><?php echo esc_html( implode( "\n", $last['log'] ) ); ?></pre>
		<?php
	}

	/**
	 * Render admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    Notice type.
	 */
	private static function render_notice( $message, $type ) {
		$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
		echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Export reviewed dry-run CSV.
	 */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'dsb-photo-repair' ) );
		}

		check_admin_referer( 'dsb_photo_repair_export_csv', 'dsb_photo_repair_nonce' );

		$token = isset( $_POST['dsb_review_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dsb_review_token'] ) ) : '';
		$review = self::decode_review_token( $token );
		if ( empty( $review ) ) {
			wp_die( esc_html__( 'Dry-run review token is invalid. Run another dry run before exporting.', 'dsb-photo-repair' ) );
		}

		$report = self::build_report( $review['scope'], absint( $review['user_id'] ), false );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=dsb-photo-repair-dry-run-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'user_id', 'username', 'existing_dsb_photos_urls', 'attachment_urls', 'missing_urls', 'would_add_json', 'safe_to_update', 'warnings' ) );

		foreach ( $report['rows'] as $row ) {
			fputcsv(
				$output,
				array(
					$row['user_id'],
					$row['username'],
					implode( "\n", $row['existing_urls'] ),
					implode( "\n", $row['attachment_urls'] ),
					implode( "\n", $row['missing_urls'] ),
					wp_json_encode( $row['would_add'] ),
					! empty( $row['safe_to_update'] ) ? 'yes' : 'no',
					implode( "\n", $row['warnings'] ),
				)
			);
		}

		fclose( $output );
		exit;
	}
}

DSB_Photo_Repair_Tool::init();
