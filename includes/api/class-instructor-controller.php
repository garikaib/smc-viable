<?php
/**
 * REST API Controller for Instructors.
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
 * Class Instructor_Controller
 */
class Instructor_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'instructor';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/instructor/stats
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_stats' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/instructor/students
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/students',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_students' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // GET /smc/v1/instructor/courses
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/courses',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_courses' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
                [
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_course' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // POST /smc/v1/instructor/courses/{id}/structure
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/courses/(?P<id>\d+)/structure',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_structure' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // POST /smc/v1/instructor/lessons
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lessons',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_lesson' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // GET/POST /smc/v1/instructor/lessons/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lessons/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_lesson' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
                [
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_lesson' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'manage_options' ); // For now, only admins can be instructors
	}

	/**
	 * Get Stats.
	 */
	public function get_stats( $request ) {
        // Mock stats for now
		return rest_ensure_response( [
            'total_students' => count( get_users() ),
            'active_courses' => wp_count_posts( 'smc_product' )->publish,
            'completions_today' => 5,
        ] );
	}

	/**
	 * Get Students.
	 */
	public function get_students( $request ) {
        $users = get_users( [ 'role__in' => [ 'subscriber', 'customer' ] ] );
        $student_list = [];

        foreach ( $users as $user ) {
            $student_list[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'progress' => 45, // Placeholder
            ];
        }

		return rest_ensure_response( $student_list );
	}

    /**
     * Get Courses.
     */
    public function get_courses( $request ) {
        $courses = get_posts( [
            'post_type' => 'smc_product', // Assuming smc_product is the course CPT
            'posts_per_page' => -1,
            'post_status' => 'any',
        ] );

        $data = [];
        foreach ( $courses as $course ) {
            $data[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'status' => $course->post_status,
                'students_count' => 0, // Todo: calculate real count
                'lessons_count' => 0, // Todo: calculate from meta
            ];
        }

        return rest_ensure_response( $data );
    }

    /**
     * Create/Update Course.
     */
    public function create_course( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $id = (int) $request->get_param( 'id' );

        $post_data = [
            'post_type' => 'smc_product',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
        ];

        if ( $id ) {
            $post_data['ID'] = $id;
            $post_id = wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return rest_ensure_response( [
            'id' => $post_id,
            'message' => 'Course saved',
        ] );
    }

    /**
     * Update Course Structure.
     */
    public function update_structure( $request ) {
        $id = (int) $request->get_param( 'id' );
        $sections = $request->get_param( 'sections' ); // Array of sections with lesson IDs

        if ( ! is_array( $sections ) ) {
            return new WP_Error( 'invalid_data', 'Sections must be an array', [ 'status' => 400 ] );
        }

        // Sanitize and save
        $sanitized_sections = [];
        foreach ( $sections as $section ) {
            $sanitized_sections[] = [
                'title' => sanitize_text_field( $section['title'] ),
                'lessons' => array_map( 'intval', $section['lessons'] ?? [] ),
            ];
        }

        update_post_meta( $id, '_course_sections', $sanitized_sections );

        return rest_ensure_response( [
            'success' => true,
            'message' => 'Structure updated',
        ] );
    }

    /**
     * Create Lesson.
     */
    public function create_lesson( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $type = sanitize_text_field( $request->get_param( 'type' ) ?: 'video' );

        $post_id = wp_insert_post( [
            'post_type' => 'smc_lesson',
            'post_title' => $title,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_lesson_type', $type );

        return rest_ensure_response( [
            'id' => $post_id,
            'title' => $title,
            'type' => $type,
        ] );
    }

    /**
     * Get Lesson.
     */
    public function get_lesson( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );
        
        if ( ! $post || 'smc_lesson' !== $post->post_type ) {
            return new WP_Error( 'not_found', 'Lesson not found', [ 'status' => 404 ] );
        }

        return rest_ensure_response( [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'type' => get_post_meta( $post->ID, '_lesson_type', true ) ?: 'video',
            'video_url' => get_post_meta( $post->ID, '_lesson_video_url', true ) ?: '',
            'duration' => get_post_meta( $post->ID, '_lesson_duration', true ) ?: 0,
        ] );
    }

    /**
     * Update Lesson.
     */
    public function update_lesson( $request ) {
        $id = (int) $request->get_param( 'id' );
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $type = sanitize_text_field( $request->get_param( 'type' ) );
        $video_url = esc_url_raw( $request->get_param( 'video_url' ) );
        $duration = (int) $request->get_param( 'duration' );

        $post_id = wp_update_post( [
            'ID' => $id,
            'post_title' => $title,
            'post_content' => $content,
        ] );

        update_post_meta( $id, '_lesson_type', $type );
        update_post_meta( $id, '_lesson_video_url', $video_url );
        update_post_meta( $id, '_lesson_duration', $duration );

        return rest_ensure_response( [ 'success' => true ] );
    }
}
