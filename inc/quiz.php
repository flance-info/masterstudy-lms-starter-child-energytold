<?php

class STM_LMS_Quiz_Child extends STM_LMS_Quiz {


	public static function quiz_passed_child( $quiz_id, $user_id = '' ) {
		if ( empty( $user_id ) ) {
			$user = STM_LMS_User::get_current_user();
			if ( empty( $user['id'] ) ) {
				return false;
			}
			$user_id = $user['id'];
		}
		$last_quiz = stm_lms_get_user_max_quiz( $user_id, $quiz_id, array( 'progress' ) );
		if ( empty( $last_quiz ) ) {
			return false;
		}
		$max_quiz[] = array_reduce( $last_quiz, function ( $max_item, $current_item ) {
			return $current_item['progress'] > $max_item['progress'] ? $current_item : $max_item;
		}, array( 'progress' => 0 ) );
		$last_quiz  = $max_quiz;
		//stm_lms_get_user_all_quizzes( $user_id, $limit = '', $offset = '', $fields = array(), $get_total = false )
		$last_quiz     = STM_LMS_Helpers::simplify_db_array( $last_quiz );
		$passing_grade = self::passing_grade( STM_LMS_Helpers::parse_meta_field( $quiz_id ) );

		return $last_quiz['progress'] >= $passing_grade;
	}

	public static function can_watch_answers( $quiz_id ) {
		$show_answers = get_post_meta( $quiz_id, 'correct_answer', true );
		if ( ! empty( $show_answers ) && 'on' === $show_answers ) {
			return true;
		}

		return self::quiz_passed( $quiz_id );
	}

	public static function answers_url() {
		return add_query_arg( 'show_answers', '1', STM_LMS_Helpers::get_current_url() );
	}

	public static function show_answers( $quiz_id ) {
		if ( ! self::can_watch_answers( $quiz_id ) ) {
			return false;
		}
		if ( self::quiz_passed( $quiz_id ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_GET['show_answers'] ) && $_GET['show_answers'];
	}

	public static function get_quiz_duration( $quiz_id ) {
		$duration = get_post_meta( $quiz_id, 'duration', true );
		if ( empty( $duration ) ) {
			return 0;
		}

		$duration_measure = get_post_meta( $quiz_id, 'duration_measure', true );
		switch ( $duration_measure ) {
			case 'hours':
				$multiple = 60 * 60;
				break;
			case 'days':
				$multiple = 24 * 60 * 60;
				break;
			default:
				$multiple = 60;
		}

		return $duration * $multiple;
	}

	public static function get_style( $quiz_id ) {
		$quiz_style = get_post_meta( $quiz_id, 'quiz_style', true );

		if ( ! empty( $quiz_style ) && 'global' !== $quiz_style ) {
			return $quiz_style;
		}

		return STM_LMS_Options::get_option( 'quiz_style', 'default' );
	}
}
