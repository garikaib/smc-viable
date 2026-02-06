<?php
/**
 * REST API Controller for Shop.
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
 * Class Shop_Controller
 */
class Shop_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'shop';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
        // GET /smc/v1/shop/products
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_products' ],
					'permission_callback' => '__return_true', // Public
				],
			]
		);

        // POST /smc/v1/shop/checkout
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/checkout',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_order' ],
                'permission_callback' => [ $this, 'checkout_permissions_check' ],
            ]
        );

        // GET /smc/v1/shop/access
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/access',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_user_access' ],
                'permission_callback' => '__return_true', // Public, handle auth internal
            ]
        );
        
        // POST /smc/v1/shop/paynow/init
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/paynow/init',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'init_payment' ],
                'permission_callback' => '__return_true',
            ]
        );

        // Admin Endpoints
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/admin/products',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_products_admin' ],
                    'permission_callback' => [ $this, 'admin_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'save_product' ],
                    'permission_callback' => [ $this, 'admin_permissions_check' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/admin/products/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'save_product' ],
                    'permission_callback' => [ $this, 'admin_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_product' ],
                    'permission_callback' => [ $this, 'admin_permissions_check' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/admin/orders',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_orders_admin' ],
                    'permission_callback' => [ $this, 'admin_permissions_check' ],
                ],
            ]
        );
	}

	/**
	 * Get Products.
	 */
	public function get_products( $request ) {
		$args = [
			'post_type'      => 'smc_product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];
		$posts = get_posts( $args );

		$data = [];
		foreach ( $posts as $post ) {
            $price = get_post_meta( $post->ID, '_price', true );
            $type = get_post_meta( $post->ID, '_product_type', true );
            $plan_level = get_post_meta( $post->ID, '_plan_level', true );
            
			$data[] = [
                'id'          => $post->ID,
                'slug'        => $post->post_name,
                'title'       => $post->post_title,
                'description' => $post->post_content, // Or excerpt
                'price'       => (float) $price,
                'type'        => $type,
                'plan_level'  => $plan_level, // If type is 'plan'
                'image'       => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: get_post_meta( $post->ID, '_smc_product_image_url', true ),
                'long_description' => get_post_meta( $post->ID, '_long_description', true ),
                'features'    => get_post_meta( $post->ID, '_features', true ) ?: [],
            ];
		}

		return rest_ensure_response( $data );
	}

    /**
     * Checkout / Create Order.
     */
    public function create_order( $request ) {
        $user_id = get_current_user_id();
        $items = $request->get_param( 'items' ); // Array of product IDs or objects
        $method = $request->get_param( 'payment_method' ) ?: 'paynow';
        
        if ( empty( $items ) || ! is_array( $items ) ) {
            return new WP_Error( 'empty_cart', 'Cart is empty.', [ 'status' => 400 ] );
        }

        // Calculate Total
        $total = 0;
        $order_items = [];
        
        foreach ( $items as $item ) {
            $product_id = isset( $item['id'] ) ? $item['id'] : $item;
            $product = get_post( $product_id );
            
            if ( ! $product || 'smc_product' !== $product->post_type ) {
                continue;
            }
            
            $price = (float) get_post_meta( $product_id, '_price', true );
            $total += $price;
            
            $order_items[] = [
                'product_id' => $product_id,
                'name'       => $product->post_title,
                'price'      => $price,
            ];
        }

        // Create Order Post
        $order_id = wp_insert_post( [
            'post_title'  => 'Order #' . time(), // Temporary title
            'post_type'   => 'smc_order',
            'post_status' => 'publish', // or 'pending'
        ] );

        if ( is_wp_error( $order_id ) ) {
            return $order_id;
        }

        // Update Order Title
        wp_update_post( [ 
            'ID' => $order_id, 
            'post_title' => 'Order #' . $order_id 
        ] );

        // Save Meta
        update_post_meta( $order_id, '_customer_id', $user_id );
        update_post_meta( $order_id, '_order_total', $total );
        update_post_meta( $order_id, '_order_status', 'pending' ); // Pending until payment
        update_post_meta( $order_id, '_order_items', $order_items );
        update_post_meta( $order_id, '_payment_method', $method );

        return rest_ensure_response( [
            'order_id' => $order_id,
            'total'    => $total,
            'status'   => 'pending',
            'message'  => 'Order created. Proceed to payment.'
        ] );
    }

    /**
     * Init Payment (Mock).
     */
    public function init_payment( $request ) {
        $order_id = $request->get_param( 'order_id' );
        // Simulate Paynow URL generation
        // In reality, this would call Paynow API
        
        // Mock Success Update for now (Auto-complete for testing)
        // Note: Remove this mock logic in production!
        $mock_success = true;
        
        if ( $mock_success ) {
             $this->complete_order( $order_id );
             return rest_ensure_response( [
                 'success' => true,
                 'redirect_url' => home_url( '/smc-shop/success?order_id=' . $order_id ), // Example frontend route
             ] );
        }
    }
    
    /**
     * Complete Order (Internal Helper).
     */
    private function complete_order( $order_id ) {
        update_post_meta( $order_id, '_order_status', 'completed' );
        
        // Grant Access
        $items = get_post_meta( $order_id, '_order_items', true );
        $user_id = get_post_meta( $order_id, '_customer_id', true );
        
        if ( ! $user_id ) return;
        
        foreach ( $items as $item ) {
            $product_id = $item['product_id'];
            $type = get_post_meta( $product_id, '_product_type', true );
            
            if ( 'plan' === $type ) {
                $level = get_post_meta( $product_id, '_plan_level', true );
                // Set User Plan
                update_user_meta( $user_id, '_smc_user_plan', $level );
            }
            // Handle other types (single access) logic later
        }
    }

    /**
     * Get User Access Info.
     */
    public function get_user_access( $request ) {
        if ( ! is_user_logged_in() ) {
            return rest_ensure_response( [
                'user_id' => 0,
                'plan'    => 'free',
                'remedial' => [],
            ] );
        }

        $user_id = get_current_user_id();
        $plan = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
        
        // Get remedial recommendations (Mock logic for now)
        // Find latest lead linked to this user email?
        // Or assume user is logged in and we track their quiz attempts via user_id in future.
        // For now, just return plan.
        
        return rest_ensure_response( [
            'user_id' => $user_id,
            'plan'    => $plan,
            'remedial' => [], // TODO: implementations
        ] );
    }

    /**
     * Permissions Check.
     */
    public function checkout_permissions_check( $request ) {
        return is_user_logged_in();
    }
    
    public function access_permissions_check( $request ) {
        return true; 
    }

    public function admin_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get Products for Admin.
     */
    public function get_products_admin( $request ) {
        $args = [
            'post_type'      => 'smc_product',
            'posts_per_page' => -1,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
        ];
        $posts = get_posts( $args );

        $data = [];
        foreach ( $posts as $post ) {
            $data[] = $this->prepare_product_for_response( $post );
        }
        return rest_ensure_response( $data );
    }

    /**
     * Save Product (Create/Update).
     */
    public function save_product( $request ) {
        $id = (int) $request->get_param( 'id' );
        $title = $request->get_param( 'title' );
        $content = $request->get_param( 'content' );
        $price = $request->get_param( 'price' );
        $type = $request->get_param( 'product_type' );
        $plan_level = $request->get_param( 'plan_level' );
        $status = $request->get_param( 'status' ) ?: 'publish';

        $post_data = [
            'post_type'    => 'smc_product',
            'post_title'   => sanitize_text_field( $title ),
            'post_content' => wp_kses_post( $content ),
            'post_status'  => $status,
        ];

        if ( $id > 0 ) {
            $post_data['ID'] = $id;
            $post_id = wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Update Meta
        update_post_meta( $post_id, '_price', (float) $price );
        update_post_meta( $post_id, '_product_type', sanitize_text_field( $type ) );
        update_post_meta( $post_id, '_plan_level', sanitize_text_field( $plan_level ) );

        $post = get_post( $post_id );
        return rest_ensure_response( $this->prepare_product_for_response( $post ) );
    }

    /**
     * Delete Product.
     */
    public function delete_product( $request ) {
        $id = (int) $request->get_param( 'id' );
        $result = wp_delete_post( $id, true );
        return rest_ensure_response( [ 'success' => (bool) $result ] );
    }

    /**
     * Get Orders for Admin.
     */
    public function get_orders_admin( $request ) {
        $args = [
            'post_type'      => 'smc_order',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ];
        $posts = get_posts( $args );

        $data = [];
        foreach ( $posts as $post ) {
            $customer_id = get_post_meta( $post->ID, '_customer_id', true );
            $user = get_userdata( (int) $customer_id );
            
            $data[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'status'        => get_post_meta( $post->ID, '_order_status', true ),
                'total'         => (float) get_post_meta( $post->ID, '_order_total', true ),
                'items'         => get_post_meta( $post->ID, '_order_items', true ),
                'customer_name' => $user ? $user->display_name : 'Guest',
                'customer_email'=> $user ? $user->user_email : 'N/A',
                'date'          => $post->post_date,
            ];
        }
        return rest_ensure_response( $data );
    }

    /**
     * Prepare Product for Response.
     */
    private function prepare_product_for_response( $post ) {
        return [
            'id'           => $post->ID,
            'slug'         => $post->post_name,
            'title'        => $post->post_title,
            'content'      => $post->post_content,
            'status'       => $post->post_status,
            'price'        => (float) get_post_meta( $post->ID, '_price', true ),
            'product_type' => get_post_meta( $post->ID, '_product_type', true ),
            'plan_level'   => get_post_meta( $post->ID, '_plan_level', true ),
            'image'        => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: get_post_meta( $post->ID, '_smc_product_image_url', true ),
            'long_description' => get_post_meta( $post->ID, '_long_description', true ),
            'features'     => get_post_meta( $post->ID, '_features', true ) ?: [],
        ];
    }
}
