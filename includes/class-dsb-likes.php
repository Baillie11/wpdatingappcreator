<?php
/**
 * Likes and matching system.
 *
 * @package DatingSiteBuilder
 */

class DSB_Likes {

	/**
	 * Toggle like via AJAX.
	 */
	public function ajax_toggle_like() {
		check_ajax_referer( 'dsb_likes_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$target_id = intval( $_POST['target_id'] );

		if ( $user_id === $target_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot like yourself', 'dating-site-builder' ) ) );
		}

		// Check if already liked
		$already_liked = self::has_liked( $user_id, $target_id );

		if ( $already_liked ) {
			// Unlike
			self::remove_like( $user_id, $target_id );
			wp_send_json_success( array(
				'liked'    => false,
				'is_match' => false,
			) );
		} else {
			// Like
			self::add_like( $user_id, $target_id );
			
			// Check if it's a mutual match
			$is_match = self::is_mutual_match( $user_id, $target_id );
			
			if ( $is_match ) {
				// Trigger match action (extensibility point for notifications)
				do_action( 'dsb_new_match', $user_id, $target_id );
			}

			wp_send_json_success( array(
				'liked'    => true,
				'is_match' => $is_match,
			) );
		}
	}

	/**
	 * Get likes via AJAX.
	 */
	public function ajax_get_likes() {
		check_ajax_referer( 'dsb_likes_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'sent';

		if ( $type === 'sent' ) {
			$likes = self::get_user_likes( $user_id );
		} elseif ( $type === 'received' ) {
			$likes = self::get_likes_received( $user_id );
		} elseif ( $type === 'matches' ) {
			$likes = self::get_mutual_matches( $user_id );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid type', 'dating-site-builder' ) ) );
		}

		wp_send_json_success( array(
			'likes' => $likes,
		) );
	}

	/**
	 * Add a like.
	 */
	public static function add_like( $user_id, $target_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		return $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'target_id'  => $target_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * Remove a like.
	 */
	public static function remove_like( $user_id, $target_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		return $wpdb->delete(
			$table,
			array(
				'user_id'   => $user_id,
				'target_id' => $target_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Check if user has liked target.
	 */
	public static function has_liked( $user_id, $target_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND target_id = %d",
			$user_id,
			$target_id
		) );

		return $count > 0;
	}

	/**
	 * Check if two users are mutual matches.
	 */
	public static function is_mutual_match( $user1_id, $user2_id ) {
		return self::has_liked( $user1_id, $user2_id ) && self::has_liked( $user2_id, $user1_id );
	}

	/**
	 * Get all users that the current user has liked.
	 */
	public static function get_user_likes( $user_id, $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT target_id FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
			$user_id,
			$limit
		) );
	}

	/**
	 * Alias for get_user_likes - Get all users that the current user has liked.
	 */
	public static function get_liked_users( $user_id, $limit = 100 ) {
		return self::get_user_likes( $user_id, $limit );
	}

	/**
	 * Get all users who have liked the current user.
	 */
	public static function get_likes_received( $user_id, $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM $table WHERE target_id = %d ORDER BY created_at DESC LIMIT %d",
			$user_id,
			$limit
		) );
	}

	/**
	 * Get mutual matches for a user.
	 */
	public static function get_mutual_matches( $user_id, $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		$sql = $wpdb->prepare(
			"SELECT l1.target_id as match_id
			FROM $table l1
			INNER JOIN $table l2 
			    ON l1.user_id = l2.target_id 
			    AND l1.target_id = l2.user_id
			WHERE l1.user_id = %d
			ORDER BY l1.created_at DESC
			LIMIT %d",
			$user_id,
			$limit
		);

		return $wpdb->get_col( $sql );
	}

	/**
	 * Get match count for a user.
	 */
	public static function get_match_count( $user_id ) {
		return count( self::get_mutual_matches( $user_id, 9999 ) );
	}

	/**
	 * Get new likes count (likes received that user hasn't seen yet).
	 */
	public static function get_new_likes_count( $user_id ) {
		$received_likes = self::get_likes_received( $user_id );
		$last_checked = get_user_meta( $user_id, 'dsb_likes_last_checked', true );
		
		if ( empty( $last_checked ) ) {
			return count( $received_likes );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'dsb_likes';

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE target_id = %d AND created_at > %s",
			$user_id,
			$last_checked
		) );
	}

	/**
	 * Mark likes as checked.
	 */
	public static function mark_likes_checked( $user_id ) {
		update_user_meta( $user_id, 'dsb_likes_last_checked', current_time( 'mysql' ) );
	}
}
