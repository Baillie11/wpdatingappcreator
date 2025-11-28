<?php
/**
 * Matching algorithm for finding compatible users.
 *
 * @package DatingSiteBuilder
 */

class DSB_Matching {

	/**
	 * Get matches for a user.
	 *
	 * @param int $user_id User ID to find matches for.
	 * @param array $args Optional arguments (limit, offset, filters).
	 * @return array Array of matched user IDs with scores.
	 */
	public static function get_matches( $user_id, $args = array() ) {
		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		// Get current user's preferences
		$user_prefs = self::get_user_preferences( $user_id );
		
		// Get potential matches
		$potential_matches = self::get_potential_matches( $user_id, $user_prefs );
		
		// Score each potential match
		$scored_matches = array();
		foreach ( $potential_matches as $match_id ) {
			$score = self::calculate_match_score( $user_id, $match_id, $user_prefs );
			if ( $score > 0 ) {
				$scored_matches[ $match_id ] = $score;
			}
		}

		// Sort by score (highest first)
		arsort( $scored_matches );

		// Apply limit and offset
		$scored_matches = array_slice( $scored_matches, $args['offset'], $args['limit'], true );

		return $scored_matches;
	}

	/**
	 * Get user's dating preferences from meta.
	 */
	private static function get_user_preferences( $user_id ) {
		return array(
			'gender'            => get_user_meta( $user_id, 'dsb_gender', true ),
			'looking_for'       => get_user_meta( $user_id, 'dsb_looking_for', true ),
			'date_of_birth'     => get_user_meta( $user_id, 'dsb_date_of_birth', true ),
			'age_min'           => get_user_meta( $user_id, 'dsb_age_min', true ) ?: 18,
			'age_max'           => get_user_meta( $user_id, 'dsb_age_max', true ) ?: 99,
			'country'           => get_user_meta( $user_id, 'dsb_country', true ),
			'state'             => get_user_meta( $user_id, 'dsb_state', true ),
			'interests'         => get_user_meta( $user_id, 'dsb_interests', true ),
			'block_other_countries' => get_user_meta( $user_id, 'dsb_block_other_countries', true ),
			'block_other_states'    => get_user_meta( $user_id, 'dsb_block_other_states', true ),
		);
	}

	/**
	 * Get potential matches based on basic criteria.
	 */
	private static function get_potential_matches( $user_id, $user_prefs ) {
		global $wpdb;

		// Build query to get users matching basic criteria
		$meta_query = array(
			'relation' => 'AND',
		);

		// Must be interested in user's gender
		$user_gender = $user_prefs['gender'];
		$looking_for = $user_prefs['looking_for'];

		// Orientation compatibility
		if ( ! empty( $looking_for ) ) {
			if ( is_array( $looking_for ) ) {
				$meta_query[] = array(
					'key'     => 'dsb_gender',
					'value'   => $looking_for,
					'compare' => 'IN',
				);
			} else {
				$meta_query[] = array(
					'key'     => 'dsb_gender',
					'value'   => $looking_for,
					'compare' => '=',
				);
			}
		}

		// Location filtering
		if ( ! empty( $user_prefs['block_other_countries'] ) && $user_prefs['block_other_countries'] === 'yes' ) {
			if ( ! empty( $user_prefs['country'] ) ) {
				$meta_query[] = array(
					'key'     => 'dsb_country',
					'value'   => $user_prefs['country'],
					'compare' => '=',
				);
			}
		}

		if ( ! empty( $user_prefs['block_other_states'] ) && $user_prefs['block_other_states'] === 'yes' ) {
			if ( ! empty( $user_prefs['state'] ) ) {
				$meta_query[] = array(
					'key'     => 'dsb_state',
					'value'   => $user_prefs['state'],
					'compare' => '=',
				);
			}
		}

		// Profile must be approved
		$meta_query[] = array(
			'key'     => 'dsb_profile_approved',
			'value'   => '1',
			'compare' => '=',
		);

		// User must not be banned
		$meta_query[] = array(
			'key'     => 'dsb_banned',
			'compare' => 'NOT EXISTS',
		);

		// Get users with dating member or premium roles
		$user_query = new WP_User_Query( array(
			'role__in'    => array( 'dating_member', 'dating_premium' ),
			'exclude'     => array( $user_id ),
			'meta_query'  => $meta_query,
			'number'      => 500, // Max potential matches to consider
			'fields'      => 'ID',
		) );

		$potential_matches = $user_query->get_results();

		// Remove blocked users
		$blocked_users = self::get_blocked_users( $user_id );
		$potential_matches = array_diff( $potential_matches, $blocked_users );

		// Remove users who blocked current user
		$blocked_by_users = self::get_users_who_blocked( $user_id );
		$potential_matches = array_diff( $potential_matches, $blocked_by_users );

		return array_values( $potential_matches );
	}

