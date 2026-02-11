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
	 * Get Stats.
	 */
	public function get_stats( $request ) {
		return rest_ensure_response( [
            'total_students' => count( get_users( [ 'role__in' => [ 'subscriber', 'customer' ] ] ) ),
            'active_courses' => wp_count_posts( 'smc_training' )->publish,
            'total_enrollments' => (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}smc_enrollments WHERE status = 'active'" ),
        ] );
	}

	/**
	 * Get All Students.
	 */
	public function get_all_students( $request ) {
        $users = get_users( [ 'role__in' => [ 'subscriber', 'customer' ] ] );
        $student_list = [];

        foreach ( $users as $user ) {
            $enrollments = Enrollment_Manager::get_user_enrollments( $user->ID );
            $active_count = 0;
            $completed_count = 0;
            foreach ( $enrollments as $e ) {
                if ( $e->status === 'completed' ) $completed_count++;
                else $active_count++;
            }

            $student_list[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url( $user->ID ),
                'enrollment_count' => $active_count,
                'completed_count' => $completed_count,
                'last_active' => get_user_meta( $user->ID, '_smc_last_access', true ) ?: $user->user_registered,
            ];
        }

		return rest_ensure_response( $student_list );
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
        $courses = get_posts( [
            'post_type' => 'smc_training',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ] );

        $data = [];
        foreach ( $courses as $course ) {
            $sections = get_post_meta( $course->ID, '_course_sections', true ) ?: [];
            $lesson_count = 0;
            if ( is_array( $sections ) ) {
                foreach ( $sections as $s ) {
                    $lesson_count += count( $s['lessons'] ?? [] );
                }
            }

            $students = Enrollment_Manager::get_course_students( $course->ID );

            // Get Linked Product Info
            $product_id = get_post_meta( $course->ID, '_linked_product_id', true );
            $product_info = null;
            if ( $product_id ) {
                $product = get_post( $product_id );
                if ( $product ) {
                    $product_info = [
                        'id' => $product->ID,
                        'title' => $product->post_title,
                        'price' => get_post_meta( $product->ID, '_price', true ),
                        'status' => $product->post_status,
                    ];
                }
            }

            $data[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'status' => $course->post_status,
                'students_count' => count( $students ),
                'lessons_count' => $lesson_count,
                'access_type' => get_post_meta( $course->ID, '_access_type', true ) ?: 'standalone',
                'plan_level' => get_post_meta( $course->ID, '_plan_level', true ) ?: 'free',
                'image' => get_the_post_thumbnail_url( $course->ID, 'medium' ),
                'linked_product' => $product_info,
            ];
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get Light Course List (for dropdowns).
     */
    public function get_courses_list_light( $request ) {
        $courses = get_posts( [
            'post_type' => 'smc_training',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ] );

        $data = array_map( function( $c ) {
            return [ 'id' => $c->ID, 'title' => $c->post_title ];
        }, $courses );

        return rest_ensure_response( $data );
    }

    /**
     * Create/Update Course.
     */
    public function create_course( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $description = wp_kses_post( $request->get_param( 'description' ) );
        $id = (int) $request->get_param( 'id' );
        $access_type = $request->get_param( 'access_type' );
        $plan_level = $request->get_param( 'plan_level' );
        $status = $request->get_param( 'status' ) ?: 'draft';

        $post_data = [
            'post_type' => 'smc_training',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => $status,
        ];

        if ( $id ) {
            $post_id = wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }
        
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_access_type', sanitize_text_field( $access_type ) );
        update_post_meta( $post_id, '_plan_level', sanitize_text_field( $plan_level ) );

        // Link with Product if standalone or explicitly requested
        if ( 'standalone' === $access_type ) {
             $product_id = get_post_meta( $post_id, '_linked_product_id', true );
             if ( ! $product_id ) {
                 // Create Product
                 $product_id = wp_insert_post( [
                     'post_title'   => $title,
                     'post_type'    => 'smc_product',
                     'post_content' => $description,
                     'post_status'  => 'publish',
                 ] );
                 update_post_meta( $product_id, '_product_type', 'course' );
                 update_post_meta( $product_id, '_price', 0 ); // Default to free or user can edit in admin
                 update_post_meta( $product_id, '_linked_training_id', (int) $post_id );
                 update_post_meta( $post_id, '_linked_product_id', (int) $product_id );
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
            return [
                'id' => $l->ID,
                'title' => $l->post_title,
                'type' => get_post_meta( $l->ID, '_lesson_type', true ) ?: 'text',
                'duration' => get_post_meta( $l->ID, '_lesson_duration', true ),
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

        $post_id = wp_insert_post( [
            'post_type' => 'smc_lesson',
            'post_title' => $title,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) return $post_id;
        
        // Return edit link
        $edit_link = get_edit_post_link( $post_id, 'raw' );

        return rest_ensure_response( [
            'id' => $post_id,
            'title' => $title,
            'edit_url' => $edit_link,
            'type' => 'text' // Default
        ] );
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
