<?php
/**
 * REST API Controller for Quizzes.
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
 * Class Quiz_Controller
 */
class Quiz_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'quizzes';
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
				],
			]
		);

        register_rest_route(
            $this->namespace,
            '/seed',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'seed_quizzes' ],
                'permission_callback' => [ $this, 'create_item_permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/submit-email',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'submit_email_report' ],
                // Public endpoint, but we handle logic internally
                'permission_callback' => '__return_true', 
            ]
        );

        register_rest_route(
            $this->namespace,
            '/leads',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_leads' ],
                'permission_callback' => [ $this, 'get_items_permissions_check' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/leads/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_lead' ],
                'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                'args'                => [
                    'id' => [
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        }
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'id' => [
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            }
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE, // POST, PUT, PATCH
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                    'args'                => [
                        'id' => [
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            }
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                    'args'                => [
                        'id' => [
                            'validate_callback' => function( $param ) {
                                return is_numeric( $param );
                            }
                        ],
                    ],
                ],
            ]
        );
	}

    /**
     * Get all leads.
     */
    public function get_leads( $request ) {
        $args = [
            'post_type'      => 'smc_lead',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        $posts = get_posts( $args );

        $data = [];
        foreach ( $posts as $post ) {
            $name = get_post_meta( $post->ID, '_smc_lead_name', true );
            if ( empty( $name ) ) {
                $title_parts = explode( ' - ', $post->post_title, 2 );
                $name = trim( $title_parts[0] );
            }
            
            $data[] = [
                'id'      => $post->ID,
                'name'    => $name,
                'date'    => $post->post_date,
                'email'   => get_post_meta( $post->ID, '_smc_lead_email', true ) ?: '',
                'phone'   => get_post_meta( $post->ID, '_smc_lead_phone', true ) ?: '',
                'quiz_id' => get_post_meta( $post->ID, '_smc_lead_quiz_id', true ) ?: 0,
            ];
        }

        return rest_ensure_response( $data );
    }

    /**
     * Delete a single lead.
     */
    public function delete_lead( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'smc_lead' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Lead not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $result = wp_delete_post( $id, true );

        if ( ! $result ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete lead.', 'smc-viable' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }

	/**
	 * Check if a given request has access to get items.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Get a collection of items.
	 */
	public function get_items( $request ) {
		$args = [
			'post_type'      => 'smc_quiz',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];
		$posts = get_posts( $args );

		$data = [];
		foreach ( $posts as $post ) {
			$data[] = $this->prepare_item_for_response( $post, $request );
		}

		return rest_ensure_response( $data );
	}

    /**
     * Prepare a single quiz for response.
     */
    public function prepare_item_for_response( $post, $request ) {
        return [
            'id'    => $post->ID,
            'title' => [
                'rendered' => $post->post_title,
            ],
            'date'  => $post->post_date,
            'meta'  => [
                '_smc_quiz_questions' => get_post_meta( $post->ID, '_smc_quiz_questions', true ) ?: [],
                '_smc_quiz_settings'  => get_post_meta( $post->ID, '_smc_quiz_settings', true ) ?: [],
                '_smc_quiz_dashboard_config' => get_post_meta( $post->ID, '_smc_quiz_dashboard_config', true ) ?: [],
                '_smc_quiz_stages' => get_post_meta( $post->ID, '_smc_quiz_stages', true ) ?: [],
                '_smc_quiz_plan_level' => get_post_meta( $post->ID, '_smc_quiz_plan_level', true ) ?: 'free',
            ],
        ];
    }

    /**
     * Get a single quiz.
     */
    public function get_item( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'smc_quiz' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Quiz not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        return rest_ensure_response( $this->prepare_item_for_response( $post, $request ) );
    }

	/**
	 * Check if a given request has access to create items.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Create one item from the collection.
	 */
	public function create_item( $request ) {
		$title     = sanitize_text_field( $request->get_param( 'title' ) );
		$questions = $request->get_param( 'questions' );

		if ( empty( $title ) ) {
			return new WP_Error( 'missing_title', __( 'Quiz title is required.', 'smc-viable' ), [ 'status' => 400 ] );
		}

		$post_id = wp_insert_post( [
			'post_title'  => $title,
			'post_type'   => 'smc_quiz',
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'create_failed', __( 'Failed to create quiz.', 'smc-viable' ), [ 'status' => 500 ] );
		}

		if ( ! empty( $questions ) && is_array( $questions ) ) {
			update_post_meta( $post_id, '_smc_quiz_questions', $questions );
		}

        $settings = $request->get_param( 'settings' );
        if ( isset( $settings ) ) {
            update_post_meta( $post_id, '_smc_quiz_settings', $settings );
        }

        $dashboard_config = $request->get_param( 'dashboard_config' );
        if ( isset( $dashboard_config ) ) {
            update_post_meta( $post_id, '_smc_quiz_dashboard_config', $dashboard_config );
        }
        
        $stages = $request->get_param( 'stages' );
        if ( isset( $stages ) ) {
            update_post_meta( $post_id, '_smc_quiz_stages', $stages );
        }

        $plan_level = $request->get_param( 'plan_level' );
        if ( isset( $plan_level ) ) {
             update_post_meta( $post_id, '_smc_quiz_plan_level', $plan_level );
        }

		$post = get_post( $post_id );
		return rest_ensure_response( $this->prepare_item_for_response( $post, $request ) );
	}

    /**
     * Update one item from the collection.
     */
    public function update_item( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $post      = get_post( $id );

        if ( ! $post || 'smc_quiz' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Quiz not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $title     = sanitize_text_field( $request->get_param( 'title' ) );
        $questions = $request->get_param( 'questions' );
        $settings  = $request->get_param( 'settings' );
        $dashboard_config = $request->get_param( 'dashboard_config' );
        $stages = $request->get_param( 'stages' );

        if ( ! empty( $title ) ) {
            wp_update_post( [
                'ID'         => $id,
                'post_title' => $title,
            ] );
        }

        if ( isset( $questions ) && is_array( $questions ) ) {
            update_post_meta( $id, '_smc_quiz_questions', $questions );
        }

        if ( isset( $settings ) ) {
            update_post_meta( $id, '_smc_quiz_settings', $settings );
        }

        if ( isset( $dashboard_config ) ) {
            update_post_meta( $id, '_smc_quiz_dashboard_config', $dashboard_config );
        }

        if ( isset( $stages ) ) {
            update_post_meta( $id, '_smc_quiz_stages', $stages );
        }

        $plan_level = $request->get_param( 'plan_level' );
        if ( isset( $plan_level ) ) {
             update_post_meta( $id, '_smc_quiz_plan_level', $plan_level );
        }

        $post = get_post( $id );
        return rest_ensure_response( $this->prepare_item_for_response( $post, $request ) );
    }

    /**
     * Seed quizzes.
     */
    public function seed_quizzes() {
        if ( ! class_exists( '\SMC\Viable\Seeder' ) ) {
            return new WP_Error( 'seeder_missing', 'Seeder class not found.', [ 'status' => 500 ] );
        }
        
        $logs = \SMC\Viable\Seeder::seed_content();
        
        return rest_ensure_response( [ 
            'message' => 'Seeding completed.',
            'logs' => $logs
        ] );
    }

    /**
     * Delete item permissions check.
     */
    public function delete_item_permissions_check( $request ) {
        if ( ! current_user_can( 'delete_posts' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Delete one item from the collection.
     */
    public function delete_item( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'smc_quiz' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Quiz not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $result = wp_delete_post( $id, true );

        if ( ! $result ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete quiz.', 'smc-viable' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }

    /**
     * Handle Email Submission with Report.
     */
    public function submit_email_report( $request ) {
        $parameters = $request->get_file_params();
        $params     = $request->get_params();
        
        $name  = sanitize_text_field( $params['name'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );
        $phone = sanitize_text_field( $params['phone'] ?? '' );
        $quiz_id = (int) ( $params['quiz_id'] ?? 0 );
        
        // Parse Score Data
        $score_data_json = $params['score_data'] ?? '';
        $score_data = json_decode( $score_data_json, true );
        
        // --- 1. Save Lead Logic (Always save lead) ---
        // Check for duplicate? (Keeping existing logic)
        $existing_leads = get_posts( [
            'post_type'      => 'smc_lead',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_smc_lead_email',
                    'value'   => $email,
                    'compare' => '=',
                ]
            ]
        ] );

        $lead_id = 0;
        if ( ! empty( $existing_leads ) ) {
             // Maybe update existing lead?
             // For now, let's allow it or just use existing (duplicate logic was returning error before, let's keep it safe for now but maybe relax)
             // The previous logic returned 409. 
             // Let's stick to 409 if strict, but maybe we want to allow re-takes?
             // For this MVP, let's return 409 as per previous code to be safe.
             return new WP_Error( 'duplicate_email', __( 'This email has already submitted an assessment.', 'smc-viable' ), [ 'status' => 409 ] );
        } else {
             $lead_id = wp_insert_post( [
                 'post_title'  => $name . ' - ' . date('Y-m-d H:i'),
                 'post_type'   => 'smc_lead',
                 'post_status' => 'publish',
                 'meta_input'  => [
                     '_smc_lead_name'    => $name,
                     '_smc_lead_email'   => $email,
                     '_smc_lead_phone'   => $phone,
                     '_smc_lead_quiz_id' => $quiz_id,
                 ]
             ] );
        }
        // ------------------------------------------

        // --- 2. Process Enrollment Rules ---
        $enrollment_results = [];
        $recommended_courses = [];
        $requires_login = false;

        if ( $quiz_id && is_array( $score_data ) ) {
            if ( is_user_logged_in() ) {
                // Logged in: Enroll directly
                $user_id = get_current_user_id();
                // Check if user email matches input email? 
                // Ideally yes, but maybe they are submitting for themselves.
                // Trust logged in user ID.
                $enrollment_results = Enrollment_Manager::process_quiz_enrollment( $user_id, $quiz_id, $score_data );
                
                // Send Enrollment Notification
                if ( ! empty( $enrollment_results ) ) {
                    $user = get_userdata( $user_id );
                    \SMC\Viable\Email_Service::send_quiz_enrollment( $user, $enrollment_results );
                }
            } else {
                // Anonymous: Dry run
                $recommended_courses = Enrollment_Manager::evaluate_quiz_rules( $quiz_id, $score_data );
                if ( ! empty( $recommended_courses ) ) {
                    $requires_login = true;
                }
            }
        }
        // -----------------------------------

        // --- 3. Send Email (Reports) ---
        // (Existing email logic...)
        // Simplified for brevity in this rewrite, but reusing the logic from previous version
        
        $email_sent = false;
        $file = $parameters['report'] ?? null;
        if ( $file && $file['type'] === 'application/pdf' ) {
            // ... (Handle PDF upload/move/email) ...
            // Re-implementing simplified version:
             $upload_dir = wp_upload_dir();
             $temp_path = $upload_dir['basedir'] . '/smc_reports/';
             if ( ! file_exists( $temp_path ) ) mkdir( $temp_path, 0755, true );
             $new_file_path = $temp_path . 'report_' . time() . '.pdf';
             
             if ( move_uploaded_file( $file['tmp_name'], $new_file_path ) ) {
                 $subject = sprintf( __( 'Your Assessment Results - %s', 'smc-viable' ), get_bloginfo( 'name' ) );
                 $message = sprintf( __( "Hi %s,\n\nHere is your assessment report attached.\n\nBest,\n%s", 'smc-viable' ), $name, get_bloginfo( 'name' ) );
                 $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
                 
                 $email_sent = wp_mail( $email, $subject, $message, $headers, [ $new_file_path ] );
                 if ( $email_sent ) unlink( $new_file_path );
             }
        }
        
        // -------------------------------

        return rest_ensure_response( [ 
            'success' => true, 
            'message' => 'Submission processed.',
            'lead_id' => $lead_id,
            'enrolled_courses' => $enrollment_results,
            'recommended_courses' => $recommended_courses,
            'requires_login' => $requires_login,
            'email_sent' => $email_sent
        ] );
    }
}
