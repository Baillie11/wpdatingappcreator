<?php
/**
 * Messaging system for private conversations.
 *
 * @package DatingSiteBuilder
 */

class DSB_Messaging {

	/**
	 * Send a message via AJAX.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'dsb_messaging_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$sender_id = get_current_user_id();
		$receiver_id = intval( $_POST['receiver_id'] );
		$message_text = wp_kses_post( $_POST['message'] );

		// Validate
		if ( empty( $message_text ) ) {
			wp_send_json_error( array( 'message' => __( 'Message cannot be empty', 'dating-site-builder' ) ) );
		}

		if ( $sender_id === $receiver_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot message yourself', 'dating-site-builder' ) ) );
		}

		// Check if blocked
		if ( self::is_blocked( $sender_id, $receiver_id ) || self::is_blocked( $receiver_id, $sender_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to send message', 'dating-site-builder' ) ) );
		}

		// Check if mutual like required
		if ( get_option( 'dsb_require_mutual_like', false ) ) {
			if ( ! DSB_Likes::is_mutual_match( $sender_id, $receiver_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You can only message users you have matched with', 'dating-site-builder' ) ) );
			}
		}

		// Check premium limits (extensibility point)
		$can_send = apply_filters( 'dsb_can_send_message', true, $sender_id, $receiver_id );
		if ( ! $can_send ) {
			wp_send_json_error( array( 'message' => __( 'Message limit reached. Upgrade to premium for unlimited messaging', 'dating-site-builder' ) ) );
		}

		// Insert message
		$message_id = self::insert_message( $sender_id, $receiver_id, $message_text );

		if ( $message_id ) {
			// Send notification (extensibility point)
			do_action( 'dsb_message_sent', $message_id, $sender_id, $receiver_id );

			wp_send_json_success( array(
				'message_id' => $message_id,
				'timestamp'  => current_time( 'mysql' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send message', 'dating-site-builder' ) ) );
		}
	}

	/**
	 * Get messages via AJAX.
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'dsb_messaging_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$other_user_id = intval( $_POST['other_user_id'] );
		$last_message_id = isset( $_POST['last_message_id'] ) ? intval( $_POST['last_message_id'] ) : 0;

		$messages = self::get_conversation( $user_id, $other_user_id, $last_message_id );

		wp_send_json_success( array(
			'messages' => $messages,
		) );
	}

	/**
	 * Mark messages as read via AJAX.
	 */
	public function ajax_mark_read() {
		check_ajax_referer( 'dsb_messaging_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		$other_user_id = intval( $_POST['other_user_id'] );

		self::mark_conversation_read( $user_id, $other_user_id );

		wp_send_json_success();
	}

	/**
	 * Block user via AJAX.
	 */
	public function ajax_block_user() {
		check_ajax_referer( 'dsb_messaging_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$blocked_user_id = intval( $_POST['blocked_user_id'] );

		if ( self::block_user( $user_id, $blocked_user_id ) ) {
			wp_send_json_success( array( 'message' => __( 'User blocked successfully', 'dating-site-builder' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to block user', 'dating-site-builder' ) ) );
		}
	}

	/**
	 * Report user via AJAX.
	 */
	public function ajax_report_user() {
		check_ajax_referer( 'dsb_messaging_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in', 'dating-site-builder' ) ) );
		}

		$reporter_id = get_current_user_id();
		$reported_user_id = intval( $_POST['reported_user_id'] );
		$reason = sanitize_textarea_field( $_POST['reason'] );
		$type = sanitize_text_field( $_POST['type'] ?: 'user' );

		if ( empty( $reason ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a reason', 'dating-site-builder' ) ) );
		}

		if ( self::report_user( $reporter_id, $reported_user_id, $reason, $type ) ) {
			wp_send_json_success( array( 'message' => __( 'Report submitted. Thank you', 'dating-site-builder' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to submit report', 'dating-site-builder' ) ) );
		}
	}

	/**
	 * Insert a message into the database.
	 */
	public static function insert_message( $sender_id, $receiver_id, $message_text ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';

		$result = $wpdb->insert(
			$table,
			array(
				'sender_id'    => $sender_id,
				'receiver_id'  => $receiver_id,
				'message_text' => $message_text,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get conversation between two users.
	 */
	public static function get_conversation( $user1_id, $user2_id, $after_message_id = 0, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';

		$sql = $wpdb->prepare(
			"SELECT * FROM $table 
			WHERE ((sender_id = %d AND receiver_id = %d AND deleted_by_sender = 0) 
			   OR (sender_id = %d AND receiver_id = %d AND deleted_by_receiver = 0))
			AND id > %d
			ORDER BY created_at ASC 
			LIMIT %d",
			$user1_id, $user2_id, $user2_id, $user1_id, $after_message_id, $limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get inbox conversations for a user.
	 */
	public static function get_inbox( $user_id, $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';

		// Get unique conversations with latest message
		$sql = $wpdb->prepare(
			"SELECT m1.*, 
			       CASE 
			         WHEN m1.sender_id = %d THEN m1.receiver_id 
			         ELSE m1.sender_id 
			       END as other_user_id,
			       (SELECT COUNT(*) FROM $table m2 
			        WHERE m2.sender_id = other_user_id 
			        AND m2.receiver_id = %d 
			        AND m2.read_at IS NULL) as unread_count
			FROM $table m1
			WHERE (m1.sender_id = %d OR m1.receiver_id = %d)
			AND m1.id IN (
			    SELECT MAX(id) 
			    FROM $table m3 
			    WHERE (m3.sender_id = %d OR m3.receiver_id = %d)
			    GROUP BY LEAST(m3.sender_id, m3.receiver_id), GREATEST(m3.sender_id, m3.receiver_id)
			)
			ORDER BY m1.created_at DESC
			LIMIT %d",
			$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Mark conversation as read.
	 */
	public static function mark_conversation_read( $user_id, $other_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';

		return $wpdb->query( $wpdb->prepare(
			"UPDATE $table 
			SET read_at = %s 
			WHERE sender_id = %d 
			AND receiver_id = %d 
			AND read_at IS NULL",
			current_time( 'mysql' ),
			$other_user_id,
			$user_id
		) );
	}

	/**
	 * Check if user is blocked.
	 */
	public static function is_blocked( $user_id, $blocked_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_blocks';

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE user_id = %d AND blocked_user_id = %d",
			$user_id,
			$blocked_user_id
		) );

		return $count > 0;
	}

	/**
	 * Block a user.
	 */
	public static function block_user( $user_id, $blocked_user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_blocks';

		return $wpdb->insert(
			$table,
			array(
				'user_id'         => $user_id,
				'blocked_user_id' => $blocked_user_id,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * Report a user.
	 */
	public static function report_user( $reporter_id, $reported_user_id, $reason, $type = 'user', $related_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_reports';

		return $wpdb->insert(
			$table,
			array(
				'reporter_id'      => $reporter_id,
				'reported_user_id' => $reported_user_id,
				'report_type'      => $type,
				'report_reason'    => $reason,
				'related_id'       => $related_id,
				'status'           => 'pending',
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get unread message count for user.
	 */
	public static function get_unread_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE receiver_id = %d AND read_at IS NULL AND deleted_by_receiver = 0",
			$user_id
		) );
	}
}
