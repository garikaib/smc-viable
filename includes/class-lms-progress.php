<?php
/**
 * LMS Progress Manager
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMS_Progress
 */
class LMS_Progress {

	/**
	 * Mark lesson as complete.
	 *
	 * @param int $user_id User ID.
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return bool|WP_Error
	 */
	public static function complete_lesson( int $user_id, int $lesson_id, int $course_id ) {
		global $wpdb;
		$table = $wpdb->prefix . LMS_DB::TABLE_PROGRESS;

		// Use REPLACE INTO to handle duplicates (upsert)
		// Or INSERT ON DUPLICATE KEY UPDATE
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO $table (user_id, course_id, lesson_id, status, progress_percent, completed_at, updated_at)
			 VALUES (%d, %d, %d, 'completed', 100, NOW(), NOW())
			 ON DUPLICATE KEY UPDATE status = 'completed', progress_percent = 100, completed_at = NOW(), updated_at = NOW()",
			$user_id,
			$course_id,
			$lesson_id
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', 'Could not save progress.' );
		}

        // Recalculate Course Progress?
        // Maybe do this async or on-demand
        self::update_course_progress( $user_id, $course_id );

		return true;
	}

    /**
     * Get Lesson Progress Map for a Course.
     * Returns array of lesson_id => status.
     */
    public static function get_lesson_progress_map( int $user_id, int $course_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . LMS_DB::TABLE_PROGRESS;
        
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT lesson_id, status FROM $table WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        ) );
        
        $progress = [];
        foreach ( $results as $row ) {
            $progress[ $row->lesson_id ] = $row->status;
        }
        return $progress;
    }

    /**
     * Legacy wrapper or specific percent getter.
     */
    public static function get_course_progress_percent( int $user_id, int $course_id ): int {
        $data = self::get_full_course_progress( $user_id, $course_id );
        return $data['overall_percent'];
    }

    /**
     * Get Full Course Progress (Overall % + Lesson Map).
     */
    public static function get_full_course_progress( int $user_id, int $course_id ): array {
        $lesson_progress = self::get_lesson_progress_map( $user_id, $course_id );
        
        // Calculate overall %
        $sections = get_post_meta( $course_id, '_course_sections', true ) ?: [];
        $total_lessons = 0;
        $completed_count = 0;

        foreach ( $sections as $s ) {
            $lessons = $s['lessons'] ?? [];
            foreach ( $lessons as $lid ) {
                $total_lessons++;
                if ( isset( $lesson_progress[$lid] ) && 'completed' === $lesson_progress[$lid] ) {
                    $completed_count++;
                }
            }
        }

        $percent = $total_lessons > 0 ? (int) round( ($completed_count / $total_lessons) * 100 ) : 0;

        return [
            'overall_percent' => $percent,
            'lessons'         => $lesson_progress,
            'total_lessons'   => $total_lessons,
            'completed_count' => $completed_count,
        ];
    }
    
    /**
     * Update/Cache Course Progress and Check Completion.
     */
    public static function update_course_progress( int $user_id, int $course_id ) {
        $data = self::get_full_course_progress( $user_id, $course_id );
        $percent = $data['overall_percent'];
        
        // Cache in user meta
        update_user_meta( $user_id, "_smc_progress_{$course_id}", $percent );

        // Check for Course Completion
        if ( $percent === 100 ) {
            // Auto-complete enrollment if not already
            if ( class_exists( '\SMC\Viable\Enrollment_Manager' ) ) {
                // We check if already completed to avoid duplicate actions
                // Enrollment_Manager::mark_course_completed handles checks internally?
                // It updates status to 'completed'.
                // But we should differentiate auto-complete vs manual?
                // The plan says: "When all lessons completed -> auto-update enrollment status"
                
                // Let's call a method that sets status to completed without 'manual' flag
                global $wpdb;
                $wpdb->update(
                    "{$wpdb->prefix}smc_enrollments",
                    [ 
                        'status' => 'completed', 
                        'completed_at' => current_time( 'mysql' ),
                        'source_meta' => json_encode( ['completed_via' => 'auto'] ) // Merge?
                    ],
                    [ 'user_id' => $user_id, 'course_id' => $course_id, 'status' => 'active' ] // Only update if active
                );
                
                do_action( 'smc_training_completed', $user_id, $course_id );
            }
        }
    }
}
