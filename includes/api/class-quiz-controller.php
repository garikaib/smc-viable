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
use SMC\Viable\LMS_DB;
use SMC\Viable\Quiz_Question_Schema;
use SMC\Viable\Quiz_Grader;

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
			'/' . $this->rest_base . '/migrate',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'migrate_quiz_questions' ],
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
            '/migrate-legacy',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'migrate_legacy_data' ],
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
            '/report/save',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_assessment_report' ],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            $this->namespace,
            '/report/download/(?P<id>[\d]+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'download_assessment_report' ],
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
                'delivery' => get_post_meta( $post->ID, '_smc_lead_delivery', true ) ?: 'download',
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
		if ( ! $this->current_user_is_instructor() ) {
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
        $raw_questions = get_post_meta( $post->ID, '_smc_quiz_questions', true ) ?: [];
        $normalized_questions = Quiz_Question_Schema::normalize_questions( $raw_questions );

        return [
            'id'    => $post->ID,
            'title' => [
                'rendered' => $post->post_title,
            ],
            'date'  => $post->post_date,
            'meta'  => [
                '_smc_quiz_questions' => $normalized_questions,
                '_smc_quiz_settings'  => get_post_meta( $post->ID, '_smc_quiz_settings', true ) ?: [],
                '_smc_quiz_dashboard_config' => get_post_meta( $post->ID, '_smc_quiz_dashboard_config', true ) ?: [],
                '_smc_quiz_stages' => get_post_meta( $post->ID, '_smc_quiz_stages', true ) ?: [],
                '_smc_quiz_plan_level' => \SMC\Viable\Plan_Tiers::normalize_or_default( (string) get_post_meta( $post->ID, '_smc_quiz_plan_level', true ), 'free' ),
                '_smc_quiz_shop' => get_post_meta( $post->ID, '_smc_quiz_shop', true ) ?: [],
                '_smc_quiz_schema_version' => Quiz_Question_Schema::VERSION,
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
		if ( ! $this->current_user_is_instructor() ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to create quizzes.', 'smc-viable' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/**
	 * By default, editors and administrators are considered instructors.
	 */
	private function current_user_is_instructor(): bool {
		$user = wp_get_current_user();

		return $user instanceof \WP_User
			&& (
				in_array( 'administrator', (array) $user->roles, true )
				|| in_array( 'editor', (array) $user->roles, true )
			);
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
			update_post_meta( $post_id, '_smc_quiz_questions', Quiz_Question_Schema::normalize_questions( $questions ) );
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
             update_post_meta( $post_id, '_smc_quiz_plan_level', \SMC\Viable\Plan_Tiers::normalize_or_default( (string) $plan_level, 'free' ) );
        }

        $shop = $request->get_param( 'shop' );
        if ( isset( $shop ) ) {
             update_post_meta( $post_id, '_smc_quiz_shop', $this->normalize_shop_settings( $shop ) );
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
            update_post_meta( $id, '_smc_quiz_questions', Quiz_Question_Schema::normalize_questions( $questions ) );
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
             update_post_meta( $id, '_smc_quiz_plan_level', \SMC\Viable\Plan_Tiers::normalize_or_default( (string) $plan_level, 'free' ) );
        }

        $shop = $request->get_param( 'shop' );
        if ( isset( $shop ) ) {
             update_post_meta( $id, '_smc_quiz_shop', $this->normalize_shop_settings( $shop ) );
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
     * Migrate legacy test data to latest schema.
     */
    public function migrate_legacy_data() {
        if ( ! class_exists( '\SMC\Viable\Seeder' ) ) {
            return new WP_Error( 'seeder_missing', 'Seeder class not found.', [ 'status' => 500 ] );
        }

        $logs = \SMC\Viable\Seeder::migrate_legacy_data();

        return rest_ensure_response( [
            'message' => 'Legacy migration completed.',
            'logs'    => $logs,
        ] );
    }

    private function normalize_shop_settings( $shop ): array {
        if ( is_string( $shop ) ) {
            $decoded = json_decode( $shop, true );
            if ( is_array( $decoded ) ) {
                $shop = $decoded;
            }
        }

        if ( ! is_array( $shop ) ) {
            return [];
        }

        $mode = sanitize_key( (string) ( $shop['access_mode'] ?? 'standalone' ) );
        if ( ! in_array( $mode, [ 'standalone', 'plan', 'both' ], true ) ) {
            $mode = 'standalone';
        }

        return [
            'enabled'       => ! empty( $shop['enabled'] ),
            'access_mode'   => $mode,
            'assigned_plan' => \SMC\Viable\Plan_Tiers::normalize_or_default( (string) ( $shop['assigned_plan'] ?? 'free' ), 'free' ),
            'price'         => isset( $shop['price'] ) ? (float) $shop['price'] : 0.0,
            'features'      => is_array( $shop['features'] ?? null )
                ? array_values( array_filter( array_map( 'sanitize_text_field', $shop['features'] ) ) )
                : [],
        ];
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
        $params = $request->get_params();
        $params['delivery'] = 'email';
        $request->set_param( 'delivery', 'email' );
        return $this->save_assessment_report( $request );
    }

    /**
     * Save assessment report snapshot for logged-in and guest users.
     */
    public function save_assessment_report( $request ) {
        $params = $request->get_params();
        $quiz_id = (int) ( $params['quiz_id'] ?? 0 );
        $quiz = $quiz_id > 0 ? get_post( $quiz_id ) : null;
        if ( ! $quiz || 'smc_quiz' !== $quiz->post_type ) {
            return new WP_Error( 'quiz_not_found', __( 'Assessment not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $raw_score_data = $params['score_data'] ?? [];
        $incoming_score_data = is_string( $raw_score_data ) ? json_decode( $raw_score_data, true ) : $raw_score_data;
        if ( ! is_array( $incoming_score_data ) ) {
            $incoming_score_data = [];
        }

		$raw_answers = $params['answers'] ?? ( $incoming_score_data['answers'] ?? [] );
		$answers = is_array( $raw_answers ) ? $raw_answers : [];

		$quiz_questions_raw = get_post_meta( $quiz_id, '_smc_quiz_questions', true ) ?: [];
		$quiz_questions = Quiz_Question_Schema::normalize_questions( $quiz_questions_raw );
		$score_data = Quiz_Grader::grade( $quiz_questions, $answers );

        $raw_settings = get_post_meta( $quiz_id, '_smc_quiz_settings', true );
        $quiz_settings = is_array( $raw_settings ) ? $raw_settings : [];
        $guest_pdf_access = 'public' === (string) ( $quiz_settings['guest_pdf_access'] ?? 'account_required' )
            ? 'public'
            : 'account_required';
        $allow_logged_in_email_link = ! isset( $quiz_settings['logged_in_email_link'] ) || (bool) $quiz_settings['logged_in_email_link'];
        $allow_guest_email_capture  = ! isset( $quiz_settings['guest_email_capture'] ) || (bool) $quiz_settings['guest_email_capture'];
        $delivery = sanitize_key( (string) ( $params['delivery'] ?? 'download' ) );
        if ( '' === $delivery ) {
            $delivery = 'download';
        }

        $name  = sanitize_text_field( $params['name'] ?? '' );
        $email = sanitize_email( $params['email'] ?? '' );
        $phone = sanitize_text_field( $params['phone'] ?? '' );

        $user_id = 0;
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $user = get_userdata( $user_id );
            if ( $user instanceof \WP_User ) {
                if ( '' === $name ) {
                    $name = (string) $user->display_name;
                }
                if ( '' === $email ) {
                    $email = (string) $user->user_email;
                }
            }
        } elseif ( '' !== $email ) {
            $matched_user = get_user_by( 'email', $email );
            if ( $matched_user instanceof \WP_User ) {
                $user_id = (int) $matched_user->ID;
            }
        }

        if ( 'email_link' === $delivery && $user_id <= 0 ) {
            return new WP_Error( 'auth_required', __( 'Please log in to email your download link.', 'smc-viable' ), [ 'status' => 401 ] );
        }
        if ( 'email_link' === $delivery && ! $allow_logged_in_email_link ) {
            return new WP_Error( 'email_link_disabled', __( 'Email link delivery is disabled for this assessment.', 'smc-viable' ), [ 'status' => 400 ] );
        }
        if ( in_array( $delivery, [ 'email', 'email_guest' ], true ) && ! $allow_guest_email_capture ) {
            return new WP_Error( 'guest_email_disabled', __( 'Guest email capture is disabled for this assessment.', 'smc-viable' ), [ 'status' => 400 ] );
        }
        if ( in_array( $delivery, [ 'email', 'email_guest', 'email_link', 'account_required' ], true ) && ! is_email( $email ) ) {
            return new WP_Error( 'missing_email', __( 'A valid email is required for this delivery option.', 'smc-viable' ), [ 'status' => 400 ] );
        }

        $total_score = isset( $score_data['total_score'] ) ? (int) $score_data['total_score'] : 0;
        $scores_by_stage = $score_data['scores_by_stage'] ?? [];
        if ( ! is_array( $scores_by_stage ) ) {
            $scores_by_stage = [];
        }

        $total_possible = 0;
        foreach ( $scores_by_stage as $stage_data ) {
            if ( is_array( $stage_data ) ) {
                $total_possible += isset( $stage_data['max'] ) ? (int) $stage_data['max'] : 0;
            }
        }
        if ( $total_possible <= 0 ) {
            $total_possible = max( 15, count( (array) get_post_meta( $quiz_id, '_smc_quiz_questions', true ) ) * 15 );
        }

        $percent = (int) round( ( $total_score / max( 1, $total_possible ) ) * 100 );
        $result = $this->build_result_summary( $quiz_id, $total_score, $percent );
        $flags = $this->extract_flags( $score_data );
        $stage_summary = $this->build_stage_summary( $scores_by_stage );

        $report_token = wp_generate_password( 32, false, false );
        $lead_id = wp_insert_post(
            [
                'post_title'  => sprintf(
                    'Assessment Report - %s - %s',
                    '' !== $name ? $name : ( '' !== $email ? $email : 'Guest' ),
                    current_time( 'Y-m-d H:i' )
                ),
                'post_type'   => 'smc_lead',
                'post_status' => 'publish',
                'meta_input'  => [
                    '_smc_lead_name'             => $name,
                    '_smc_lead_email'            => $email,
                    '_smc_lead_phone'            => $phone,
                    '_smc_lead_quiz_id'          => $quiz_id,
                    '_smc_lead_user_id'          => $user_id,
                    '_smc_lead_score_data'       => wp_json_encode( $score_data ),
                    '_smc_lead_stage_summary'    => wp_json_encode( $stage_summary ),
                    '_smc_lead_flags'            => wp_json_encode( $flags ),
                    '_smc_lead_result_title'     => $result['title'],
                    '_smc_lead_result_message'   => $result['message'],
                    '_smc_lead_result_color'     => $result['color'],
                    '_smc_lead_delivery'         => $delivery,
                    '_smc_lead_report_token'     => $report_token,
                    '_smc_lead_report_generated' => current_time( 'mysql' ),
                ],
            ]
        );

        if ( is_wp_error( $lead_id ) || $lead_id <= 0 ) {
            return new WP_Error( 'save_failed', __( 'Failed to save assessment report.', 'smc-viable' ), [ 'status' => 500 ] );
        }

        $enrollment_results = [];
        $recommended_courses = [];
        $requires_login = false;
        $email_sent = false;
        $email_error = '';

        if ( $user_id > 0 ) {
            $this->record_quiz_submission( $user_id, $quiz_id, $score_data );
            $enrollment_results = Enrollment_Manager::process_quiz_enrollment( $user_id, $quiz_id, $score_data );
        } else {
            $recommended_courses = Enrollment_Manager::evaluate_quiz_rules( $quiz_id, $score_data );
            $requires_login = 'account_required' === $guest_pdf_access;
        }

        $download_url = add_query_arg(
            'token',
            rawurlencode( $report_token ),
            rest_url( sprintf( 'smc/v1/report/download/%d', (int) $lead_id ) )
        );

        if ( in_array( $delivery, [ 'email', 'email_guest', 'email_link' ], true ) && is_email( $email ) ) {
            $mail = $this->send_report_link_email( $email, $name, (string) $quiz->post_title, $download_url );
            if ( is_wp_error( $mail ) ) {
                $email_error = $mail->get_error_message();
            } else {
                $email_sent = true;
            }
        }

        return rest_ensure_response(
            [
                'success'             => true,
                'lead_id'             => (int) $lead_id,
                'download_url'        => esc_url_raw( $download_url ),
                'delivery'            => $delivery,
                'guest_pdf_access'    => $guest_pdf_access,
                'requires_login'      => $requires_login,
                'login_url'           => esc_url_raw( wp_login_url( home_url( '/my-account/' ) ) ),
                'register_url'        => esc_url_raw( wp_registration_url() ),
                'email_sent'          => $email_sent,
                'email_error'         => $email_error,
                'enrolled_courses'    => $enrollment_results,
                'recommended_courses' => $recommended_courses,
                'result'              => $result,
                'stage_summary'       => $stage_summary,
                'flags'               => $flags,
                'score_data'          => $score_data,
            ]
        );
    }

	/**
	 * Migrate all quizzes to schema v2 question format in-place.
	 */
	public function migrate_quiz_questions( $request ) {
		$posts = get_posts(
			[
				'post_type'      => 'smc_quiz',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$updated = 0;
		$skipped = 0;
		$items = [];

		foreach ( $posts as $quiz_id ) {
			$raw_questions = get_post_meta( (int) $quiz_id, '_smc_quiz_questions', true );
			$normalized = Quiz_Question_Schema::normalize_questions( $raw_questions );

			if ( ! is_array( $raw_questions ) ) {
				$raw_questions = [];
			}

			$before = wp_json_encode( $raw_questions );
			$after  = wp_json_encode( $normalized );
			if ( $before === $after ) {
				$skipped++;
				$items[] = [ 'quiz_id' => (int) $quiz_id, 'status' => 'skipped' ];
				continue;
			}

			update_post_meta( (int) $quiz_id, '_smc_quiz_questions', $normalized );
			$updated++;
			$items[] = [ 'quiz_id' => (int) $quiz_id, 'status' => 'updated', 'count' => count( $normalized ) ];
		}

		return rest_ensure_response(
			[
				'success' => true,
				'total'   => count( $posts ),
				'updated' => $updated,
				'skipped' => $skipped,
				'items'   => $items,
			]
		);
	}

    /**
     * Email a secure report download link.
     */
    private function send_report_link_email( string $email, string $name, string $quiz_title, string $download_url ) {
        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Invalid email for report delivery.', 'smc-viable' ), [ 'status' => 400 ] );
        }

        $recipient_name = '' !== trim( $name ) ? trim( $name ) : __( 'there', 'smc-viable' );
        $subject = sprintf(
            /* translators: %s: assessment title */
            __( 'Your %s report is ready', 'smc-viable' ),
            $quiz_title
        );
        $message_lines = [
            sprintf( __( 'Hi %s,', 'smc-viable' ), $recipient_name ),
            '',
            sprintf( __( 'Your report for "%s" is ready.', 'smc-viable' ), $quiz_title ),
            __( 'Use the secure link below to download your PDF:', 'smc-viable' ),
            esc_url_raw( $download_url ),
            '',
            __( 'If you did not request this, you can safely ignore this email.', 'smc-viable' ),
        ];
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
        $sent = wp_mail( $email, $subject, implode( "\n", $message_lines ), $headers );
        if ( ! $sent ) {
            return new WP_Error( 'mail_failed', __( 'Could not send report email.', 'smc-viable' ), [ 'status' => 500 ] );
        }
        return true;
    }

    /**
     * Download saved assessment report as PDF.
     */
    public function download_assessment_report( $request ) {
        $resolved_user_id = get_current_user_id();
        if ( $resolved_user_id <= 0 && defined( 'LOGGED_IN_COOKIE' ) && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
            $cookie_user_id = wp_validate_auth_cookie( (string) $_COOKIE[ LOGGED_IN_COOKIE ], 'logged_in' );
            if ( $cookie_user_id > 0 ) {
                wp_set_current_user( $cookie_user_id );
                $resolved_user_id = (int) $cookie_user_id;
            }
        }

        $report_id = (int) $request->get_param( 'id' );
        $lead = get_post( $report_id );
        if ( ! $lead || 'smc_lead' !== $lead->post_type ) {
            return new WP_Error( 'report_not_found', __( 'Report not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $report_user_id = (int) get_post_meta( $report_id, '_smc_lead_user_id', true );
        $token = sanitize_text_field( (string) $request->get_param( 'token' ) );
        $stored_token = (string) get_post_meta( $report_id, '_smc_lead_report_token', true );
        $report_email = sanitize_email( (string) get_post_meta( $report_id, '_smc_lead_email', true ) );

        $allowed = $resolved_user_id > 0 && user_can( $resolved_user_id, 'manage_options' );
        if ( ! $allowed && $resolved_user_id > 0 && $report_user_id > 0 && $report_user_id === $resolved_user_id ) {
            $allowed = true;
        }
        if ( ! $allowed && $resolved_user_id > 0 ) {
            $current_user = get_userdata( $resolved_user_id );
            $current_email = $current_user instanceof \WP_User ? sanitize_email( (string) $current_user->user_email ) : '';
            if ( '' !== $report_email && '' !== $current_email && $report_email === $current_email ) {
                $allowed = true;
                if ( $report_user_id <= 0 ) {
                    // Claim legacy guest report for this account once ownership is verified by email.
                    update_post_meta( $report_id, '_smc_lead_user_id', $resolved_user_id );
                }
            }
        }
        if ( ! $allowed && $resolved_user_id > 0 ) {
            $quiz_id = (int) get_post_meta( $report_id, '_smc_lead_quiz_id', true );
            if ( $quiz_id > 0 ) {
                global $wpdb;
                $table = $wpdb->prefix . LMS_DB::TABLE_QUIZ_SUBMISSIONS;
                $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
                if ( $table_exists ) {
                    $has_submission = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND quiz_id = %d",
                            $resolved_user_id,
                            $quiz_id
                        )
                    ) > 0;
                    if ( $has_submission ) {
                        $allowed = true;
                        if ( $report_user_id <= 0 ) {
                            update_post_meta( $report_id, '_smc_lead_user_id', $resolved_user_id );
                        }
                    }
                }
            }
        }
        if ( ! $allowed && '' !== $token && hash_equals( $stored_token, $token ) ) {
            $allowed = true;
        }

        if ( ! $allowed ) {
            return new WP_Error( 'forbidden', __( 'You are not allowed to download this report.', 'smc-viable' ), [ 'status' => 403 ] );
        }

        $quiz_id = (int) get_post_meta( $report_id, '_smc_lead_quiz_id', true );
        $quiz = $quiz_id > 0 ? get_post( $quiz_id ) : null;
        if ( ! $quiz || 'smc_quiz' !== $quiz->post_type ) {
            return new WP_Error( 'quiz_not_found', __( 'Assessment source not found.', 'smc-viable' ), [ 'status' => 404 ] );
        }

        $score_data = json_decode( (string) get_post_meta( $report_id, '_smc_lead_score_data', true ), true );
        if ( ! is_array( $score_data ) ) {
            $score_data = [];
        }

        $payload = [
            'quiz'          => $quiz,
            'name'          => (string) get_post_meta( $report_id, '_smc_lead_name', true ),
            'email'         => (string) get_post_meta( $report_id, '_smc_lead_email', true ),
            'score_data'    => $score_data,
            'stage_summary' => json_decode( (string) get_post_meta( $report_id, '_smc_lead_stage_summary', true ), true ) ?: [],
            'flags'         => json_decode( (string) get_post_meta( $report_id, '_smc_lead_flags', true ), true ) ?: [],
            'result'        => [
                'title'   => (string) get_post_meta( $report_id, '_smc_lead_result_title', true ),
                'message' => (string) get_post_meta( $report_id, '_smc_lead_result_message', true ),
                'color'   => (string) get_post_meta( $report_id, '_smc_lead_result_color', true ),
            ],
            'generated_at'  => (string) get_post_meta( $report_id, '_smc_lead_report_generated', true ),
            'report_id'     => $report_id,
        ];

        $pdf = $this->generate_assessment_pdf( $payload );

        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="assessment-report-' . (int) $report_id . '.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Build derived report summary from dashboard config and score.
     */
    private function build_result_summary( int $quiz_id, int $total_score, int $percent ): array {
        $default = [
            'title'   => 'Overall Score',
            'message' => ( $percent >= 70 ) ? 'Strong readiness with clear opportunities to scale.' : 'Your business shows promise, with key areas needing attention.',
            'color'   => ( $percent >= 70 ) ? '#0E7673' : '#A1232A',
        ];

        $raw = get_post_meta( $quiz_id, '_smc_quiz_dashboard_config', true );
        if ( ! $raw ) {
            return $default;
        }

        $config = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $config ) || empty( $config['dashboard_config']['rules'] ) || ! is_array( $config['dashboard_config']['rules'] ) ) {
            return $default;
        }

        foreach ( $config['dashboard_config']['rules'] as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }
            $logic = $rule['logic'] ?? [];
            if ( ! is_array( $logic ) ) {
                continue;
            }

            $matched = false;
            $operator = $logic['operator'] ?? 'gt';
            if ( 'gt' === $operator ) {
                $matched = $total_score > (int) ( $logic['value'] ?? 0 );
            } elseif ( 'lt' === $operator ) {
                $matched = $total_score < (int) ( $logic['value'] ?? 0 );
            } elseif ( 'gte' === $operator ) {
                $matched = $total_score >= (int) ( $logic['value'] ?? 0 );
            } elseif ( 'lte' === $operator ) {
                $matched = $total_score <= (int) ( $logic['value'] ?? 0 );
            } elseif ( 'between' === $operator ) {
                $matched = $total_score >= (int) ( $logic['min'] ?? 0 ) && $total_score <= (int) ( $logic['max'] ?? 0 );
            }

            if ( ! $matched ) {
                continue;
            }

            $style_color = sanitize_key( (string) ( $rule['style']['color'] ?? '' ) );
            $color_map = [
                'green'       => '#0E7673',
                'light-green' => '#2E9D8D',
                'orange'      => '#D97706',
                'red'         => '#A1232A',
            ];

            return [
                'title'   => (string) ( $rule['condition_text'] ?? $default['title'] ),
                'message' => (string) ( $rule['message'] ?? $default['message'] ),
                'color'   => $color_map[ $style_color ] ?? $default['color'],
            ];
        }

        return $default;
    }

    /**
     * Extract scored red flags from score payload.
     */
    private function extract_flags( array $score_data ): array {
        $flags = [];
        $stage_scores = $score_data['scores_by_stage'] ?? [];
        if ( ! is_array( $stage_scores ) ) {
            return $flags;
        }

        foreach ( $stage_scores as $stage_name => $stage ) {
            if ( ! is_array( $stage ) ) {
                continue;
            }
            $total = isset( $stage['total'] ) ? (int) $stage['total'] : 0;
            $max = max( 1, (int) ( $stage['max'] ?? 0 ) );
            $percent = (int) round( ( $total / $max ) * 100 );
            if ( $percent < 40 ) {
                $flags[] = [
                    'stage'   => (string) $stage_name,
                    'message' => 'Performance in this stage is below threshold and needs immediate attention.',
                    'score'   => $percent,
                ];
            }
        }

        return $flags;
    }

    /**
     * Build stage-level commentary cards for report.
     */
    private function build_stage_summary( array $scores_by_stage ): array {
        $summary = [];
        foreach ( $scores_by_stage as $stage_name => $stage ) {
            if ( ! is_array( $stage ) ) {
                continue;
            }

            $total = isset( $stage['total'] ) ? (int) $stage['total'] : 0;
            $max = max( 1, (int) ( $stage['max'] ?? 0 ) );
            $percent = (int) round( ( $total / $max ) * 100 );

            $tone = 'Foundation';
            $comment = 'Focus on process discipline and practical execution patterns in this area.';
            $color = '#A1232A';

            if ( $percent >= 75 ) {
                $tone = 'Advanced';
                $comment = 'Strong maturity. Keep compounding this advantage and mentor adjacent teams.';
                $color = '#0E7673';
            } elseif ( $percent >= 55 ) {
                $tone = 'Growing';
                $comment = 'Solid trajectory. Introduce tighter measurement and improve consistency.';
                $color = '#D97706';
            }

            $summary[] = [
                'stage'   => (string) $stage_name,
                'total'   => $total,
                'max'     => $max,
                'percent' => $percent,
                'tone'    => $tone,
                'comment' => $comment,
                'color'   => $color,
            ];
        }

        return $summary;
    }

    /**
     * Generate multi-page infographic PDF report with invoice-aligned visual language.
     */
    private function generate_assessment_pdf( array $payload ): string {
        $quiz = $payload['quiz'];
        $score_data = is_array( $payload['score_data'] ?? null ) ? $payload['score_data'] : [];
        $stage_summary = is_array( $payload['stage_summary'] ?? null ) ? $payload['stage_summary'] : [];
        $flags = is_array( $payload['flags'] ?? null ) ? $payload['flags'] : [];
        $result = is_array( $payload['result'] ?? null ) ? $payload['result'] : [];

        $total_score = isset( $score_data['total_score'] ) ? (int) $score_data['total_score'] : 0;
        $total_possible = 0;
        foreach ( $stage_summary as $stage ) {
            $total_possible += isset( $stage['max'] ) ? (int) $stage['max'] : 0;
        }
        $total_possible = max( 1, $total_possible );
        $percent = (int) round( ( $total_score / $total_possible ) * 100 );

        $accent = (string) ( $result['color'] ?? '#0E7673' );
        $result_title = (string) ( $result['title'] ?? 'Overall Score' );
        $result_message = (string) ( $result['message'] ?? '' );

        $display_name = trim( (string) ( $payload['name'] ?? '' ) );
        if ( '' === $display_name ) {
            $display_name = 'Assessment Participant';
        }

        $generated_at = (string) ( $payload['generated_at'] ?? current_time( 'mysql' ) );
        if ( '' === $generated_at ) {
            $generated_at = current_time( 'mysql' );
        }

        $page_one = [];
        $page_one[] = $this->pdf_fill_rect( 0, 0, 595, 112, [ 0.035, 0.133, 0.278 ] );
        $page_one[] = $this->pdf_fill_rect( 0, 112, 595, 12, [ 0.055, 0.463, 0.451 ] );
        $page_one[] = $this->pdf_text( 42, 48, 25, 'ASSESSMENT REPORT', [ 0.94, 0.98, 1 ] );
        $page_one[] = $this->pdf_text( 42, 76, 11, 'Social Marketing Centre', [ 0.84, 0.92, 0.97 ] );
        $page_one[] = $this->pdf_text( 390, 46, 11, 'Generated', [ 0.84, 0.92, 0.97 ] );
        $page_one[] = $this->pdf_text( 390, 66, 12, date( 'M d, Y', strtotime( $generated_at ) ), [ 1, 1, 1 ] );

        $page_one[] = $this->pdf_fill_rect( 38, 150, 519, 180, [ 0.965, 0.977, 0.992 ] );
        $page_one[] = $this->pdf_stroke_rect( 38, 150, 519, 180, [ 0.86, 0.91, 0.96 ] );
        $page_one[] = $this->pdf_text( 58, 182, 11, 'Assessment', [ 0.23, 0.32, 0.42 ] );
        $page_one[] = $this->pdf_text( 58, 204, 18, (string) $quiz->post_title );
        $page_one[] = $this->pdf_text( 58, 230, 10, 'Participant', [ 0.23, 0.32, 0.42 ] );
        $page_one[] = $this->pdf_text( 58, 248, 13, $display_name );
        $page_one[] = $this->pdf_text( 58, 274, 10, 'Result', [ 0.23, 0.32, 0.42 ] );
        $page_one[] = $this->pdf_text( 58, 292, 13, $result_title, $this->hex_to_rgb( $accent ) );

        $page_one[] = $this->pdf_fill_rect( 390, 178, 145, 132, $this->hex_to_rgb( $accent ) );
        $page_one[] = $this->pdf_text( 430, 214, 9, 'SCORE', [ 1, 1, 1 ] );
        $page_one[] = $this->pdf_text( 440, 252, 34, (string) $percent, [ 1, 1, 1 ] );
        $page_one[] = $this->pdf_text( 463, 254, 13, '%', [ 1, 1, 1 ] );
        $page_one[] = $this->pdf_text( 410, 284, 10, sprintf( '%d / %d', $total_score, $total_possible ), [ 0.94, 0.98, 1 ] );

        $message_lines = $this->wrap_text_for_pdf( $result_message, 66 );
        $line_y = 366;
        $page_one[] = $this->pdf_text( 42, 348, 12, 'Executive Commentary', [ 0.06, 0.14, 0.23 ] );
        foreach ( $message_lines as $line ) {
            $page_one[] = $this->pdf_text( 42, $line_y, 11, $line, [ 0.27, 0.34, 0.45 ] );
            $line_y += 16;
        }

        $page_one[] = $this->pdf_text( 42, 430, 12, 'Stage Breakdown', [ 0.06, 0.14, 0.23 ] );
        $bar_y = 452;
        $stage_for_page_one = array_slice( $stage_summary, 0, 5 );
        foreach ( $stage_for_page_one as $stage ) {
            $label = (string) ( $stage['stage'] ?? 'Stage' );
            $pct = (int) ( $stage['percent'] ?? 0 );
            $clr = $this->hex_to_rgb( (string) ( $stage['color'] ?? '#0E7673' ) );
            $width = (int) round( max( 0, min( 100, $pct ) ) * 4.6 );

            $page_one[] = $this->pdf_text( 42, $bar_y + 11, 10, $label, [ 0.17, 0.24, 0.34 ] );
            $page_one[] = $this->pdf_fill_rect( 220, $bar_y, 240, 14, [ 0.91, 0.94, 0.97 ] );
            $page_one[] = $this->pdf_fill_rect( 220, $bar_y, $width, 14, $clr );
            $page_one[] = $this->pdf_text( 468, $bar_y + 11, 10, $pct . '%', [ 0.17, 0.24, 0.34 ] );
            $bar_y += 32;
        }

        $page_one[] = $this->pdf_fill_rect( 0, 796, 595, 46, [ 0.035, 0.133, 0.278 ] );
        $page_one[] = $this->pdf_text( 40, 823, 9, 'SMC Viable | support@smcviable.com', [ 0.92, 0.97, 1 ] );
        $page_one[] = $this->pdf_text( 485, 823, 9, 'Page 1', [ 0.92, 0.97, 1 ] );

        $page_two = [];
        $page_two[] = $this->pdf_fill_rect( 0, 0, 595, 74, [ 0.055, 0.463, 0.451 ] );
        $page_two[] = $this->pdf_text( 42, 44, 21, 'STAGE INSIGHTS', [ 0.94, 0.98, 1 ] );
        $page_two[] = $this->pdf_text( 42, 64, 10, 'Action-ready guidance by capability area', [ 0.89, 0.96, 0.95 ] );

        $stage_rows = array_slice( $stage_summary, 5 );
        if ( empty( $stage_rows ) ) {
            $stage_rows = $stage_summary;
        }

        $row_y = 106;
        foreach ( $stage_rows as $stage ) {
            if ( $row_y > 510 ) {
                break;
            }
            $tone_color = $this->hex_to_rgb( (string) ( $stage['color'] ?? '#0E7673' ) );
            $page_two[] = $this->pdf_fill_rect( 38, $row_y, 519, 82, [ 0.97, 0.98, 0.99 ] );
            $page_two[] = $this->pdf_stroke_rect( 38, $row_y, 519, 82, [ 0.89, 0.93, 0.97 ] );
            $page_two[] = $this->pdf_fill_rect( 38, $row_y, 8, 82, $tone_color );
            $page_two[] = $this->pdf_text( 56, $row_y + 24, 13, (string) ( $stage['stage'] ?? 'Stage' ) );
            $page_two[] = $this->pdf_text( 56, $row_y + 44, 10, sprintf( '%s | %d%%', (string) ( $stage['tone'] ?? 'Foundation' ), (int) ( $stage['percent'] ?? 0 ) ), [ 0.35, 0.43, 0.52 ] );

            $comment_lines = $this->wrap_text_for_pdf( (string) ( $stage['comment'] ?? '' ), 78 );
            $comment_y = $row_y + 60;
            foreach ( array_slice( $comment_lines, 0, 1 ) as $line ) {
                $page_two[] = $this->pdf_text( 56, $comment_y, 10, $line, [ 0.22, 0.29, 0.38 ] );
            }
            $row_y += 96;
        }

        $page_two[] = $this->pdf_text( 42, 566, 12, 'Critical Flags', [ 0.06, 0.14, 0.23 ] );
        if ( empty( $flags ) ) {
            $page_two[] = $this->pdf_fill_rect( 38, 582, 519, 54, [ 0.91, 0.97, 0.94 ] );
            $page_two[] = $this->pdf_text( 56, 614, 11, 'No critical red flags were detected in this submission.', [ 0.04, 0.35, 0.26 ] );
        } else {
            $flag_y = 582;
            foreach ( array_slice( $flags, 0, 3 ) as $flag ) {
                $page_two[] = $this->pdf_fill_rect( 38, $flag_y, 519, 44, [ 0.99, 0.93, 0.93 ] );
                $page_two[] = $this->pdf_text( 56, $flag_y + 21, 11, (string) ( $flag['stage'] ?? 'Flag' ) . ' - ' . (string) ( $flag['score'] ?? 0 ) . '%', [ 0.63, 0.13, 0.16 ] );
                $page_two[] = $this->pdf_text( 56, $flag_y + 37, 9, (string) ( $flag['message'] ?? '' ), [ 0.4, 0.18, 0.2 ] );
                $flag_y += 52;
            }
        }

        $page_two[] = $this->pdf_fill_rect( 0, 796, 595, 46, [ 0.055, 0.463, 0.451 ] );
        $page_two[] = $this->pdf_text( 40, 823, 9, 'This report is designed to guide practical course selection and execution.', [ 0.90, 0.97, 0.95 ] );
        $page_two[] = $this->pdf_text( 485, 823, 9, 'Page 2', [ 0.90, 0.97, 0.95 ] );

        return $this->assemble_pdf_pages(
            [
                implode( "\n", $page_one ),
                implode( "\n", $page_two ),
            ]
        );
    }

    /**
     * Assemble multi-page PDF from content streams.
     */
    private function assemble_pdf_pages( array $streams ): string {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $page_ids = [];
        $base_page_id = 3;
        foreach ( array_values( $streams ) as $index => $stream ) {
            $page_id = $base_page_id + ( $index * 2 );
            $content_id = $page_id + 1;
            $page_ids[] = $page_id;
            $objects[ $content_id ] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $font_regular_id = $base_page_id + ( count( $streams ) * 2 );
        $font_bold_id = $font_regular_id + 1;

        $kids = implode(
            ' ',
            array_map(
                static function ( $id ) {
                    return $id . ' 0 R';
                },
                $page_ids
            )
        );

        $objects[2] = '<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count( $page_ids ) . ' >>';

        foreach ( array_values( $streams ) as $index => $stream ) {
            $page_id = $base_page_id + ( $index * 2 );
            $content_id = $page_id + 1;
            $objects[ $page_id ] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $font_regular_id . ' 0 R /F2 ' . $font_bold_id . ' 0 R >> >> /Contents ' . $content_id . ' 0 R >>';
        }

        $objects[ $font_regular_id ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[ $font_bold_id ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        ksort( $objects );
        return $this->assemble_pdf( $objects );
    }

    /**
     * Assemble raw PDF objects into final document.
     */
    private function assemble_pdf( array $objects ): string {
        $offsets = [];
        $pdf = "%PDF-1.4\n";

        foreach ( $objects as $id => $object ) {
            $offsets[ $id ] = strlen( $pdf );
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $start_xref = strlen( $pdf );
        $pdf .= "xref\n";
        $pdf .= '0 ' . ( count( $objects ) + 1 ) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ( $objects as $id => $object ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $id ] );
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n";
        $pdf .= "startxref\n";
        $pdf .= $start_xref . "\n";
        $pdf .= "%%EOF";
        return $pdf;
    }

    /**
     * Draw filled rectangle.
     */
    private function pdf_fill_rect( int $x, int $y, int $w, int $h, array $rgb ): string {
        $pdf_y = 842 - $y - $h;
        return sprintf( 'q %.3F %.3F %.3F rg %d %d %d %d re f Q', $rgb[0], $rgb[1], $rgb[2], $x, $pdf_y, $w, $h );
    }

    /**
     * Draw stroked rectangle.
     */
    private function pdf_stroke_rect( int $x, int $y, int $w, int $h, array $rgb ): string {
        $pdf_y = 842 - $y - $h;
        return sprintf( 'q %.3F %.3F %.3F RG 1 w %d %d %d %d re S Q', $rgb[0], $rgb[1], $rgb[2], $x, $pdf_y, $w, $h );
    }

    /**
     * Draw text command.
     */
    private function pdf_text( int $x, int $y, int $size, string $text, array $rgb = [ 0.06, 0.09, 0.16 ], string $font = 'F1' ): string {
        $safe = $this->escape_pdf_text( $text );
        $pdf_y = 842 - $y;
        return sprintf(
            'BT /%s %d Tf %.3F %.3F %.3F rg 1 0 0 1 %d %d Tm (%s) Tj ET',
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $pdf_y,
            $safe
        );
    }

    /**
     * Escape text for PDF stream.
     */
    private function escape_pdf_text( string $text ): string {
        return str_replace(
            [ "\\", "(", ")", "\r", "\n" ],
            [ "\\\\", "\\(", "\\)", '', ' ' ],
            wp_strip_all_tags( $text )
        );
    }

    /**
     * Convert HEX color to normalized RGB.
     */
    private function hex_to_rgb( string $hex ): array {
        $hex = ltrim( trim( $hex ), '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return [ 0.055, 0.463, 0.451 ];
        }
        return [
            hexdec( substr( $hex, 0, 2 ) ) / 255,
            hexdec( substr( $hex, 2, 2 ) ) / 255,
            hexdec( substr( $hex, 4, 2 ) ) / 255,
        ];
    }

    /**
     * Basic text wrap for fixed-width PDF layout.
     */
    private function wrap_text_for_pdf( string $text, int $limit = 72 ): array {
        $plain = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        if ( '' === $plain ) {
            return [];
        }
        return explode( "\n", wordwrap( $plain, $limit, "\n", true ) );
    }

    /**
     * Store a user quiz submission for account dashboard identity and analytics.
     */
    private function record_quiz_submission( int $user_id, int $quiz_id, array $score_data ): void {
        if ( $user_id <= 0 || $quiz_id <= 0 ) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . LMS_DB::TABLE_QUIZ_SUBMISSIONS;
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return;
        }

        $answers = $score_data['answers'] ?? [];
        if ( ! is_array( $answers ) ) {
            $answers = [];
        }

        $score = isset( $score_data['total_score'] ) ? (int) $score_data['total_score'] : 0;

        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'quiz_id'    => $quiz_id,
                'answers'    => wp_json_encode( $answers ),
                'score'      => $score,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%d', '%s' ]
        );
    }
}
