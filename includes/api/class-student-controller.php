<?php
/**
 * REST API Controller for Student Hub.
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
 * Class Student_Controller
 */
class Student_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'student';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/student/dashboard
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_dashboard' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // POST /smc/v1/student/progress/complete-lesson
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/progress/complete-lesson',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'complete_lesson' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // POST /smc/v1/student/courses/{id}/complete
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/complete',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'complete_course' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Get Student Dashboard Data.
	 */
	public function get_dashboard( $request ) {
		$user_id = get_current_user_id();
        
        // Pass true to include locked courses (upsell)
        $accessible_courses = Enrollment_Manager::get_accessible_courses( $user_id, true );
        $courses_data = [];

        foreach ( $accessible_courses as $course ) {
            // Get Progress
            $progress_data = LMS_Progress::get_full_course_progress( $user_id, $course->ID );
            
            // Determine status
            $status = 'not_started';
            if ( $progress_data['overall_percent'] > 0 ) {
                $status = 'in_progress';
            }
            if ( $progress_data['overall_percent'] >= 100 || Enrollment_Manager::is_enrolled( $user_id, $course->ID ) && $this->is_manually_completed( $user_id, $course->ID ) ) {
                $status = 'completed';
            }

            $courses_data[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'slug' => $course->post_name,
                'thumbnail' => get_the_post_thumbnail_url( $course->ID, 'medium' ),
                'progress' => $progress_data['overall_percent'],
                'status' => $status,
                'access_source' => $course->access_source_label ?? 'Enrolled',
                'lessons_completed' => $progress_data['completed_count'],
                'total_lessons' => $progress_data['total_lessons'],
                'is_locked' => $course->is_locked ?? false,
            ];
        }

        // Add Plan Info
        $plan = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
        // Add Premium Courses Upsell (if on free/basic)
        // Find all plan-courses higher than current plan
        // ... (Optional for now, straightforward logic)

		return rest_ensure_response( [
            'courses' => $courses_data,
            'user_plan' => $plan,
            'recent_activity' => [], 
        ] );
	}

    private function is_manually_completed( $user_id, $course_id ) {
        // Enrollment manager handles access/status check more robustly
        // Here just strictly check if status is completed in DB if needed, 
        // but get_full_course_progress returns percent based on lessons.
        // Manual completion sets DB status 'completed'. 
        // We really should trust DB status first.
        global $wpdb;
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        ) );
        return 'completed' === $status;
    }

    /**
     * Complete Lesson.
     */
    public function complete_lesson( $request ) {
        $user_id = get_current_user_id();
        $course_id = (int) $request->get_param( 'course_id' );
        $lesson_id = (int) $request->get_param( 'lesson_id' );
        
        if ( ! $course_id || ! $lesson_id ) {
            return new WP_Error( 'missing_params', 'Missing IDs', [ 'status' => 400 ] );
        }
        
        // Ensure access first
        if ( ! Enrollment_Manager::can_access_course( $user_id, $course_id ) ) {
            return new WP_Error( 'forbidden', 'Access denied', [ 'status' => 403 ] );
        }

        $result = LMS_Progress::complete_lesson( $user_id, $lesson_id, $course_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Update overall progress & check completion
        LMS_Progress::update_course_progress( $user_id, $course_id );
        
        // Re-fetch progress to return new state
        $data = LMS_Progress::get_full_course_progress( $user_id, $course_id );
        
        return rest_ensure_response( [
            'success' => true,
            'message' => 'Lesson completed',
            'progress' => $data['overall_percent'],
            'course_completed' => $data['overall_percent'] >= 100,
        ] );
    }

    /**
     * Complete Course Manually.
     */
    public function complete_course( $request ) {
        $user_id = get_current_user_id();
        $course_id = (int) $request->get_param( 'id' );
        
        $result = Enrollment_Manager::mark_course_completed( $user_id, $course_id );
        
        if ( $result ) {
            return rest_ensure_response( [ 'success' => true ] );
        } else {
            return new WP_Error( 'failed', 'Could not complete course', [ 'status' => 500 ] );
        }
    }
}
