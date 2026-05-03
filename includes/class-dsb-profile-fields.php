<?php
/**
 * Manage profile fields and configurations.
 *
 * @package DatingSiteBuilder
 */

class DSB_Profile_Fields {

	/**
	 * Get all available profile fields based on site configuration.
	 *
	 * @return array Array of field definitions
	 */
	public static function get_all_fields() {
		$site_type = get_option( 'dsb_site_type', 'standard' );
		$enabled_fields = get_option( 'dsb_enabled_field_groups', array() );
		
		$fields = array();

		// Basic fields (always present)
		if ( in_array( 'basics', $enabled_fields ) || empty( $enabled_fields ) ) {
			$fields = array_merge( $fields, self::get_basic_fields() );
		}

		// About me fields
		if ( in_array( 'about', $enabled_fields ) || empty( $enabled_fields ) ) {
			$fields = array_merge( $fields, self::get_about_fields() );
		}

		// Partner / dual-profile fields are always defined; the front-end
		// edit form hides them via JS unless the member picks a couple
		// option in the "I am / We are" select.
		$fields = array_merge( $fields, self::get_partner_fields() );

		// Lifestyle fields
		if ( in_array( 'lifestyle', $enabled_fields ) ) {
			$fields = array_merge( $fields, self::get_lifestyle_fields() );
		}

		// Accessibility fields (especially for NDIS mode)
		if ( in_array( 'accessibility', $enabled_fields ) ) {
			$fields = array_merge( $fields, self::get_accessibility_fields() );
		}

		// Adult/Swingers specific fields
		if ( in_array( 'adult_preferences', $enabled_fields ) && in_array( $site_type, array( 'adult', 'swingers' ) ) ) {
			$fields = array_merge( $fields, self::get_adult_fields() );
		}

		// Allow customization via filter
		return apply_filters( 'dsb_profile_fields', $fields );
	}

	/**
	 * Get profile-edit fields in UI order.
	 *
	 * Couple-only partner fields are moved directly beneath the
	 * "I am / We are" selector so the editor matches the wording
	 * that those fields are unlocked "below" when a couple option
	 * is selected.
	 *
	 * @return array Ordered field definitions for edit screens.
	 */
	public static function get_edit_fields() {
		$fields         = self::get_all_fields();
		$partner_fields = array();
		$primary_fields = array();

		foreach ( $fields as $field_key => $field ) {
			if ( ! empty( $field['requires_couple'] ) ) {
				$partner_fields[ $field_key ] = $field;
				continue;
			}

			$primary_fields[ $field_key ] = $field;
		}

		if ( empty( $partner_fields ) || ! isset( $primary_fields['profile_kind'] ) ) {
			return array_merge( $primary_fields, $partner_fields );
		}

		$ordered_fields = array();
		foreach ( $primary_fields as $field_key => $field ) {
			$ordered_fields[ $field_key ] = $field;

			if ( 'profile_kind' === $field_key ) {
				foreach ( $partner_fields as $partner_key => $partner_field ) {
					$ordered_fields[ $partner_key ] = $partner_field;
				}
			}
		}

		return $ordered_fields;
	}

