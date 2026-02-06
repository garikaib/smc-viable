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
                'permission_callback' => '__return_true', // Public endpoint
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

        // Single lead DELETE
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

        // Single quiz operations (GET, DELETE, UPDATE)
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
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
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
            // Extract name from title (format: "Name - Date")
            // Also check for dedicated name meta (for future leads)
            $name = get_post_meta( $post->ID, '_smc_lead_name', true );
            if ( empty( $name ) ) {
                // Parse from title - split on " - " and take first part
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
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_lead( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'smc_lead' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Lead not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $result = wp_delete_post( $id, true ); // Force delete (bypass trash)

        if ( ! $result ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete lead.', 'smc-viable' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param \WP_REST_Request $request Note: using fully qualified for WP compatibility.
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Get a collection of items.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response|\WP_Error
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
     *
     * @param \WP_Post         $post    Post object.
     * @param \WP_REST_Request $request Request object.
     * @return array
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
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
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
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * Create one item from the collection.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response|\WP_Error
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

		// Save questions meta
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
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
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

        // Update title if provided
        if ( ! empty( $title ) ) {
            wp_update_post( [
                'ID'         => $id,
                'post_title' => $title,
            ] );
        }

        // Update questions meta if provided
        if ( isset( $questions ) && is_array( $questions ) ) {
            update_post_meta( $id, '_smc_quiz_questions', $questions );
        }

        // Update settings
        if ( isset( $settings ) ) {
            update_post_meta( $id, '_smc_quiz_settings', $settings );
        }

        // Update dashboard config
        if ( isset( $dashboard_config ) ) {
            update_post_meta( $id, '_smc_quiz_dashboard_config', $dashboard_config );
        }

        // Update stages
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
     *
     * @return \WP_REST_Response|\WP_Error
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
     * Check if a given request has access to delete items.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return bool|\WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        if ( ! current_user_can( 'delete_posts' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Delete one item from the collection.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_item( $request ) {
        $id = (int) $request->get_param( 'id' );
        $post = get_post( $id );

        if ( ! $post || 'smc_quiz' !== $post->post_type ) {
            return new WP_Error( 'not_found', __( 'Quiz not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $result = wp_delete_post( $id, true ); // Force delete (bypass trash)

        if ( ! $result ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete quiz.', 'smc-viable' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }

    /**
     * Handle Email Submission with Report.
     *
     * @param \WP_REST_Request $request Full data about the request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function submit_email_report( $request ) {
        $parameters = $request->get_file_params();
        $params     = $request->get_params();
        
        $name  = sanitize_text_field( $params['name'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );
        $phone = sanitize_text_field( $params['phone'] ?? '' );
        $quiz_id = (int) ( $params['quiz_id'] ?? 0 );

        error_log( 'SMC Quiz: Email submission started.' );

        if ( empty( $email ) || ! is_email( $email ) ) {
            error_log( 'SMC Quiz: Invalid email: ' . $email );
            return new WP_Error( 'invalid_email', __( 'Valid email required.', 'smc-viable' ), [ 'status' => 400 ] );
        }

        // Check for duplicate email
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

        if ( ! empty( $existing_leads ) ) {
            error_log( 'SMC Quiz: Duplicate email detected: ' . $email );
            return new WP_Error( 'duplicate_email', __( 'This email has already submitted an assessment.', 'smc-viable' ), [ 'status' => 409 ] );
        }

        if ( empty( $parameters['report'] ) ) {
             error_log( 'SMC Quiz: Missing report file.' );
            return new WP_Error( 'missing_file', __( 'Report file is missing.', 'smc-viable' ), [ 'status' => 400 ] );
        }

        $file = $parameters['report'];
        
        // Basic Security Check on File
        if ( $file['type'] !== 'application/pdf' ) {
             return new WP_Error( 'invalid_file', __( 'Only PDF allowed.', 'smc-viable' ), [ 'status' => 400 ] );
        }

        // Email logic
        $subject = sprintf( __( 'Your Assessment Results - %s', 'smc-viable' ), get_bloginfo( 'name' ) );
        $message = sprintf( __( "Hi %s,\n\nHere is your assessment report attached.\n\nBest,\n%s", 'smc-viable' ), $name, get_bloginfo( 'name' ) );
        $from_name = get_bloginfo( 'name' );
        $from_email = get_option( 'admin_email' );
        $headers = [ 
            'Content-Type: text/plain; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ) 
        ];
        
        // Use the tmp file directly for attachment
        $attachments = [ $file['tmp_name'] ];
        
        // We need to rename it to have .pdf extension for mailer potentially, or rely on mailer handling tmp file.
        // WP_Mail usually expects file path.
        // It's safer to move it to a temp dir with correct name.
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/smc_reports/';
        if ( ! file_exists( $temp_path ) ) {
            mkdir( $temp_path, 0755, true );
        }
        
        $new_file_path = $temp_path . 'report_' . time() . '.pdf';
        if ( move_uploaded_file( $file['tmp_name'], $new_file_path ) ) {
             
             // --- Save Lead ---
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
             error_log( 'SMC Quiz: Lead saved with ID: ' . $lead_id );
             // -----------------

             // Add error capture for wp_mail failures
             $mail_error = null;
             add_action('wp_mail_failed', function($error) use (&$mail_error) {
                 $mail_error = $error;
                 error_log('SMC Quiz: wp_mail failed with error: ' . print_r($error->get_error_message(), true));
             });

             $sent = wp_mail( $email, $subject, $message, $headers, [ $new_file_path ] );
             
             if ( $sent ) {
                 unlink( $new_file_path ); // Cleanup only if successful
                 error_log( 'SMC Quiz: Email sent successfully to ' . $email );
                 // Notify Admin as well
                 $admin_email = get_option( 'admin_email' );
                 $admin_message = sprintf( "New lead generated:\nName: %s\nEmail: %s\nPhone: %s\nQuiz ID: %d", $name, $email, $phone, $quiz_id );
                 wp_mail( $admin_email, "New Assessment Submission: $name", $admin_message );

                 return rest_ensure_response( [ 'success' => true, 'message' => __( 'Report sent successfully.', 'smc-viable' ) ] );
             } else {
                 // FALLBACK: Email with attachment failed, try without attachment
                 error_log( 'SMC Quiz: Attachment email failed, trying fallback without attachment...' );
                 
                 // Keep the PDF file and generate a unique download link
                 $unique_filename = 'report_' . $lead_id . '_' . wp_generate_password(8, false) . '.pdf';
                 $permanent_path = $temp_path . $unique_filename;
                 rename( $new_file_path, $permanent_path );
                 
                 // Generate download URL
                 $download_url = $upload_dir['baseurl'] . '/smc_reports/' . $unique_filename;
                 
                 // Send email WITHOUT attachment
                 $fallback_subject = sprintf( __( 'Your Assessment Results - %s', 'smc-viable' ), get_bloginfo( 'name' ) );
                 $fallback_message = sprintf( 
                     __( "Hi %s,\n\nThank you for completing our assessment!\n\nYou can download your personalized PDF report here:\n%s\n\nThis link will be available for 7 days.\n\nBest regards,\n%s", 'smc-viable' ), 
                     $name, 
                     $download_url,
                     get_bloginfo( 'name' ) 
                 );
                 
                 // Remove attachment error handler
                 $mail_error = null;
                 
                 $fallback_sent = wp_mail( $email, $fallback_subject, $fallback_message, $headers );
                 
                 if ( $fallback_sent ) {
                     error_log( 'SMC Quiz: Fallback email (without attachment) sent successfully to ' . $email );
                     
                     // Store download URL in lead meta for reference
                     update_post_meta( $lead_id, '_smc_lead_report_url', $download_url );
                     
                     // Notify Admin
                     $admin_email = get_option( 'admin_email' );
                     $admin_message = sprintf( "New lead generated (report sent via download link):\nName: %s\nEmail: %s\nPhone: %s\nQuiz ID: %d\nReport: %s", $name, $email, $phone, $quiz_id, $download_url );
                     wp_mail( $admin_email, "New Assessment Submission: $name", $admin_message );
                     
                     return rest_ensure_response( [ 
                         'success' => true, 
                         'message' => __( 'Report link sent successfully! Check your email.', 'smc-viable' ) 
                     ] );
                 } else {
                     // Both methods failed
                     error_log( 'SMC Quiz: Both email methods failed for ' . $email );
                     
                     // Store URL anyway for manual access
                     update_post_meta( $lead_id, '_smc_lead_report_url', $download_url );
                     
                     return rest_ensure_response( [ 
                         'success' => true, 
                         'message' => __( 'Your submission was saved. We encountered an email issue - please contact support.', 'smc-viable' ),
                         'email_sent' => false,
                         'download_url' => $download_url  // Provide direct link as last resort
                     ] );
                 }
             }
        }

        return new WP_Error( 'upload_failed', __( 'Failed to process report file.', 'smc-viable' ), [ 'status' => 500 ] );
    }
}
