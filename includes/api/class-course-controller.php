<?php
/**
 * REST API Controller for Courses.
 *
 * @package SMC\Viable\API
 */

declare(strict_types=1);

namespace SMC\Viable\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use SMC\Viable\Enrollment_Manager;
use SMC\Viable\LMS_Progress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Course_Controller
 */
class Course_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'courses';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/courses/{id}/structure
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/structure',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_structure' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );
        
        if ( ! $post || 'smc_training' !== $post->post_type ) {
            return new WP_Error( 'rest_course_invalid', 'Invalid course ID.', [ 'status' => 404 ] );
        }

        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', 'You must be logged in.', [ 'status' => 401 ] );
        }

        // Use Enrollment Manager to gate access
        if ( ! Enrollment_Manager::can_access_course( get_current_user_id(), $id ) ) {
             return new WP_Error( 'rest_forbidden', 'You do not have access to this course.', [ 'status' => 403 ] );
        }
        
		return true;
	}

	/**
	 * Get Course Structure.
	 */
	public function get_structure( $request ) {
		$id = (int) $request->get_param( 'id' );
        $course = get_post( $id );
        $user_id = get_current_user_id();
        
        $sections = get_post_meta( $id, '_course_sections', true ) ?: [];

        // Hydrate progress
        $progress_data = LMS_Progress::get_full_course_progress( $user_id, $id );
        $lesson_status_map = $progress_data['lessons'];

        // Get Recommendations (if enrolled via quiz)
        $enrollment = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare(
            "SELECT source, source_meta FROM {$GLOBALS['wpdb']->prefix}smc_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id, $id
        ) );

        $recommended_sections = [];
        if ( $enrollment && 'quiz' === $enrollment->source && $enrollment->source_meta ) {
            $meta = json_decode( $enrollment->source_meta, true );
            $recommended_sections = $meta['recommended_sections'] ?? [];
            // Assuming recommended_sections is array of section indices or titles?
            // Existing plan says: "Store section titles in addition to indices"
            // For now, let's assume indices are reliable enough or we match by title if possible.
            // Let's pass the raw structure to frontend to decide.
        }
        
        // Hydrate lessons content
        $hydrated_sections = [];
        foreach ( $sections as $index => $section ) {
            $section_data = [
                'index' => $index,
                'title' => $section['title'],
                'lessons' => [],
                'is_recommended' => false,
            ];

            // Check if recommended
            // If recommended_sections contains indices like [ { section_indices: [0, 2] } ] ? 
            // Wait, standard structure in rules JSON was: "recommended_sections": [ { "course_id": 12, "section_indices": [2, 3] } ]
            // So we need to parse that.
            // But here we are inside a specific course. So we just need the list of indices for THIS course.
            // In process_quiz_enrollment, we stored: `'recommended_sections' => $rule['recommended_sections'] ?? []`
            // So source_meta contains the full array of recommendations across potentially multiple courses?
            // Actually, in `process_quiz_enrollment`, we filter `recommended_sections` for the specific course?
            // "For each range: select courses... For each course in range: optionally select recommended sections"
            // The JSON structure:
            // "recommended_sections": [ { "course_id": 15, "section_indices": [0, 1] } ]
            // So we need to find the entry for $id. (Wait, course_id inside `recommended_sections` might be redundant if the rule maps to courses differently).
            // Let's assume the rules JSON structure is:
            // "recommended_sections": [ { "course_id": 123, "indices": [0,1] } ]
            // Then we filter here.
            
            if ( ! empty( $recommended_sections ) ) {
                foreach ( $recommended_sections as $rec ) {
                     // Check if this recommendation block applies to current course
                     if ( isset( $rec['course_id'] ) && (int) $rec['course_id'] === $id ) {
                         if ( isset( $rec['section_indices'] ) && in_array( $index, $rec['section_indices'] ) ) {
                             $section_data['is_recommended'] = true;
                         }
                     }
                }
            }

            if ( ! empty( $section['lessons'] ) ) {
                foreach ( $section['lessons'] as $lesson_id ) {
                    $lesson = get_post( $lesson_id );
                    if ( ! $lesson ) continue;
                    
                    $type = get_post_meta( $lesson->ID, '_lesson_type', true ) ?: 'text';
                    $duration = get_post_meta( $lesson->ID, '_lesson_duration', true ) ?: 0;
                    $video_url = get_post_meta( $lesson->ID, '_lesson_video_url', true ) ?: '';
                    
                    $status = $lesson_status_map[ $lesson->ID ] ?? 'not_started';

                    $section_data['lessons'][] = [
                        'id' => $lesson->ID,
                        'title' => $lesson->post_title,
                        'type' => $type,
                        'duration' => (int) $duration,
                        'status' => $status, 
                        'is_locked' => false, // Could implement sequential unlock here
                        'content' => $lesson->post_content,
                        'video_url' => $video_url,
                    ];
                }
            }
            $hydrated_sections[] = $section_data;
        }

		return rest_ensure_response( [
            'id' => $id,
            'title' => $course->post_title,
            'sections' => $hydrated_sections,
            'progress' => $progress_data['overall_percent'],
        ] );
	}
}
