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

        // POST /smc/v1/student/progress/complete
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/progress/complete',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'complete_lesson' ],
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
        
        // Get Enrolled Courses (Based on Purchase History / Access)
        // For phase 1, we simulate based on 'plan' or find orders.
        // Let's rely on orders to find products of type 'course' or 'plan'.
        
        // Logic: Find completed orders for this user
        $args = [
            'post_type'  => 'smc_order',
            'meta_query' => [
                [
                    'key'   => '_customer_id',
                    'value' => $user_id,
                ],
                [
                    'key'   => '_order_status',
                    'value' => 'completed',
                ]
            ],
            'posts_per_page' => -1,
        ];
        
        $orders = get_posts( $args );
        $product_ids = [];
        
        foreach ( $orders as $order ) {
            $items = get_post_meta( $order->ID, '_order_items', true );
            if ( is_array( $items ) ) {
                foreach ( $items as $item ) {
                    $product_ids[] = $item['product_id'];
                }
            }
        }
        
        $product_ids = array_unique( $product_ids );
        
        // Fetch Course Details
        $enrolled_courses = [];
        foreach ( $product_ids as $pid ) {
            $course = get_post( $pid );
            if ( ! $course ) continue;
            
            // Calculate Progress (Stub for now)
            $progress = 0; 
            
            $enrolled_courses[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'slug' => $course->post_name,
                'thumbnail' => get_the_post_thumbnail_url( $course->ID, 'medium' ),
                'progress' => $progress,
                'status' => 'active', // TODO: Logic
            ];
        }

		return rest_ensure_response( [
            'courses' => $enrolled_courses,
            'recent_activity' => [], // Future
        ] );
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
        
        // Use LMS_Progress class (ensure it's loaded/aliased or use FQN)
        $result = \SMC\Viable\LMS_Progress::complete_lesson( $user_id, $lesson_id, $course_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( [
            'success' => true,
            'message' => 'Lesson completed',
            'next_lesson' => null, // Logic to find next could go here
        ] );
    }
}
