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

        // Single quiz operations (GET, DELETE, UPDATE)
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
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
}
