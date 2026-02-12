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
use SMC\Viable\Enrollment_Manager;

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

		// GET/POST /smc/v1/instructor/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/instructor/public-profile/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/public-profile/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_public_profile' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		// GET /smc/v1/instructor/students (Global directory)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/students',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_all_students' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_student' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/instructor/students/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/students/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_student_detail' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_student' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// POST /smc/v1/instructor/students/{id}/status
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/students/(?P<id>\d+)/status',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_student_status' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

        // COURSES ENDPOINTS
        
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

        // GET /smc/v1/instructor/courses-list (Lightweight)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses-list',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_courses_list_light' ],
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

        // POST /smc/v1/instructor/courses/{id}/title
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/title',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'update_course_title' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // ENROLLMENT MANAGEMENT

        // GET /smc/v1/instructor/courses/{id}/enrollments
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/enrollments',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_course_enrollments' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // POST /smc/v1/instructor/courses/{id}/enroll (Manual)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/enroll',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'manual_enroll' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // POST /smc/v1/instructor/courses/{id}/invite (Email)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/invite',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'invite_via_email' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // DELETE /smc/v1/instructor/courses/{id}/enrollments/{user_id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/courses/(?P<id>\d+)/enrollments/(?P<user_id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'unenroll_student' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // LESSONS MANAGEMENT

        // GET /smc/v1/instructor/lessons/search
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/lessons/search',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'search_lessons' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // POST /smc/v1/instructor/lessons (Create Blank)
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

        // POST /smc/v1/instructor/lessons/{id} (Update)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/lessons/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE, // Using POST for updates to avoid PUT issues
                    'callback'            => [ $this, 'update_lesson' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // QUIZ RULES

        // GET /smc/v1/instructor/quiz-enrollment-rules/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/quiz-enrollment-rules/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_quiz_rules' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'save_quiz_rules' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
		return current_user_can( 'edit_posts' ); // Allow instructors/editors
	}

	/**
	 * Return profile for current instructor.
	 */
	public function get_profile( $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in.', [ 'status' => 401 ] );
		}

		$user = get_userdata( $user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return new WP_Error( 'not_found', 'Instructor not found.', [ 'status' => 404 ] );
		}

		$raw = get_user_meta( $user_id, '_smc_instructor_profile', true );
		$data = is_array( $raw ) ? $raw : [];

		return rest_ensure_response( $this->build_profile_payload( $user, $data ) );
	}

	/**
	 * Save profile for current instructor.
	 */
	public function save_profile( $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'rest_forbidden', 'You must be logged in.', [ 'status' => 401 ] );
		}

		$user = get_userdata( $user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return new WP_Error( 'not_found', 'Instructor not found.', [ 'status' => 404 ] );
		}

		$profile = [
			'avatar_id'    => absint( $request->get_param( 'avatar_id' ) ),
			'avatar'       => esc_url_raw( (string) $request->get_param( 'avatar' ) ),
			'intro'        => sanitize_text_field( (string) $request->get_param( 'intro' ) ),
			'bio'          => sanitize_textarea_field( (string) $request->get_param( 'bio' ) ),
			'experience'   => sanitize_textarea_field( (string) $request->get_param( 'experience' ) ),
			'skills'       => $this->sanitize_skills( $request->get_param( 'skills' ) ),
			'social_links' => $this->sanitize_social_links( $request->get_param( 'social_links' ) ),
		];

		update_user_meta( $user_id, '_smc_instructor_profile', $profile );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => 'Instructor profile saved.',
				'profile' => $this->build_profile_payload( $user, $profile ),
			]
		);
	}

	/**
	 * Return public-safe instructor profile payload.
	 */
	public function get_public_profile( $request ) {
		$instructor_id = (int) $request->get_param( 'id' );
		if ( $instructor_id <= 0 ) {
			return new WP_Error( 'invalid_instructor', 'Invalid instructor ID.', [ 'status' => 400 ] );
		}

		$user = get_userdata( $instructor_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return new WP_Error( 'not_found', 'Instructor not found.', [ 'status' => 404 ] );
		}

		$raw = get_user_meta( $instructor_id, '_smc_instructor_profile', true );
		$data = is_array( $raw ) ? $raw : [];

		return rest_ensure_response( $this->build_profile_payload( $user, $data ) );
	}

	/**
	 * Normalize profile payload for frontend consumers.
	 */
	private function build_profile_payload( \WP_User $user, array $profile ): array {
		$defaults = [
			'avatar_id'    => 0,
			'avatar'       => '',
			'intro'        => '',
			'bio'          => '',
			'experience'   => '',
			'skills'       => [],
			'social_links' => [],
		];
		$merged = array_merge( $defaults, $profile );

		return [
			'id'           => (int) $user->ID,
			'name'         => (string) $user->display_name,
			'avatar_id'    => absint( $merged['avatar_id'] ),
			'avatar'       => $this->resolve_avatar_url( $user, $merged ),
			'intro'        => sanitize_text_field( (string) $merged['intro'] ),
			'bio'          => sanitize_textarea_field( (string) $merged['bio'] ),
			'experience'   => sanitize_textarea_field( (string) $merged['experience'] ),
			'skills'       => $this->sanitize_skills( $merged['skills'] ),
			'social_links' => $this->sanitize_social_links( $merged['social_links'] ),
		];
	}

	/**
	 * Resolve custom instructor avatar URL with fallback to WP avatar.
	 *
	 * @param \WP_User $user Instructor user object.
	 * @param array    $profile Sanitized instructor profile.
	 * @return string
	 */
	private function resolve_avatar_url( \WP_User $user, array $profile ): string {
		$avatar_id = absint( $profile['avatar_id'] ?? 0 );
		if ( $avatar_id > 0 ) {
			$attachment_url = wp_get_attachment_image_url( $avatar_id, 'medium' );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				return esc_url_raw( $attachment_url );
			}
		}

		$avatar_url = esc_url_raw( (string) ( $profile['avatar'] ?? '' ) );
		if ( '' !== $avatar_url ) {
			return $avatar_url;
		}

		return get_avatar_url( $user->ID );
	}

	/**
	 * Sanitize skills as a unique non-empty list.
	 *
	 * @param mixed $skills Raw skills payload.
	 * @return array
	 */
	private function sanitize_skills( $skills ): array {
		if ( is_string( $skills ) ) {
			$skills = preg_split( '/[\n,]+/', $skills );
		}
		if ( ! is_array( $skills ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $skills as $skill ) {
			$value = sanitize_text_field( (string) $skill );
			if ( '' === $value ) {
				continue;
			}
			$normalized[] = $value;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Sanitize supported social links.
	 *
	 * @param mixed $links Raw social payload.
	 * @return array
	 */
	private function sanitize_social_links( $links ): array {
		$supported = [ 'website', 'linkedin', 'twitter', 'facebook', 'instagram', 'youtube', 'tiktok' ];
		$source = is_array( $links ) ? $links : [];
		$sanitized = [];

		foreach ( $supported as $key ) {
			$sanitized[ $key ] = isset( $source[ $key ] ) ? esc_url_raw( (string) $source[ $key ] ) : '';
		}

		return $sanitized;
	}

	/**
	 * Get Stats.
	 */
	public function get_stats( $request ) {
		global $wpdb;
		$now_ts         = (int) current_time( 'timestamp' );
		$today          = wp_date( 'Y-m-d', $now_ts );
		$yesterday_ts   = $now_ts - DAY_IN_SECONDS;
		$yesterday      = wp_date( 'Y-m-d', $yesterday_ts );
		$yesterday_end  = wp_date( 'Y-m-d 23:59:59', $yesterday_ts );

		// Return platform-wide stats for the instructor dashboard.
		$active_courses = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'smc_training' AND post_status = 'publish'"
		);
		$active_courses_yesterday = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'smc_training' AND post_status = 'publish' AND post_date <= %s",
				$yesterday_end
			)
		);

		$total_students = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}smc_enrollments WHERE status != 'inactive'"
		);
		$total_students_yesterday = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}smc_enrollments WHERE status != 'inactive' AND enrolled_at <= %s",
				$yesterday_end
			)
		);

		$total_enrollments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}smc_enrollments WHERE status = 'active'"
		);

		$completions_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}smc_enrollments WHERE status = 'completed' AND DATE(completed_at) = %s",
			$today
		) );
		$completions_yesterday = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}smc_enrollments WHERE status = 'completed' AND DATE(completed_at) = %s",
			$yesterday
		) );

		return rest_ensure_response( [
			'total_students'    => $total_students,
			'active_courses'    => $active_courses,
			'total_enrollments' => $total_enrollments,
			'completions_today' => $completions_today,
			'total_orders'      => (int) wp_count_posts( 'smc_order' )->publish,
			'trends'            => [
				'total_students'    => $this->calculate_percentage_change( $total_students, $total_students_yesterday ),
				'active_courses'    => $this->calculate_percentage_change( $active_courses, $active_courses_yesterday ),
				'completions_today' => $this->calculate_percentage_change( $completions_today, $completions_yesterday ),
			],
		] );
	}

	/**
	 * Calculate percentage change from previous to current.
	 *
	 * @param int $current Current value.
	 * @param int $previous Previous value.
	 * @return int
	 */
	private function calculate_percentage_change( int $current, int $previous ): int {
		if ( $previous <= 0 ) {
			return $current > 0 ? 100 : 0;
		}

		return (int) round( ( ( $current - $previous ) / $previous ) * 100 );
	}

	/**
	 * Get All Students.
	 */
	public function get_all_students( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();

		// 1. Get Instructor's Courses
		$course_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'smc_training' AND post_author = %d",
			$user_id
		) );

		$student_ids = [];
		if ( ! empty( $course_ids ) ) {
			$course_ids_str = implode( ',', array_map( 'intval', $course_ids ) );
			$student_ids    = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$wpdb->prefix}smc_enrollments WHERE course_id IN ($course_ids_str)" );
		}

		// 2. Include WordPress users so directory reflects past and present accounts.
		$wp_user_ids = get_users(
			[
				'fields'  => 'ids',
				'number'  => -1,
				'orderby' => 'registered',
				'order'   => 'DESC',
			]
		);
		if ( is_array( $wp_user_ids ) && ! empty( $wp_user_ids ) ) {
			$student_ids = array_merge( $student_ids, array_map( 'intval', $wp_user_ids ) );
		}

		// 3. Include manually managed students added from this instructor dashboard.
		$managed_student_ids = get_users(
			[
				'fields'     => 'ids',
				'meta_query' => [
					[
						'key'   => '_smc_student_manager_added_by',
						'value' => $user_id,
					],
				],
			]
		);

		if ( is_array( $managed_student_ids ) && ! empty( $managed_student_ids ) ) {
			$student_ids = array_unique( array_merge( $student_ids, array_map( 'intval', $managed_student_ids ) ) );
		}

		$student_ids   = array_values( array_unique( array_map( 'intval', $student_ids ) ) );
		$student_list  = [];
		foreach ( $student_ids as $sid ) {
			$user = get_userdata( $sid );
			if ( ! $user ) {
				continue;
			}
			if ( (int) $sid === (int) $user_id ) {
				continue;
			}
			if ( user_can( $sid, 'manage_options' ) || user_can( $sid, 'edit_posts' ) ) {
				continue;
			}
			$student_list[] = $this->build_student_list_item( $user );
		}

		usort(
			$student_list,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return rest_ensure_response( $student_list );
	}

	/**
	 * Build student payload for directory listing.
	 */
	private function build_student_list_item( \WP_User $user ): array {
		global $wpdb;
		$sid = (int) $user->ID;

		$enrollments = $wpdb->get_results( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d", $sid ) );

		$active_count    = 0;
		$completed_count = 0;
		foreach ( $enrollments as $e ) {
			if ( isset( $e->status ) && 'completed' === $e->status ) {
				$completed_count++;
			} elseif ( isset( $e->status ) && 'inactive' !== $e->status ) {
				$active_count++;
			}
		}

		return [
			'id'               => $sid,
			'name'             => $user->display_name,
			'email'            => $user->user_email,
			'avatar'           => get_avatar_url( $sid ),
			'enrollment_count' => $active_count,
			'completed_count'  => $completed_count,
			'plan'             => Enrollment_Manager::resolve_user_plan( $sid ),
			'disabled'         => (bool) get_user_meta( $sid, '_smc_user_disabled', true ),
			'last_active'      => get_user_meta( $sid, '_smc_last_access', true ) ?: $user->user_registered,
		];
	}

	/**
	 * Ensure unique username when creating a student by email.
	 */
	private function build_unique_username( string $email ): string {
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( '' === $base ) {
			$base = 'student';
		}

		$username = $base;
		$index    = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $index;
			$index++;
		}

		return $username;
	}

	/**
	 * Assign student to selected course or plan.
	 */
	private function apply_student_assignment( int $user_id, int $item_id ): void {
		if ( $user_id <= 0 || $item_id <= 0 ) {
			return;
		}

		$item = get_post( $item_id );
		if ( ! $item ) {
			return;
		}

		$is_plan = ( 'smc_product' === $item->post_type && 'plan' === sanitize_key( (string) get_post_meta( $item_id, '_product_type', true ) ) );
		if ( $is_plan ) {
			$plan_level = \SMC\Viable\Plan_Tiers::normalize_or_default( (string) get_post_meta( $item_id, '_plan_level', true ), 'free' );
			update_user_meta( $user_id, '_smc_user_plan', $plan_level );
			return;
		}

		if ( 'smc_training' === $item->post_type ) {
			Enrollment_Manager::enroll_user( $user_id, $item_id, 'manual', [ 'assigned_by' => get_current_user_id() ] );
		}
	}

	/**
	 * Create a student manually and optionally assign a course/plan.
	 */
	public function create_student( $request ) {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$item_id  = (int) $request->get_param( 'item_id' );
		$user_id  = get_current_user_id();

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Please provide a valid email address.', [ 'status' => 400 ] );
		}

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			$target_user_id = (int) $user->ID;
			if ( '' !== $name && $name !== $user->display_name ) {
				wp_update_user(
					[
						'ID'           => $target_user_id,
						'display_name' => $name,
					],
				);
			}
		} else {
			$new_user_id = wp_create_user( $this->build_unique_username( $email ), wp_generate_password( 20, true, true ), $email );

			if ( is_wp_error( $new_user_id ) ) {
				return new WP_Error( 'create_failed', $new_user_id->get_error_message(), [ 'status' => 400 ] );
			}

			$target_user_id = (int) $new_user_id;
			if ( '' !== $name ) {
				wp_update_user(
					[
						'ID'           => $target_user_id,
						'display_name' => $name,
					],
				);
			}
		}

		$target_user = get_userdata( $target_user_id );
		if ( ! ( $target_user instanceof \WP_User ) ) {
			return new WP_Error( 'student_load_failed', 'Unable to load student profile.', [ 'status' => 500 ] );
		}

		if ( ! in_array( 'administrator', (array) $target_user->roles, true ) ) {
			$target_user->set_role( 'subscriber' );
		}

		update_user_meta( $target_user_id, '_smc_student_manager_added_by', $user_id );
		if ( $item_id > 0 ) {
			$this->apply_student_assignment( $target_user_id, $item_id );
		}

		return rest_ensure_response(
			[
				'message' => 'Student saved successfully.',
				'student' => $this->build_student_list_item( $target_user ),
			]
		);
	}

	/**
	 * Update an existing student and optionally assign a course/plan.
	 */
	public function update_student( $request ) {
		$student_id = (int) $request->get_param( 'id' );
		$email      = sanitize_email( (string) $request->get_param( 'email' ) );
		$name       = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$item_id    = (int) $request->get_param( 'item_id' );

		$user = get_userdata( $student_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', 'Student not found.', [ 'status' => 404 ] );
		}

		$update_data = [ 'ID' => $student_id ];
		if ( '' !== $name ) {
			$update_data['display_name'] = $name;
		}

		if ( '' !== $email ) {
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', 'Please provide a valid email address.', [ 'status' => 400 ] );
			}

			$existing = get_user_by( 'email', $email );
			if ( $existing instanceof \WP_User && (int) $existing->ID !== $student_id ) {
				return new WP_Error( 'email_exists', 'That email already belongs to another account.', [ 'status' => 409 ] );
			}

			$update_data['user_email'] = $email;
		}

		$updated = wp_update_user( $update_data );
		if ( is_wp_error( $updated ) ) {
			return new WP_Error( 'update_failed', $updated->get_error_message(), [ 'status' => 400 ] );
		}

		if ( $item_id > 0 ) {
			$this->apply_student_assignment( $student_id, $item_id );
		}

		$updated_user = get_userdata( $student_id );
		if ( ! ( $updated_user instanceof \WP_User ) ) {
			return new WP_Error( 'student_load_failed', 'Unable to load updated student profile.', [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'message' => 'Student updated successfully.',
				'student' => $this->build_student_list_item( $updated_user ),
			]
		);
	}

	/**
	 * Update student account status.
	 */
	public function update_student_status( $request ) {
		$student_id = (int) $request->get_param( 'id' );
		$disabled   = rest_sanitize_boolean( $request->get_param( 'disabled' ) );
		$actor_id   = get_current_user_id();

		if ( $student_id <= 0 ) {
			return new WP_Error( 'invalid_student', 'Invalid student ID.', [ 'status' => 400 ] );
		}

		if ( $student_id === $actor_id ) {
			return new WP_Error( 'forbidden', 'You cannot disable your own account.', [ 'status' => 403 ] );
		}

		if ( user_can( $student_id, 'manage_options' ) || user_can( $student_id, 'edit_posts' ) ) {
			return new WP_Error( 'forbidden', 'This account cannot be managed from Student Management.', [ 'status' => 403 ] );
		}

		update_user_meta( $student_id, '_smc_user_disabled', $disabled ? '1' : '0' );
		if ( $disabled ) {
			update_user_meta( $student_id, '_smc_user_disabled_at', current_time( 'mysql' ) );
			update_user_meta( $student_id, '_smc_user_disabled_by', $actor_id );
			if ( function_exists( 'wp_destroy_user_sessions' ) ) {
				wp_destroy_user_sessions( $student_id );
			}
		} else {
			delete_user_meta( $student_id, '_smc_user_disabled_at' );
			delete_user_meta( $student_id, '_smc_user_disabled_by' );
		}

		$user = get_userdata( $student_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return new WP_Error( 'not_found', 'Student not found.', [ 'status' => 404 ] );
		}

		return rest_ensure_response(
			[
				'message' => $disabled ? 'Student disabled.' : 'Student enabled.',
				'student' => $this->build_student_list_item( $user ),
			]
		);
	}

    /**
     * Get Single Student Detail.
     */
    public function get_student_detail( $request ) {
        $user_id = (int) $request->get_param( 'id' );
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return new WP_Error( 'not_found', 'Student not found', [ 'status' => 404 ] );
        }

        // 1. Basic Info
        $data = [
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_avatar_url( $user->ID ),
            'registered' => date( 'M d, Y', strtotime( $user->user_registered ) ),
            'plan' => Enrollment_Manager::resolve_user_plan( (int) $user->ID ),
            'disabled' => (bool) get_user_meta( $user->ID, '_smc_user_disabled', true ),
        ];

        // 2. Enrollments & Progress
        $enroll_records = Enrollment_Manager::get_user_enrollments( $user_id );
        $enrollments = [];
        foreach ( $enroll_records as $rec ) {
            $course = get_post( $rec->course_id );
            if ( ! $course ) continue;

            $progress = \SMC\Viable\LMS_Progress::get_full_course_progress( $user_id, (int) $rec->course_id );
            
            $enrollments[] = [
                'course_id' => $course->ID,
                'title' => $course->post_title,
                'status' => $rec->status,
                'progress' => $progress['overall_percent'],
                'enrolled_at' => date( 'M d, Y', strtotime( $rec->enrolled_at ) ),
                'last_accessed' => get_post_meta( $course->ID, "_last_access_{$user_id}", true ) ?: 'Never',
            ];
        }
        $data['enrollments'] = $enrollments;

        // 3. Quiz Results
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'smc_quiz_submissions';
        $submissions = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, quiz_id, score, created_at FROM $quiz_table WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        $quizzes = [];
        foreach ( $submissions as $s ) {
            $quiz = get_post( $s->quiz_id );
            $quizzes[] = [
                'id' => $s->id,
                'title' => $quiz ? $quiz->post_title : 'Unknown Quiz',
                'score' => (int) $s->score,
                'date' => date( 'M d, Y', strtotime( $s->created_at ) ),
            ];
        }
        $data['quizzes'] = $quizzes;

        // 4. Identity Score (If available)
        $identity = get_user_meta( $user_id, '_smc_business_identity', true );
        $data['business_identity'] = $identity ?: null;

        return rest_ensure_response( $data );
    }

    /**
     * Get Courses.
     */
    public function get_courses( $request ) {
        $this->sync_shop_products_to_courses();

        $courses = get_posts( [
            'post_type'      => 'smc_training',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $data = [];
        foreach ( $courses as $course ) {
            $data[] = $this->prepare_course_payload( $course );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get Light Course & Plan List (for dropdowns).
     */
    public function get_courses_list_light( $request ) {
        $this->sync_shop_products_to_courses();

        // Include plan products.
        $products = get_posts( [
            'post_type'      => 'smc_product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => '_product_type',
                    'value' => 'plan',
                ],
            ],
        ] );

        $data = [];
        foreach ( $products as $product ) {
            $data[] = [ 'id' => $product->ID, 'title' => $product->post_title . ' (Plan)' ];
        }

        // Include all courses, regardless of creator.
        $courses = get_posts( [
            'post_type'      => 'smc_training',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        foreach ( $courses as $course ) {
            $data[] = [ 'id' => $course->ID, 'title' => $course->post_title . ' (Course)' ];
        }

        return rest_ensure_response( $data );
    }

    /**
     * Ensure shop course/service products have a linked smc_training post.
     */
    private function sync_shop_products_to_courses(): void {
        $products = get_posts( [
            'post_type'      => 'smc_product',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'meta_query'     => [
                [
                    'key'     => '_product_type',
                    'value'   => [ 'course', 'service', 'single' ], // Added 'single' to match seeder
                    'compare' => 'IN',
                ],
            ],
        ] );

        foreach ( $products as $product ) {
            $this->ensure_training_course_for_product( $product );
        }
    }

    /**
     * Ensure a single training record exists for a product and return its ID.
     */
    private function ensure_training_course_for_product( \WP_Post $product ): int {
        $training_id = (int) get_post_meta( $product->ID, '_linked_training_id', true );
        if ( ! $training_id ) {
            $training_id = (int) get_post_meta( $product->ID, '_linked_course_id', true );
        }

        if ( $training_id ) {
            $existing = get_post( $training_id );
            if ( $existing && 'smc_training' === $existing->post_type ) {
                if ( (int) get_post_meta( $training_id, '_linked_product_id', true ) !== (int) $product->ID ) {
                    update_post_meta( $training_id, '_linked_product_id', (int) $product->ID );
                }
                return (int) $training_id;
            }
        }

        $new_course_id = wp_insert_post( [
            'post_type'    => 'smc_training',
            'post_title'   => $product->post_title,
            'post_content' => $product->post_content,
            'post_status'  => in_array( $product->post_status, [ 'publish', 'draft', 'pending', 'private' ], true ) ? $product->post_status : 'draft',
            'post_author'  => (int) $product->post_author ?: get_current_user_id(),
        ] );

        if ( is_wp_error( $new_course_id ) || ! $new_course_id ) {
            return 0;
        }

        update_post_meta( $new_course_id, '_linked_product_id', (int) $product->ID );
        update_post_meta( $new_course_id, '_access_type', 'standalone' );
        update_post_meta( $new_course_id, '_plan_level', get_post_meta( $product->ID, '_plan_level', true ) ?: 'free' );
        update_post_meta( $product->ID, '_linked_training_id', (int) $new_course_id );
        update_post_meta( $product->ID, '_linked_course_id', (int) $new_course_id );

        return (int) $new_course_id;
    }

    /**
     * Normalize course response for UI.
     */
    private function prepare_course_payload( \WP_Post $course ): array {
        $sections = get_post_meta( $course->ID, '_course_sections', true ) ?: [];
        $lesson_count = 0;
        if ( is_array( $sections ) ) {
            foreach ( $sections as $section ) {
                $lesson_count += count( $section['lessons'] ?? [] );
            }
        }

        $students = Enrollment_Manager::get_course_students( $course->ID );
        $author = get_userdata( (int) $course->post_author );

        $product_id = (int) get_post_meta( $course->ID, '_linked_product_id', true );
        $product_info = null;
        if ( $product_id ) {
            $product = get_post( $product_id );
            if ( $product ) {
                $formatted_price = get_post_meta( $product->ID, '_formatted_price', true );
                if ( ! $formatted_price && function_exists( 'wc_price' ) ) {
                    $formatted_price = wc_price( get_post_meta( $product->ID, '_price', true ) );
                } elseif ( ! $formatted_price ) {
                    $formatted_price = '$' . get_post_meta( $product->ID, '_price', true );
                }

                $product_info = [
                    'id'     => $product->ID,
                    'title'  => $product->post_title,
                    'price'  => get_post_meta( $product->ID, '_price', true ),
                    'formatted_price' => $formatted_price,
                    'status' => $product->post_status,
                    'type'   => get_post_meta( $product->ID, '_product_type', true ),
                ];
            }
        }

        return [
            'id'            => $course->ID,
            'title'         => $course->post_title,
            'slug'          => $course->post_name,
            'preview_url'   => home_url( '/learning/' . $course->post_name . '/' ),
            'status'        => $course->post_status,
            'students_count'=> count( $students ),
            'lessons_count' => $lesson_count,
            'access_type'   => get_post_meta( $course->ID, '_access_type', true ) ?: 'standalone',
            'plan_level'    => get_post_meta( $course->ID, '_plan_level', true ) ?: 'free',
            'image'         => get_the_post_thumbnail_url( $course->ID, 'medium' ),
            'creator_id'    => (int) $course->post_author,
            'creator_name'  => $author ? $author->display_name : '',
            'linked_product'=> $product_info,
        ];
    }

    /**
     * Create/Update Course.
     */
    public function create_course( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $id = (int) $request->get_param( 'id' );
        $access_type = $request->get_param( 'access_type' );
        $plan_level = \SMC\Viable\Plan_Tiers::normalize_or_default( (string) $request->get_param( 'plan_level' ), 'free' );
        $product_id_param = (int) $request->get_param( 'product_id' );
        $status = $request->get_param( 'status' ) ?: 'draft';

        $post_data = [
            'post_type' => 'smc_training',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => $status,
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

        update_post_meta( $post_id, '_access_type', sanitize_text_field( $access_type ) );
        update_post_meta( $post_id, '_plan_level', $plan_level );

        // Sync modern access config (Gutenberg-friendly).
        $modes = [];
        if ( 'plan' === $access_type ) {
            $modes[] = 'plan';
            $allowed_plans = [ \SMC\Viable\Plan_Tiers::normalize_or_default( (string) $plan_level, 'free' ) ];
            update_post_meta( $post_id, '_smc_allowed_plans', $allowed_plans );
            wp_set_post_terms( $post_id, $allowed_plans, 'smc_plan_access', false );
        } else {
            // Legacy builder exposes only one mode. Default to standalone.
            $modes[] = 'standalone';
            update_post_meta( $post_id, '_smc_allowed_plans', [] );
        }
        update_post_meta( $post_id, '_smc_access_modes', $modes );
        wp_set_post_terms( $post_id, $modes, 'smc_access_mode', false );

        // Link with Product if standalone or already linked (even if plan)
        $product_id = (int) get_post_meta( $post_id, '_linked_product_id', true );
        $price = $request->get_param( 'price' );

        if ( 'standalone' === $access_type || $product_id || $product_id_param ) {
             $product_data = [
                 'post_title'   => $title,
                 'post_content' => $description,
                 'post_status'  => $status, // Sync status with course
                 'post_type'    => 'smc_product',
             ];

             if ( $product_id ) {
                 $product_data['ID'] = $product_id;
                 wp_update_post( $product_data );
             } elseif ( $product_id_param ) {
                 // Link to existing product explicitly passed
                 $product_id = $product_id_param;
                 update_post_meta( $post_id, '_linked_product_id', $product_id );
                 update_post_meta( $product_id, '_linked_training_id', (int) $post_id );
                 update_post_meta( $product_id, '_linked_course_id', (int) $post_id );
                 
                 $product_data['ID'] = $product_id;
                 wp_update_post( $product_data );
             } elseif ( 'standalone' === $access_type ) {
                 // Create new standalone product
                 $product_id = wp_insert_post( $product_data );
                 update_post_meta( $product_id, '_linked_training_id', (int) $post_id );
                 update_post_meta( $post_id, '_linked_product_id', (int) $product_id );
             }

             if ( $product_id ) {
                 // Course-linked products are always 'single' type, never 'plan'.
                 // 'plan' type is reserved for actual membership tier products.
                 update_post_meta( $product_id, '_product_type', 'single' );

                 if ( null !== $price ) {
                     update_post_meta( $product_id, '_price', $price );
                 }
             }
        }
        
        // Handle thumbnail if ID passed
        $thumbnail_id = (int) $request->get_param( 'thumbnail_id' );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $post_id, $thumbnail_id );
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
        $sections = $request->get_param( 'sections' );

        if ( ! is_array( $sections ) ) {
            return new WP_Error( 'invalid_data', 'Sections must be an array', [ 'status' => 400 ] );
        }

        // Sanitize
        $sanitized = [];
        foreach ( $sections as $section ) {
            $sanitized[] = [
                'title' => sanitize_text_field( $section['title'] ),
                'lessons' => array_map( 'intval', $section['lessons'] ?? [] ),
            ];
        }

        update_post_meta( $id, '_course_sections', $sanitized );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Update Course Title Only.
     */
    public function update_course_title( $request ) {
        $id = (int) $request->get_param( 'id' );
        $title = sanitize_text_field( (string) $request->get_param( 'title' ) );

        if ( $id <= 0 ) {
            return new WP_Error( 'invalid_course', 'Invalid course ID.', [ 'status' => 400 ] );
        }

        $course = get_post( $id );
        if ( ! $course || 'smc_training' !== $course->post_type ) {
            return new WP_Error( 'course_not_found', 'Course not found.', [ 'status' => 404 ] );
        }

        if ( '' === $title ) {
            return new WP_Error( 'invalid_title', 'Course title is required.', [ 'status' => 400 ] );
        }

        $result = wp_update_post(
            [
                'ID'         => $id,
                'post_title' => $title,
            ],
            true
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Keep linked commerce record in sync so shop/product labels stay aligned.
        $linked_product_id = (int) get_post_meta( $id, '_linked_product_id', true );
        if ( $linked_product_id > 0 ) {
            wp_update_post(
                [
                    'ID'         => $linked_product_id,
                    'post_title' => $title,
                ]
            );
        } else {
            // Fallback for older records linked only from the product side.
            $linked_products = get_posts(
                [
                    'post_type'      => 'smc_product',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        'relation' => 'OR',
                        [
                            'key'   => '_linked_training_id',
                            'value' => $id,
                        ],
                        [
                            'key'   => '_linked_course_id',
                            'value' => $id,
                        ],
                    ],
                ]
            );

            if ( is_array( $linked_products ) ) {
                foreach ( $linked_products as $product_id ) {
                    wp_update_post(
                        [
                            'ID'         => (int) $product_id,
                            'post_title' => $title,
                        ]
                    );
                }
            }
        }

        return rest_ensure_response(
            [
                'id'      => $id,
                'title'   => $title,
                'message' => 'Course title updated.',
            ]
        );
    }

    /**
     * Get Course Enrollments.
     */
    public function get_course_enrollments( $request ) {
        $course_id = (int) $request->get_param( 'id' );
        $students = Enrollment_Manager::get_course_students( $course_id );
        return rest_ensure_response( $students );
    }

    /**
     * Manual Enroll.
     */
    public function manual_enroll( $request ) {
        $course_id = (int) $request->get_param( 'id' );
        $email = sanitize_email( $request->get_param( 'email' ) );

        if ( ! is_email( $email ) ) {
             return new WP_Error( 'invalid_email', 'Invalid email address', [ 'status' => 400 ] );
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
             // Create? Or error? For manual enroll, assume user exists or use invite.
             // Let's try to create if not exists, similar to invite logic but without email sending?
             // Actually, reuse invite logic for simplicity but maybe with default message.
             return $this->invite_via_email( $request );
        }

        $result = Enrollment_Manager::enroll_user( $user->ID, $course_id, 'manual' );
        
        if ( $result ) {
            return rest_ensure_response( [ 'success' => true ] );
        } else {
            return new WP_Error( 'enroll_failed', 'Enrollment failed', [ 'status' => 500 ] );
        }
    }

    /**
     * Invite via Email.
     */
    public function invite_via_email( $request ) {
        $course_id = (int) $request->get_param( 'id' );
        $emails = $request->get_param( 'emails' ); // Array
        $message = sanitize_textarea_field( $request->get_param( 'message' ) );

        if ( ! is_array( $emails ) ) {
            $emails = [ $request->get_param( 'email' ) ];
        }

        $results = Enrollment_Manager::invite_by_email( $emails, $course_id, $message );

        return rest_ensure_response( [ 'results' => $results ] );
    }

    /**
     * Unenroll Student.
     */
    public function unenroll_student( $request ) {
        $course_id = (int) $request->get_param( 'id' );
        $user_id = (int) $request->get_param( 'user_id' );

        $success = Enrollment_Manager::unenroll_user( $user_id, $course_id );
        
        return rest_ensure_response( [ 'success' => $success ] );
    }

    /**
     * Search Lessons.
     */
    public function search_lessons( $request ) {
        $q = sanitize_text_field( $request->get_param( 'q' ) );
        
        $args = [
            'post_type' => 'smc_lesson',
            'posts_per_page' => 20,
            's' => $q,
            'post_status' => 'publish',
        ];
        
        $lessons = get_posts( $args );
        $data = array_map( function( $l ) {
            $embed_settings = get_post_meta( $l->ID, '_lesson_embed_settings', true );
            if ( ! is_array( $embed_settings ) ) {
                $embed_settings = [
                    'autoplay' => false,
                    'loop'     => false,
                    'muted'    => false,
                    'controls' => true,
                ];
            }

            return [
                'id' => $l->ID,
                'title' => $l->post_title,
                'type' => get_post_meta( $l->ID, '_lesson_type', true ) ?: 'text',
                'duration' => get_post_meta( $l->ID, '_lesson_duration', true ),
                'video_url' => get_post_meta( $l->ID, '_lesson_video_url', true ) ?: '',
                'video_caption' => get_post_meta( $l->ID, '_lesson_video_caption', true ) ?: '',
                'embed_settings' => [
                    'autoplay' => (bool) ( $embed_settings['autoplay'] ?? false ),
                    'loop'     => (bool) ( $embed_settings['loop'] ?? false ),
                    'muted'    => (bool) ( $embed_settings['muted'] ?? false ),
                    'controls' => (bool) ( $embed_settings['controls'] ?? true ),
                ],
            ];
        }, $lessons );

        return rest_ensure_response( $data );
    }

    /**
     * Create Lesson (Blank).
     */
    public function create_lesson( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        if ( empty( $title ) ) $title = 'New Lesson';

        $type = sanitize_text_field( $request->get_param( 'type' ) ) ?: 'text';
        $video_url = esc_url_raw( $request->get_param( 'video_url' ) );
        $duration = (int) $request->get_param( 'duration' );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $video_caption = sanitize_text_field( (string) $request->get_param( 'video_caption' ) );
        $embed_settings = $this->sanitize_lesson_embed_settings( $request->get_param( 'embed_settings' ) );

        $post_id = wp_insert_post( [
            'post_type' => 'smc_lesson',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) return $post_id;
        
        update_post_meta( $post_id, '_lesson_type', $type );
        if ( $video_url ) update_post_meta( $post_id, '_lesson_video_url', $video_url );
        if ( $duration ) update_post_meta( $post_id, '_lesson_duration', $duration );
        if ( '' !== $video_caption ) {
            update_post_meta( $post_id, '_lesson_video_caption', $video_caption );
        }
        update_post_meta( $post_id, '_lesson_embed_settings', $embed_settings );

        // Return edit link
        $edit_link = get_edit_post_link( $post_id, 'raw' );

        return rest_ensure_response( [
            'id' => $post_id,
            'title' => $title,
            'edit_url' => $edit_link,
            'type' => $type,
            'video_url' => $video_url,
            'duration' => $duration,
            'video_caption' => $video_caption,
            'embed_settings' => $embed_settings,
        ] );
    }

    /**
     * Update Lesson.
     */
    public function update_lesson( $request ) {
        $id = (int) $request->get_param( 'id' );
        $lesson = get_post( $id );
        
        if ( ! $lesson || 'smc_lesson' !== $lesson->post_type ) {
            return new WP_Error( 'not_found', 'Lesson not found', [ 'status' => 404 ] );
        }

        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $content = $request->get_param( 'content' ); // w_kses_post intentionally omitted if trusted user, but strict is safer.
        // Let's use wp_kses_post for safety even for instructors
        if ( null !== $content ) {
            $content = wp_kses_post( $content );
        }

        $post_data = [ 'ID' => $id ];
        if ( $title ) $post_data['post_title'] = $title;
        if ( null !== $content ) $post_data['post_content'] = $content;

        if ( count( $post_data ) > 1 ) {
            wp_update_post( $post_data );
        }

        if ( null !== $request->get_param( 'type' ) ) {
            update_post_meta( $id, '_lesson_type', sanitize_text_field( $request->get_param( 'type' ) ) );
        }
        if ( null !== $request->get_param( 'video_url' ) ) {
            update_post_meta( $id, '_lesson_video_url', esc_url_raw( $request->get_param( 'video_url' ) ) );
        }
        if ( null !== $request->get_param( 'video_caption' ) ) {
            update_post_meta( $id, '_lesson_video_caption', sanitize_text_field( (string) $request->get_param( 'video_caption' ) ) );
        }
        if ( null !== $request->get_param( 'duration' ) ) {
            update_post_meta( $id, '_lesson_duration', (int) $request->get_param( 'duration' ) );
        }
        if ( null !== $request->get_param( 'embed_settings' ) ) {
            update_post_meta( $id, '_lesson_embed_settings', $this->sanitize_lesson_embed_settings( $request->get_param( 'embed_settings' ) ) );
        }

        return rest_ensure_response( [
            'id' => $id,
            'message' => 'Lesson updated',
        ] );
    }

    /**
     * Sanitize lesson embed settings.
     *
     * @param mixed $settings Raw settings.
     * @return array
     */
    private function sanitize_lesson_embed_settings( $settings ): array {
        $defaults = [
            'autoplay' => false,
            'loop'     => false,
            'muted'    => false,
            'controls' => true,
        ];

        if ( ! is_array( $settings ) ) {
            return $defaults;
        }

        return [
            'autoplay' => rest_sanitize_boolean( $settings['autoplay'] ?? false ),
            'loop'     => rest_sanitize_boolean( $settings['loop'] ?? false ),
            'muted'    => rest_sanitize_boolean( $settings['muted'] ?? false ),
            'controls' => rest_sanitize_boolean( $settings['controls'] ?? true ),
        ];
    }

    /**
     * Get Quiz Rules.
     */
    public function get_quiz_rules( $request ) {
        $quiz_id = (int) $request->get_param( 'id' );
        $rules = get_post_meta( $quiz_id, '_smc_quiz_enrollment_rules', true );
        return rest_ensure_response( $rules ?: [] );
    }

    /**
     * Save Quiz Rules.
     */
    public function save_quiz_rules( $request ) {
        $quiz_id = (int) $request->get_param( 'id' );
        $rules = $request->get_param( 'rules' );
        
        // Basic validation could happen here
        
        update_post_meta( $quiz_id, '_smc_quiz_enrollment_rules', $rules );
        return rest_ensure_response( [ 'success' => true ] );
    }
}
