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
		// Example implementation
		$args = [
			'post_type' => 'smc_quiz',
		];
		$posts = get_posts( $args );

		$data = [];
		foreach ( $posts as $post ) {
			$data[] = $this->prepare_item_for_response( $post, $request );
		}

		return rest_ensure_response( $data );
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
		// Placeholder for creation logic
		return rest_ensure_response( [ 'message' => 'Created' ] );
	}
}