	/**
	 * Calculate match score between two users.
	 *
	 * @param int $user_id Current user ID.
	 * @param int $match_id Potential match user ID.
	 * @param array $user_prefs Current user's preferences.
	 * @return float Match score (0-100).
	 */
	private static function calculate_match_score( $user_id, $match_id, $user_prefs ) {
		$score = 0;
		$max_score = 0;

		$matching_mode = get_option( 'dsb_matching_mode', 'hybrid' );
		
		$match_prefs = self::get_user_preferences( $match_id );

		// 1. Orientation/gender compatibility (40 points max)
		$max_score += 40;
		if ( self::check_orientation_compatibility( $user_prefs, $match_prefs ) ) {
			$score += 40;
		}

		// 2. Age compatibility (20 points max)
		if ( $matching_mode !== 'simple' ) {
			$max_score += 20;
			$age_score = self::calculate_age_compatibility( $user_prefs, $match_prefs );
			$score += $age_score * 20;
		}

		// 3. Location proximity (10 points max)
		if ( $matching_mode !== 'simple' ) {
			$max_score += 10;
			if ( self::check_location_match( $user_prefs, $match_prefs ) ) {
				$score += 10;
			}
		}

		// 4. Shared interests (30 points max)
		if ( in_array( $matching_mode, array( 'interests', 'hybrid' ) ) ) {
			$max_score += 30;
			$interests_score = self::calculate_interests_score( $user_prefs['interests'], $match_prefs['interests'] );
			$score += $interests_score * 30;
		}

		// 5. Activity recency bonus (10 points max)
		$max_score += 10;
		$activity_score = self::calculate_activity_score( $match_id );
		$score += $activity_score * 10;

		// Normalize to 0-100
		if ( $max_score > 0 ) {
			$score = ( $score / $max_score ) * 100;
		}

		// Allow filtering of the score
		return apply_filters( 'dsb_match_score', $score, $user_id, $match_id );
	}

	/**
	 * Check if two users are orientation-compatible.
	 */
	private static function check_orientation_compatibility( $user1_prefs, $user2_prefs ) {
		$user1_gender = $user1_prefs['gender'];
		$user1_looking_for = $user1_prefs['looking_for'];
		$user2_gender = $user2_prefs['gender'];
		$user2_looking_for = $user2_prefs['looking_for'];

		// Check if user1 is looking for user2's gender
		$user1_interested = false;
		if ( is_array( $user1_looking_for ) ) {
			$user1_interested = in_array( $user2_gender, $user1_looking_for );
		} else {
			$user1_interested = ( $user1_looking_for === $user2_gender );
		}

		// Check if user2 is looking for user1's gender
		$user2_interested = false;
		if ( is_array( $user2_looking_for ) ) {
			$user2_interested = in_array( $user1_gender, $user2_looking_for );
		} else {
			$user2_interested = ( $user2_looking_for === $user1_gender );
		}

		return $user1_interested && $user2_interested;
	}

