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
        self::update_course_progress_meta( $user_id, $course_id );

		return true;
	}

    /**
     * Get User Progress for a Course.
     */
    public static function get_course_progress( int $user_id, int $course_id ): array {
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
     * Update/Cache Course Progress % in User Meta (optional optimization).
     */
    private static function update_course_progress_meta( int $user_id, int $course_id ) {
        // Logic to count total lessons vs completed lessons
        // This requires knowing total lessons for course_id
        $sections = get_post_meta( $course_id, '_course_sections', true ) ?: [];
        $total_lessons = 0;
        foreach ( $sections as $s ) {
            $total_lessons += count( $s['lessons'] ?? [] );
        }
        
        if ( $total_lessons === 0 ) return;
        
        $progress = self::get_course_progress( $user_id, $course_id );
        $completed_count = 0;
        
        // Count only lessons that are actually part of the course structure now
        // (in case course changed or user has old progress)
        foreach ( $sections as $s ) {
            foreach ( $s['lessons'] ?? [] as $lid ) {
                if ( isset( $progress[$lid] ) && $progress[$lid] === 'completed' ) {
                    $completed_count++;
                }
            }
        }
        
        $percent = (int) round( ($completed_count / $total_lessons) * 100 );
        // Store somewhere? Maybe a separate table for course-level progress or user meta.
        // For now, let's just return it or rely on runtime calc.
        // Let's store in user meta for easy dashboard display.
        update_user_meta( $user_id, "_smc_progress_{$course_id}", $percent );
    }
}
