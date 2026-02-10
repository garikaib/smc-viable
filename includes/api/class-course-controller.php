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
	 * Allow public for now, or check if user has access to this course?
	 * Ideally check access.
	 */
	public function permissions_check( $request ) {
        // Validation: Does course exist?
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );
        
        if ( ! $post || 'smc_product' !== $post->post_type ) {
            return new WP_Error( 'rest_course_invalid', 'Invalid course ID.', [ 'status' => 404 ] );
        }

        // Ideally check purchase here.
		return is_user_logged_in();
	}

	/**
	 * Get Course Structure.
	 */
	public function get_structure( $request ) {
		$id = (int) $request->get_param( 'id' );
        $course = get_post( $id );
        
        $sections = get_post_meta( $id, '_course_sections', true ) ?: [];

        // Check Access
        $access = \SMC\Viable\Training_Manager::can_access_course( get_current_user_id(), $id );
        if ( is_wp_error( $access ) ) {
            return $access;
        }
        
        // Hydrate lessons
        $hydrated_sections = [];
        foreach ( $sections as $section ) {
            $section_data = [
                'title' => $section['title'],
                'lessons' => [],
            ];
            
            if ( ! empty( $section['lessons'] ) ) {
                foreach ( $section['lessons'] as $lesson_id ) {
                    $lesson = get_post( $lesson_id );
                    if ( ! $lesson ) continue;
                    
                    $type = get_post_meta( $lesson->ID, '_lesson_type', true );
                    $duration = get_post_meta( $lesson->ID, '_lesson_duration', true );
                    $video_url = get_post_meta( $lesson->ID, '_lesson_video_url', true );
                    
                    // Check completion status (Stub)
                    // In future, join with smc_progress table
                    $is_completed = false; 

                    $section_data['lessons'][] = [
                        'id' => $lesson->ID,
                        'title' => $lesson->post_title,
                        'type' => $type,
                        'duration' => (int) $duration, // minutes
                        'completed' => $is_completed, 
                        'is_locked' => false, 
                        'content' => $lesson->post_content, // Expose full content
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
        ] );
	}
}