	/**
	 * Basic profile fields.
	 */
	private static function get_basic_fields() {
		$allow_custom_gender = get_option( 'dsb_allow_custom_gender', true );

		$gender_options = array(
			'male'   => __( 'Male', 'dating-site-builder' ),
			'female' => __( 'Female', 'dating-site-builder' ),
		);

		if ( $allow_custom_gender ) {
			$gender_options['non-binary'] = __( 'Non-binary', 'dating-site-builder' );
			$gender_options['other'] = __( 'Other', 'dating-site-builder' );
			$gender_options['prefer-not-say'] = __( 'Prefer not to say', 'dating-site-builder' );
		}

		$fields = array(
			'profile_kind' => array(
				'label'       => __( 'I am / We are', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => true,
				'options'     => array(
					'male'           => __( 'Male', 'dating-site-builder' ),
					'female'         => __( 'Female', 'dating-site-builder' ),
					'couple_mf'      => __( 'Couple (Male & Female)', 'dating-site-builder' ),
					'couple_ff'      => __( 'Couple (Female & Female)', 'dating-site-builder' ),
					'couple_mm'      => __( 'Couple (Male & Male)', 'dating-site-builder' ),
					'group'          => __( 'Group', 'dating-site-builder' ),
					'gender_diverse' => __( 'Gender Diverse', 'dating-site-builder' ),
				),
				'privacy'     => false,
				'description' => __( 'Are you posting as an individual, couple, or group? Pick a Couple option to unlock the partner profile fields below.', 'dating-site-builder' ),
			),
			'gender' => array(
				'label'       => __( 'Gender', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => true,
				'options'     => $gender_options,
				'privacy'     => true,
			),
			'date_of_birth' => array(
				'label'       => __( 'Date of Birth', 'dating-site-builder' ),
				'type'        => 'date',
				'required'    => true,
				'privacy'     => false, // Age shown, not exact DOB
				'description' => __( 'Your exact date will not be shown, only your age.', 'dating-site-builder' ),
			),
			'looking_for' => array(
				'label'       => __( 'Looking For', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => true,
				'options'     => array(
					'casual_fun'         => __( 'Casual fun', 'dating-site-builder' ),
					'ongoing_connection' => __( 'Ongoing connection', 'dating-site-builder' ),
					'friends_first'      => __( 'Friends first', 'dating-site-builder' ),
					'couples'            => __( 'Couples', 'dating-site-builder' ),
					'group_experiences'  => __( 'Group experiences', 'dating-site-builder' ),
					'online_chat_only'   => __( 'Online chat only', 'dating-site-builder' ),
					'open_to_anything'   => __( 'Open to anything', 'dating-site-builder' ),
				),
				'privacy'     => true,
				'description' => __( 'Pick everything that fits — this is the core matching driver.', 'dating-site-builder' ),
			),
			'relationship_status' => array(
				'label'       => __( 'Relationship Status', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'single'       => __( 'Single', 'dating-site-builder' ),
					'married'      => __( 'Married', 'dating-site-builder' ),
					'polyamorous'  => __( 'Polyamorous', 'dating-site-builder' ),
					'divorced'     => __( 'Divorced', 'dating-site-builder' ),
					'widowed'      => __( 'Widowed', 'dating-site-builder' ),
					'separated'    => __( 'Separated', 'dating-site-builder' ),
					'complicated'  => __( 'It\'s complicated', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
			'country' => array(
				'label'       => __( 'Country', 'dating-site-builder' ),
				'type'        => 'text',
				'required'    => false,
				'privacy'     => false,
			),
			'state' => array(
				'label'       => __( 'State/Region', 'dating-site-builder' ),
				'type'        => 'text',
				'required'    => false,
				'privacy'     => false,
			),
			'city' => array(
				'label'       => __( 'City / Postcode', 'dating-site-builder' ),
				'type'        => 'text',
				'required'    => true,
				'privacy'     => true,
				'description' => __( 'Your city or postcode — used to surface nearby members.', 'dating-site-builder' ),
			),
		);

		return $fields;
	}

	/**
	 * About me fields.
	 */
	private static function get_about_fields() {
		return array(
			'vibe' => array(
				'label'       => __( 'How would you describe yourself?', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => false,
				'options'     => array(
					'adventurous' => __( 'Adventurous', 'dating-site-builder' ),
					'laid_back'   => __( 'Laid-back', 'dating-site-builder' ),
					'flirty'      => __( 'Flirty', 'dating-site-builder' ),
					'discreet'    => __( 'Discreet', 'dating-site-builder' ),
					'confident'   => __( 'Confident', 'dating-site-builder' ),
					'curious'     => __( 'Curious', 'dating-site-builder' ),
					'dominant'    => __( 'Dominant', 'dating-site-builder' ),
					'submissive'  => __( 'Submissive', 'dating-site-builder' ),
					'playful'     => __( 'Playful', 'dating-site-builder' ),
					'open_minded' => __( 'Open-minded', 'dating-site-builder' ),
				),
				'privacy'     => false,
				'description' => __( 'Tick the vibes that match you. We use these instead of a free-text bio.', 'dating-site-builder' ),
			),
			'interaction_style' => array(
				'label'       => __( 'How do you like to connect?', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => false,
				'options'     => array(
					'straight_to_point' => __( 'Straight to the point', 'dating-site-builder' ),
					'chat_first'        => __( 'Chat first', 'dating-site-builder' ),
					'meet_quickly'      => __( 'Meet quickly', 'dating-site-builder' ),
					'take_it_slow'      => __( 'Take it slow', 'dating-site-builder' ),
					'love_banter'       => __( 'Love a bit of banter', 'dating-site-builder' ),
				),
				'privacy'     => false,
			),
			'interests' => array(
				'label'       => __( 'Interests', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => false,
				'options'     => array(
					'travel'          => __( 'Travel', 'dating-site-builder' ),
					'beach'           => __( 'Beach', 'dating-site-builder' ),
					'fitness'         => __( 'Fitness', 'dating-site-builder' ),
					'nightlife'       => __( 'Nightlife', 'dating-site-builder' ),
					'food_drinks'     => __( 'Food & drinks', 'dating-site-builder' ),
					'scavenger_hunts' => __( 'Scavenger hunts', 'dating-site-builder' ),
					'music'           => __( 'Music', 'dating-site-builder' ),
					'movies'          => __( 'Movies', 'dating-site-builder' ),
					'outdoors'        => __( 'Outdoors', 'dating-site-builder' ),
					'events'          => __( 'Events', 'dating-site-builder' ),
				),
				'privacy'     => false,
			),
			'intent_tonight' => array(
				'label'       => __( 'Tonight I\'m…', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'browsing'     => __( 'Just browsing', 'dating-site-builder' ),
					'open_to_chat' => __( 'Open to chat', 'dating-site-builder' ),
					'looking'      => __( 'Looking to meet', 'dating-site-builder' ),
					'spontaneous'  => __( 'Ready for something spontaneous', 'dating-site-builder' ),
				),
				'privacy'     => true,
				'description' => __( 'Real-time intent — helps active members match with you right now.', 'dating-site-builder' ),
			),
		);
	}

	/**
	 * Partner / dual-profile fields.
	 *
	 * These are stored alongside the primary member's meta with a
	 * `partner_` prefix and only rendered when the member's
	 * `profile_kind` value starts with `couple_` (handled in the
	 * profile-edit and profile-view templates).
	 */
	private static function get_partner_fields() {
		return array(
			'partner_display_name' => array(
				'label'           => __( 'Partner Name', 'dating-site-builder' ),
				'type'            => 'text',
				'required'        => false,
				'maxlength'       => 50,
				'privacy'         => false,
				'requires_couple' => true,
				'description'     => __( 'How your partner is shown on your couple profile.', 'dating-site-builder' ),
			),
			'partner_date_of_birth' => array(
				'label'           => __( 'Partner Date of Birth', 'dating-site-builder' ),
				'type'            => 'date',
				'required'        => false,
				'privacy'         => true,
				'requires_couple' => true,
				'description'     => __( 'Used only to display partner\'s age. The exact date stays private.', 'dating-site-builder' ),
			),
			'partner_headline' => array(
				'label'           => __( 'Partner Headline', 'dating-site-builder' ),
				'type'            => 'text',
				'required'        => false,
				'maxlength'       => 100,
				'privacy'         => false,
				'requires_couple' => true,
				'description'     => __( 'A short tagline for your partner (max 100 characters).', 'dating-site-builder' ),
			),
			'partner_about' => array(
				'label'           => __( 'About Partner', 'dating-site-builder' ),
				'type'            => 'textarea',
				'required'        => false,
				'maxlength'       => 500,
				'privacy'         => false,
				'requires_couple' => true,
				'description'     => __( 'Tell others about your partner (max 500 characters).', 'dating-site-builder' ),
			),
			'partner_looking_for' => array(
				'label'           => __( 'What Partner Is Looking For', 'dating-site-builder' ),
				'type'            => 'textarea',
				'required'        => false,
				'maxlength'       => 500,
				'privacy'         => false,
				'requires_couple' => true,
			),
		);
	}

	/**
	 * Lifestyle and interests fields.
	 */
	private static function get_lifestyle_fields() {
		return array(
			'occupation' => array(
				'label'       => __( 'Occupation', 'dating-site-builder' ),
				'type'        => 'text',
				'required'    => false,
				'privacy'     => true,
			),
			'education' => array(
				'label'       => __( 'Education', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'high-school' => __( 'High School', 'dating-site-builder' ),
					'some-college' => __( 'Some College', 'dating-site-builder' ),
					'bachelors'   => __( 'Bachelor\'s Degree', 'dating-site-builder' ),
					'masters'     => __( 'Master\'s Degree', 'dating-site-builder' ),
					'phd'         => __( 'PhD/Doctorate', 'dating-site-builder' ),
					'other'       => __( 'Other', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
			'smoking' => array(
				'label'       => __( 'Smoking', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'no'           => __( 'Non-smoker', 'dating-site-builder' ),
					'occasionally' => __( 'Occasionally', 'dating-site-builder' ),
					'yes'          => __( 'Yes', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
			'drinking' => array(
				'label'       => __( 'Drinking', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'no'           => __( 'Non-drinker', 'dating-site-builder' ),
					'socially'     => __( 'Socially', 'dating-site-builder' ),
					'regularly'    => __( 'Regularly', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
		);
	}

	/**
	 * Accessibility and NDIS-related fields.
	 */
	private static function get_accessibility_fields() {
		$selected_accessibility = get_option( 'dsb_accessibility_fields', array() );
		
		$all_accessibility_fields = array(
			'communication_preference' => array(
				'label'       => __( 'Communication Preferences', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => false,
				'options'     => array(
					'verbal'      => __( 'Verbal', 'dating-site-builder' ),
					'text'        => __( 'Text/Written', 'dating-site-builder' ),
					'sign'        => __( 'Sign Language', 'dating-site-builder' ),
					'assisted'    => __( 'Assisted Communication', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
			'mobility_info' => array(
				'label'       => __( 'Mobility Information', 'dating-site-builder' ),
				'type'        => 'textarea',
				'required'    => false,
				'maxlength'   => 300,
				'privacy'     => true,
				'description' => __( 'Optional: Share any mobility considerations', 'dating-site-builder' ),
			),
			'sensory_preferences' => array(
				'label'       => __( 'Sensory Preferences', 'dating-site-builder' ),
				'type'        => 'textarea',
				'required'    => false,
				'maxlength'   => 300,
				'privacy'     => true,
				'description' => __( 'Optional: Any sensory considerations for activities', 'dating-site-builder' ),
			),
			'support_needs' => array(
				'label'       => __( 'Support Needs', 'dating-site-builder' ),
				'type'        => 'textarea',
				'required'    => false,
				'maxlength'   => 300,
				'privacy'     => true,
				'description' => __( 'Optional: General support needs to be aware of', 'dating-site-builder' ),
			),
			'ndis_participant' => array(
				'label'       => __( 'NDIS Participant', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					''       => __( 'Prefer not to say', 'dating-site-builder' ),
					'yes'    => __( 'Yes', 'dating-site-builder' ),
					'no'     => __( 'No', 'dating-site-builder' ),
				),
				'privacy'     => true,
			),
		);

		// Only return selected fields
		if ( ! empty( $selected_accessibility ) ) {
			return array_intersect_key( $all_accessibility_fields, array_flip( $selected_accessibility ) );
		}

		return $all_accessibility_fields;
	}

	/**
	 * Adult/Swingers specific fields (tasteful and non-explicit).
	 */
	private static function get_adult_fields() {
		$site_type = get_option( 'dsb_site_type', 'standard' );
		
		$fields = array();

		if ( $site_type === 'swingers' ) {
			$fields['profile_type'] = array(
				'label'       => __( 'Profile Type', 'dating-site-builder' ),
				'type'        => 'select',
				'required'    => true,
				'options'     => array(
					'single-male'   => __( 'Single Male', 'dating-site-builder' ),
					'single-female' => __( 'Single Female', 'dating-site-builder' ),
					'couple'        => __( 'Couple', 'dating-site-builder' ),
				),
				'privacy'     => false,
			);

			$fields['seeking'] = array(
				'label'       => __( 'Seeking', 'dating-site-builder' ),
				'type'        => 'checkbox',
				'required'    => true,
				'options'     => array(
					'single-males'   => __( 'Single Males', 'dating-site-builder' ),
					'single-females' => __( 'Single Females', 'dating-site-builder' ),
					'couples'        => __( 'Couples', 'dating-site-builder' ),
					'groups'         => __( 'Groups', 'dating-site-builder' ),
				),
				'privacy'     => false,
			);
		}

		$fields['preferences_text'] = array(
			'label'       => __( 'Preferences & Interests', 'dating-site-builder' ),
			'type'        => 'textarea',
			'required'    => false,
			'maxlength'   => 500,
			'privacy'     => true,
			'description' => __( 'Keep content respectful and appropriate', 'dating-site-builder' ),
		);

		return $fields;
	}

	/**
	 * Get a specific field configuration.
	 */
	public static function get_field( $field_key ) {
		$all_fields = self::get_all_fields();
		return isset( $all_fields[ $field_key ] ) ? $all_fields[ $field_key ] : null;
	}

	/**
	 * Validate field value.
	 */
	public static function validate_field( $field_key, $value ) {
		$field = self::get_field( $field_key );
		
		if ( ! $field ) {
			return new WP_Error( 'invalid_field', __( 'Invalid field', 'dating-site-builder' ) );
		}

		// Check required
		if ( $field['required'] && empty( $value ) ) {
			return new WP_Error( 'required_field', sprintf( __( '%s is required', 'dating-site-builder' ), $field['label'] ) );
		}

		// Check maxlength
		if ( isset( $field['maxlength'] ) && strlen( $value ) > $field['maxlength'] ) {
			return new WP_Error( 'field_too_long', sprintf( __( '%s exceeds maximum length', 'dating-site-builder' ), $field['label'] ) );
		}

		// Validate date of birth
		if ( $field_key === 'date_of_birth' ) {
			$date = strtotime( $value );
			if ( ! $date ) {
				return new WP_Error( 'invalid_date', __( 'Invalid date format', 'dating-site-builder' ) );
			}
			
			$min_age = get_option( 'dsb_minimum_age', 18 );
			$age = floor( ( time() - $date ) / 31556926 ); // Seconds in a year
			
			if ( $age < $min_age ) {
				return new WP_Error( 'age_too_young', sprintf( __( 'You must be at least %d years old', 'dating-site-builder' ), $min_age ) );
			}
		}

		return true;
	}

	/**
	 * Sanitize field value.
	 */
	public static function sanitize_field( $field_key, $value ) {
		$field = self::get_field( $field_key );
		
		if ( ! $field ) {
			return '';
		}

		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( $value );
			
			case 'textarea':
				return sanitize_textarea_field( $value );
			
			case 'select':
				// Ensure value is in allowed options
				if ( isset( $field['options'][ $value ] ) ) {
					return sanitize_text_field( $value );
				}
				return '';
			
			case 'checkbox':
				// For multiple checkboxes, value should be an array
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return array();
			
			case 'date':
				return sanitize_text_field( $value );
			
			default:
				return sanitize_text_field( $value );
		}
	}
}
