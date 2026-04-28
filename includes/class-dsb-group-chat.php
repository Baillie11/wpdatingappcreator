<?php
/**
 * Group Chat functionality.
 *
 * @package DatingSiteBuilder
 */

class DSB_Group_Chat {

	/**
	 * Send a message to the group chat.
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'dsb_group_chat_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		// Check if user is banned or suspended
		if ( get_user_meta( $user_id, 'dsb_banned', true ) || get_user_meta( $user_id, 'dsb_suspended', true ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account is restricted.', 'dating-site-builder' ) ) );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'dating-site-builder' ) ) );
		}

		// Limit message length
		if ( strlen( $message ) > 1000 ) {
			wp_send_json_error( array( 'message' => __( 'Message is too long (max 1000 characters).', 'dating-site-builder' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'dsb_group_chat';

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'message_text' => $message,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		);

		if ( $result ) {
			$user = get_userdata( $user_id );
			$photos = get_user_meta( $user_id, 'dsb_photos', true );
			$avatar = ! empty( $photos ) && is_array( $photos ) ? $photos[0] : get_avatar_url( $user_id, array( 'size' => 40 ) );

			wp_send_json_success( array(
				'message_id' => $wpdb->insert_id,
				'user_id'    => $user_id,
				'username'   => $user->display_name,
				'avatar'     => $avatar,
				'message'    => esc_html( $message ),
				'time'       => current_time( 'H:i' ),
				'timestamp'  => current_time( 'mysql' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send message.', 'dating-site-builder' ) ) );
		}
	}

	/**
	 * Get messages from the group chat.
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'dsb_group_chat_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'dating-site-builder' ) ) );
		}

		$last_id = isset( $_POST['last_id'] ) ? intval( $_POST['last_id'] ) : 0;
		$limit = isset( $_POST['limit'] ) ? min( intval( $_POST['limit'] ), 100 ) : 50;

		global $wpdb;
		$table = $wpdb->prefix . 'dsb_group_chat';

		// Get messages newer than last_id, or most recent if no last_id
		if ( $last_id > 0 ) {
			$messages = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE id > %d ORDER BY id ASC LIMIT %d",
				$last_id,
				$limit
			) );
		} else {
			// Get the most recent messages (for initial load)
			$messages = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM (
					SELECT * FROM $table ORDER BY id DESC LIMIT %d
				) sub ORDER BY id ASC",
				$limit
			) );
		}

		$formatted_messages = array();
		$current_user_id = get_current_user_id();

		foreach ( $messages as $msg ) {
			$user = get_userdata( $msg->user_id );
			if ( ! $user ) {
				continue;
			}

			$photos = get_user_meta( $msg->user_id, 'dsb_photos', true );
			$avatar = ! empty( $photos ) && is_array( $photos ) ? $photos[0] : get_avatar_url( $msg->user_id, array( 'size' => 40 ) );

			$formatted_messages[] = array(
				'id'         => $msg->id,
				'user_id'    => $msg->user_id,
				'username'   => $user->display_name,
				'avatar'     => $avatar,
				'message'    => esc_html( $msg->message_text ),
				'time'       => date_i18n( 'H:i', strtotime( $msg->created_at ) ),
				'date'       => date_i18n( 'M j', strtotime( $msg->created_at ) ),
				'timestamp'  => $msg->created_at,
				'is_own'     => ( $msg->user_id == $current_user_id ),
				'profile_url' => get_permalink( get_option( 'dsb_profile_view_page' ) ) . '?user_id=' . $msg->user_id,
			);
		}

		wp_send_json_success( array(
			'messages' => $formatted_messages,
			'last_id'  => ! empty( $messages ) ? end( $messages )->id : $last_id,
		) );
	}

	/**
	 * Get online users count (users active in last 5 minutes).
	 */
	public function ajax_get_online_users() {
		check_ajax_referer( 'dsb_group_chat_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		// Update current user's last activity
		update_user_meta( get_current_user_id(), 'dsb_last_activity', current_time( 'mysql' ) );

		global $wpdb;

		// Get users active in the last 5 minutes
		$five_minutes_ago = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );
		
		$online_users = $wpdb->get_results( $wpdb->prepare(
			"SELECT u.ID, u.display_name, um.meta_value as last_activity
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'dsb_last_activity'
			WHERE um.meta_value > %s
			ORDER BY um.meta_value DESC
			LIMIT 50",
			$five_minutes_ago
		) );

		$users = array();
		foreach ( $online_users as $user ) {
			$photos = get_user_meta( $user->ID, 'dsb_photos', true );
			$avatar = ! empty( $photos ) && is_array( $photos ) ? $photos[0] : get_avatar_url( $user->ID, array( 'size' => 32 ) );

			$users[] = array(
				'id'       => $user->ID,
				'username' => $user->display_name,
				'avatar'   => $avatar,
			);
		}

		wp_send_json_success( array(
			'count' => count( $users ),
			'users' => $users,
		) );
	}
}
