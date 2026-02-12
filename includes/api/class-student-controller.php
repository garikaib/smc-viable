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
        Enrollment_Manager::reconcile_user_purchase_enrollments( $user_id );

        // Build dashboard from explicit active/completed enrollments.
        // This keeps Student Portal aligned with actual enrollment records.
        $courses_data = [];
        $enrollments = Enrollment_Manager::get_user_enrollments( $user_id );
        // ... (Keep existing logic)
        $enrolled_statuses = [];
        $enrolled_sources = [];
        $enrolled_meta = [];

        foreach ( $enrollments as $enrollment ) {
            $course_id = isset( $enrollment->course_id ) ? (int) $enrollment->course_id : 0;
            $status    = isset( $enrollment->status ) ? (string) $enrollment->status : '';

            if ( $course_id <= 0 || ! in_array( $status, [ 'active', 'completed' ], true ) ) {
                continue;
            }

            $enrolled_statuses[ $course_id ] = $status;
            $enrolled_sources[ $course_id ]  = isset( $enrollment->source ) ? (string) $enrollment->source : 'manual';
            $raw_meta = isset( $enrollment->source_meta ) ? (string) $enrollment->source_meta : '';
            $decoded_meta = json_decode( $raw_meta, true );
            $enrolled_meta[ $course_id ] = is_array( $decoded_meta ) ? $decoded_meta : [];
        }

        if ( ! empty( $enrolled_statuses ) ) {
            $courses = get_posts( [
                'post_type'      => 'smc_training',
                'post_status'    => [ 'publish', 'private' ],
                'post__in'       => array_map( 'intval', array_keys( $enrolled_statuses ) ),
                'orderby'        => 'post__in',
                'posts_per_page' => -1,
            ] );

            foreach ( $courses as $course ) {
                $progress_data = LMS_Progress::get_full_course_progress( $user_id, (int) $course->ID );

                $progress = isset( $progress_data['overall_percent'] ) ? (int) $progress_data['overall_percent'] : 0;
                $status = 'not_started';
                if ( $progress > 0 ) {
                    $status = 'in_progress';
                }

                $enrollment_status = $enrolled_statuses[ (int) $course->ID ] ?? 'active';
                if ( $progress >= 100 || 'completed' === $enrollment_status ) {
                    $status = 'completed';
                }

                $courses_data[] = [
                    'id'                => (int) $course->ID,
                    'title'             => $this->resolve_course_display_title(
                        (int) $course->ID,
                        $enrolled_sources[ (int) $course->ID ] ?? 'manual',
                        $enrolled_meta[ (int) $course->ID ] ?? []
                    ),
                    'slug'              => $course->post_name,
                    'thumbnail'         => get_the_post_thumbnail_url( $course->ID, 'medium' ),
                    'progress'          => $progress,
                    'status'            => $status,
                    'access_source'     => $this->get_access_source_label( $enrolled_sources[ (int) $course->ID ] ?? 'manual' ),
                    'lessons_completed' => isset( $progress_data['completed_count'] ) ? (int) $progress_data['completed_count'] : 0,
                    'total_lessons'     => isset( $progress_data['total_lessons'] ) ? (int) $progress_data['total_lessons'] : 0,
                    'is_locked'         => false,
                ];
            }
        }

        // Add Plan Info
        $plan = Enrollment_Manager::resolve_user_plan( $user_id );
        // Add Premium Courses Upsell (if on free/basic)
        // Find all plan-courses higher than current plan
        // ... (Optional for now, straightforward logic)

		return rest_ensure_response( [
            'courses' => $courses_data,
            'user_plan' => $plan,
            'recent_activity' => [], 
        ] );
	}

    private function get_access_source_label( string $source ): string {
        switch ( $source ) {
            case 'purchase':
                return 'Purchased';
            case 'invitation':
                return 'Invited';
            case 'quiz':
                return 'Via Assessment';
            case 'manual':
                return 'Assigned';
            default:
                return 'Enrolled';
        }
    }

    private function resolve_course_display_title( int $course_id, string $source, array $source_meta ): string {
        if ( 'purchase' === $source ) {
            $product_id = isset( $source_meta['product_id'] ) ? (int) $source_meta['product_id'] : 0;
            if ( $product_id > 0 ) {
                $product = get_post( $product_id );
                if ( $product && 'smc_product' === $product->post_type && ! empty( $product->post_title ) ) {
                    return $product->post_title;
                }
            }
        }

        $course = get_post( $course_id );
        return $course && ! empty( $course->post_title ) ? $course->post_title : __( 'Course', 'smc-viable' );
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