	/**
	 * Calculate age compatibility score (0-1).
	 */
	private static function calculate_age_compatibility( $user1_prefs, $user2_prefs ) {
		$user1_age = self::calculate_age( $user1_prefs['date_of_birth'] );
		$user2_age = self::calculate_age( $user2_prefs['date_of_birth'] );

		// Check if ages are within each other's preferences
		$user1_ok = ( $user2_age >= $user1_prefs['age_min'] && $user2_age <= $user1_prefs['age_max'] );
		$user2_ok = ( $user1_age >= $user2_prefs['age_min'] && $user1_age <= $user2_prefs['age_max'] );

		if ( $user1_ok && $user2_ok ) {
			return 1.0;
		} elseif ( $user1_ok || $user2_ok ) {
			return 0.5;
		}

		return 0;
	}

	/**
	 * Calculate age from date of birth.
	 */
	private static function calculate_age( $date_of_birth ) {
		if ( empty( $date_of_birth ) ) {
			return 0;
		}
		
		$dob = strtotime( $date_of_birth );
		if ( ! $dob ) {
			return 0;
		}

		return floor( ( time() - $dob ) / 31556926 );
	}

	/**
	 * Check if location matches.
	 */
	private static function check_location_match( $user1_prefs, $user2_prefs ) {
		$country_match = false;
		$state_match = false;

		// Country match
		if ( ! empty( $user1_prefs['country'] ) && ! empty( $user2_prefs['country'] ) ) {
			$country_match = ( strtolower( $user1_prefs['country'] ) === strtolower( $user2_prefs['country'] ) );
		}

		// State match
		if ( ! empty( $user1_prefs['state'] ) && ! empty( $user2_prefs['state'] ) ) {
			$state_match = ( strtolower( $user1_prefs['state'] ) === strtolower( $user2_prefs['state'] ) );
		}

		// Return true if same country (better if same state too)
		return $country_match;
	}

	/**
	 * Calculate interests compatibility score (0-1).
	 */
	private static function calculate_interests_score( $interests1, $interests2 ) {
		if ( empty( $interests1 ) || empty( $interests2 ) ) {
			return 0;
		}

		// Convert to arrays of interests
		$interests1_arr = array_map( 'trim', explode( ',', strtolower( $interests1 ) ) );
		$interests2_arr = array_map( 'trim', explode( ',', strtolower( $interests2 ) ) );

		// Find common interests
		$common = array_intersect( $interests1_arr, $interests2_arr );
		$total_unique = count( array_unique( array_merge( $interests1_arr, $interests2_arr ) ) );

		if ( $total_unique === 0 ) {
			return 0;
		}

		// Jaccard similarity
		return count( $common ) / $total_unique;
	}

	/**
	 * Calculate activity score based on recent login (0-1).
	 */
	private static function calculate_activity_score( $user_id ) {
		$last_active = get_user_meta( $user_id, 'dsb_last_active', true );
		
		if ( empty( $last_active ) ) {
			// No activity recorded, use registration date
			$user = get_userdata( $user_id );
			$last_active = strtotime( $user->user_registered );
		} else {
			$last_active = strtotime( $last_active );
		}

		$days_since_active = ( time() - $last_active ) / 86400;

		// Full points if active within last 7 days
		if ( $days_since_active <= 7 ) {
			return 1.0;
		}
		// Half points if active within last 30 days
		elseif ( $days_since_active <= 30 ) {
			return 0.5;
		}
		// Quarter points if active within last 90 days
		elseif ( $days_since_active <= 90 ) {
			return 0.25;
		}

		return 0.1; // Minimal points for inactive users
	}

	/**
	 * Get list of users blocked by current user.
	 */
	private static function get_blocked_users( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_blocks';
		
		$blocked = $wpdb->get_col( $wpdb->prepare(
			"SELECT blocked_user_id FROM $table WHERE user_id = %d",
			$user_id
		) );

		return $blocked ? $blocked : array();
	}

	/**
	 * Get list of users who have blocked current user.
	 */
	private static function get_users_who_blocked( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_blocks';
		
		$blocked_by = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM $table WHERE blocked_user_id = %d",
			$user_id
		) );

		return $blocked_by ? $blocked_by : array();
	}

	/**
	 * Update user's last active timestamp.
	 */
	public static function update_last_active( $user_id ) {
		update_user_meta( $user_id, 'dsb_last_active', current_time( 'mysql' ) );
	}
}
