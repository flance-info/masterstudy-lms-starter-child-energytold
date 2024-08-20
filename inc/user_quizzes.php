<?php

function stm_lms_get_user_max_quiz( $user_id, $quiz_id, $fields = array() ) {
	global $wpdb;
	$table = stm_lms_user_quizzes_name( $wpdb );

	$fields = ( empty( $fields ) ) ? '*' : implode( ',', $fields );

	return $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT {$fields} FROM {$table} WHERE user_id = %d AND quiz_id = %d ORDER BY user_quiz_id",
			$user_id,
			$quiz_id
		),
		ARRAY_A
	);
}