<?php

use MasterStudy\Lms\Repositories\CurriculumMaterialRepository;
use MasterStudy\Lms\Repositories\CurriculumRepository;

new STM_LMS_User_Manager_Course_User_Child();

class STM_LMS_User_Manager_Course_User_Child extends STM_LMS_User_Manager_Course_User {

	  public function __construct() {
        // Hook to the 'init' action to ensure all actions are registered before attempting to remove them
        add_action('init', array($this, 'remove_and_add_actions'));
    }

    public function remove_and_add_actions() {
        // Remove all actions attached to the 'wp_ajax_stm_lms_dashboard_get_student_progress' hook
        remove_all_actions('wp_ajax_stm_lms_dashboard_get_student_progress');

        // Add the new action for 'wp_ajax_stm_lms_dashboard_get_student_progress'
        add_action('wp_ajax_stm_lms_dashboard_get_student_progress', array($this, 'student_progress'));
    }

	// phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
	public static function _student_progress( $course_id, $student_id ) {
		$curriculum = ( new CurriculumRepository() )->get_curriculum( $course_id );

		foreach ( $curriculum['materials'] as &$material ) {
			$material = array_merge( $material, self::course_material_data( $material, $student_id, $course_id ) );
		}

		$user_stats = STM_LMS_Helpers::simplify_db_array(
			stm_lms_get_user_course(
				$student_id,
				$course_id,
				array(
					'current_lesson_id',
					'progress_percent',
				)
			)
		);
		if ( empty( $user_stats['current_lesson_id'] ) ) {
			$user_stats['current_lesson_id'] = STM_LMS_Lesson::get_first_lesson( $course_id );
		}

		$lesson_type = get_post_meta( $user_stats['current_lesson_id'], 'type', true );
		if ( empty( $lesson_type ) ) {
			$lesson_type = 'text';
		}

		$user_stats['lesson_type'] = $lesson_type;

		$curriculum = array_merge( $user_stats, $curriculum );

		$curriculum['user']         = STM_LMS_User::get_current_user( $student_id );
		$curriculum['course_title'] = get_the_title( $course_id );

		return $curriculum;
	}

	public function student_progress() {
		check_ajax_referer( 'stm_lms_dashboard_get_student_progress', 'nonce' );

		if ( ! STM_LMS_User_Manager_Interface::isInstructor() ) {
			die;
		}

		$request_body = file_get_contents( 'php://input' );

		$data = json_decode( $request_body, true );

		if ( empty( $data['user_id'] ) || empty( $data['course_id'] ) ) {
			die;
		}

		$course_id  = intval( $data['course_id'] );
		$student_id = intval( $data['user_id'] );

		wp_send_json( self::_student_progress( $course_id, $student_id ) );
	}

	public static function course_material_data( $material, $student_id, $course_id ) {
		$previous_completed = ( isset( $completed ) ) ? $completed : 'first';
		$has_preview        = STM_LMS_Lesson::lesson_has_preview( $material['post_id'] );

		$user      = STM_LMS_User::get_current_user( $student_id );
		$user_id   = $user['id'];
		$duration  = '';
		$questions = '';
		$quiz_info = array();

		if ( 'stm-quizzes' === $material['post_type'] ) {
			$type      = 'quiz';
			$quiz_info = STM_LMS_Helpers::simplify_db_array( stm_lms_get_user_quizzes( $user_id, $material['post_id'], array( 'progress' ) ) );
			$completed = STM_LMS_Quiz_Child::quiz_passed_child( $material['post_id'], $user_id );

			$q = get_post_meta( $material['post_id'], 'questions', true );
			if ( ! empty( $q ) ) :
				/* translators: %s: Post Type Label */
				$questions = sprintf(
					/* translators: %s: Count of Questions */
					_n(
						'%s question',
						'%s questions',
						count(
							explode(
								',',
								$q
							)
						),
						'masterstudy-lms-learning-management-system'
					),
					count(
						explode(
							',',
							$q
						)
					)
				);
			endif;

		} elseif ( 'stm-assignments' === $material['post_type'] ) {
			$type      = 'assignment';
			$completed = class_exists( 'STM_LMS_Assignments' ) && STM_LMS_Assignments::has_passed_assignment( $material['post_id'], $student_id );
		} else {
			$completed = STM_LMS_Lesson::is_lesson_completed( $user_id, $course_id, $material['post_id'] );
			$type      = get_post_meta( $material['post_id'], 'type', true );
			$duration  = get_post_meta( $material['post_id'], 'duration', true );
		}

		if ( empty( $type ) ) {
			$type = 'lesson';
		}

		if ( empty( $duration ) ) {
			$duration = '';
		}

		$locked = str_replace(
			'prev-status-',
			'',
			apply_filters( 'stm_lms_prev_status', "{$previous_completed}", $course_id, $material['post_id'], $user_id )
		);

		$locked = ( empty( $locked ) );

		return compact( 'type', 'quiz_info', 'locked', 'completed', 'has_preview', 'duration', 'questions' );
	}
}
