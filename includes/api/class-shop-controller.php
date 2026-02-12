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
use SMC\Viable\Enrollment_Manager;
use SMC\Viable\Plan_Tiers;

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

        // POST /smc/v1/shop/enroll
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/enroll',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'enroll_included_course' ],
                'permission_callback' => [ $this, 'enroll_permissions_check' ],
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
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $user_plan = 'free';
        $purchased_product_ids = [];
        $assessment_enrollments = [];

        if ( $user_id > 0 ) {
            Enrollment_Manager::reconcile_user_purchase_enrollments( $user_id );
            $user_plan = Enrollment_Manager::resolve_user_plan( $user_id );
            $purchased_product_ids = $this->get_user_purchased_product_ids( $user_id );
            $assessment_enrollments = $this->get_user_assessment_enrollments( $user_id );
        }

		$args = [
			'post_type'      => 'smc_product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];
		$posts = get_posts( $args );

		$data = [];
		foreach ( $posts as $post ) {
            $price = get_post_meta( $post->ID, '_price', true );
            $type = sanitize_key( (string) get_post_meta( $post->ID, '_product_type', true ) );
            $plan_level = Plan_Tiers::normalize_or_default( (string) get_post_meta( $post->ID, '_plan_level', true ), 'free' );
            $linked_course_id = $this->get_linked_course_id_from_product( (int) $post->ID );
            $is_course_product = ( $linked_course_id > 0 ) && ( 'plan' !== $type );
            $is_owned = false;

            if ( $user_id > 0 ) {
                if ( 'plan' === $type ) {
                    $is_owned = ( '' !== $plan_level && 'free' !== $plan_level && $this->user_matches_required_plan( $user_plan, $plan_level ) );
                } else {
                    $is_owned = ( $linked_course_id > 0 && Enrollment_Manager::is_enrolled( $user_id, $linked_course_id ) )
                        || in_array( (int) $post->ID, $purchased_product_ids, true );
                }
            }

            $course_access = $this->get_course_access_flags(
                $user_id,
                $user_plan,
                $linked_course_id,
                $type,
                $is_owned
            );
            
            $plan_status = [
                'is_higher'  => false,
                'is_lower'   => false,
                'is_current' => false,
            ];

            if ( 'plan' === $type && $user_id > 0 ) {
                $user_rank = Plan_Tiers::rank( $user_plan );
                $prod_rank = Plan_Tiers::rank( $plan_level );
                $plan_status['is_higher']  = $prod_rank > $user_rank;
                $plan_status['is_lower']   = $prod_rank < $user_rank;
                $plan_status['is_current'] = $prod_rank === $user_rank;
            }

			$data[] = [
                'id'          => $post->ID,
                'slug'        => $post->post_name,
                'title'       => $post->post_title,
                'description' => $post->post_content, // Or excerpt
                'price'       => (float) $price,
                'type'        => $type,
                'plan_level'  => ( 'plan' === sanitize_key( (string) $type ) ) ? $plan_level : '',
                'image'       => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: get_post_meta( $post->ID, '_smc_product_image_url', true ),
                'long_description' => get_post_meta( $post->ID, '_long_description', true ),
                'features'    => get_post_meta( $post->ID, '_features', true ) ?: [],
                'is_owned'    => $is_owned,
                'linked_course_id' => $linked_course_id,
                'is_course_product' => $is_course_product,
                'can_enroll_now' => $course_access['can_enroll_now'],
                'requires_upgrade' => $course_access['requires_upgrade'],
                'required_plan_levels' => $course_access['required_plan_levels'],
                'recommended_upgrade_plan_level' => $course_access['recommended_upgrade_plan_level'],
                'recommended_upgrade_plan_product_id' => $course_access['recommended_upgrade_plan_product_id'],
                'plan_status' => $plan_status,
			];
		}

		$quiz_args = [
			'post_type'      => 'smc_quiz',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		];
		$quiz_posts = get_posts( $quiz_args );
		foreach ( $quiz_posts as $quiz_post ) {
			$shop = get_post_meta( $quiz_post->ID, '_smc_quiz_shop', true );
			if ( is_string( $shop ) ) {
				$shop = json_decode( $shop, true );
			}
			if ( ! is_array( $shop ) || empty( $shop['enabled'] ) ) {
				continue;
			}

			$mode = sanitize_key( (string) ( $shop['access_mode'] ?? 'standalone' ) );
			$assigned_plan = Plan_Tiers::normalize_or_default( (string) ( $shop['assigned_plan'] ?? 'free' ), 'free' );
			$assessment_price = isset( $shop['price'] ) ? (float) $shop['price'] : 0.0;
			$assessment_url = '' !== $quiz_post->post_name ? home_url( '/' . $quiz_post->post_name . '/' ) : '';
			if ( '' === $assessment_url ) {
				$assessment_url = home_url( '/free-assessment/' );
			}

			$has_submission = false;
			if ( $user_id > 0 ) {
				global $wpdb;
				$table = $wpdb->prefix . 'smc_quiz_submissions';
				$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $table_exists ) {
					$has_submission = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND quiz_id = %d",
							$user_id,
							(int) $quiz_post->ID
						)
					) > 0;
				}
			}

			$has_plan_access = false;
			if ( in_array( $mode, [ 'plan', 'both' ], true ) ) {
				$has_plan_access = ( 'free' === $assigned_plan ) || $this->user_matches_required_plan( $user_plan, $assigned_plan );
			}

            $assessment_enrolled = $has_submission || in_array( (int) $quiz_post->ID, $assessment_enrollments, true );
            $assessment_can_enroll_now = ! $assessment_enrolled && $has_plan_access;
            $assessment_requires_upgrade = ! $assessment_enrolled
                && in_array( $mode, [ 'plan', 'both' ], true )
                && ! $has_plan_access
                && '' !== $assigned_plan
                && 'free' !== $assigned_plan;
            $assessment_upgrade_product_id = $assessment_requires_upgrade
                ? (int) $this->find_plan_product_id_by_level( $assigned_plan )
                : 0;

			$data[] = [
				'id'                => (int) $quiz_post->ID,
				'slug'              => 'assessment-' . $quiz_post->post_name,
				'title'             => $quiz_post->post_title,
				'description'       => wp_trim_words( wp_strip_all_tags( (string) $quiz_post->post_content ), 28 ),
				'price'             => $assessment_price,
				'type'              => 'assessment',
				'plan_level'        => $assigned_plan,
				'access_mode'       => $mode,
				'image'             => get_the_post_thumbnail_url( $quiz_post->ID, 'medium' ) ?: '',
				'long_description'  => (string) $quiz_post->post_content,
				'features'          => is_array( $shop['features'] ?? null ) ? $shop['features'] : [],
				'is_owned'          => $assessment_enrolled,
				'linked_course_id'  => 0,
				'assessment_url'    => esc_url_raw( $assessment_url ),
				'assessment_quiz_id'=> (int) $quiz_post->ID,
                'can_enroll_now' => $assessment_can_enroll_now,
                'requires_upgrade' => $assessment_requires_upgrade,
                'required_plan_levels' => $assessment_requires_upgrade ? [ $assigned_plan ] : [],
                'recommended_upgrade_plan_level' => $assessment_requires_upgrade ? $assigned_plan : '',
                'recommended_upgrade_plan_product_id' => $assessment_upgrade_product_id,
			];
		}

		return rest_ensure_response( $data );
	}

    /**
     * Enroll user in a course that is included in their current plan.
     */
    public function enroll_included_course( $request ) {
        $user_id = get_current_user_id();
        $product_id = (int) $request->get_param( 'product_id' );

        if ( $product_id <= 0 ) {
            return new WP_Error( 'invalid_product', 'Invalid product ID.', [ 'status' => 400 ] );
        }

        $product = get_post( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'product_not_found', 'Item not found.', [ 'status' => 404 ] );
        }

        if ( 'smc_quiz' === $product->post_type ) {
            return $this->enroll_assessment_via_plan( $user_id, (int) $product->ID );
        }

        if ( 'smc_product' !== $product->post_type ) {
            return new WP_Error( 'invalid_product_type', 'Unsupported item type.', [ 'status' => 400 ] );
        }

        $type = sanitize_key( (string) get_post_meta( $product_id, '_product_type', true ) );
        $course_id = $this->get_linked_course_id_from_product( $product_id );
        if ( $course_id <= 0 ) {
            return new WP_Error( 'missing_course', 'This product is not linked to a course.', [ 'status' => 400 ] );
        }

        if ( 'plan' === $type ) {
            return new WP_Error( 'invalid_product_type', 'Plan products cannot be enrolled directly.', [ 'status' => 400 ] );
        }

        if ( Enrollment_Manager::is_enrolled( $user_id, $course_id ) ) {
            return rest_ensure_response( [
                'success' => true,
                'already_enrolled' => true,
                'message' => 'Already enrolled.',
            ] );
        }

        if ( ! Enrollment_Manager::can_enroll_for_free( $user_id, $course_id ) ) {
            return new WP_Error(
                'not_included_in_plan',
                'This course is not included in your current plan.',
                [ 'status' => 403 ]
            );
        }

        $enrollment_id = Enrollment_Manager::enroll_user(
            $user_id,
            $course_id,
            'manual',
            [
                'source' => 'shop_plan_enroll',
                'product_id' => $product_id,
            ]
        );

        if ( ! $enrollment_id ) {
            return new WP_Error( 'enroll_failed', 'Could not enroll in course.', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'already_enrolled' => false,
            'message' => 'Enrolled successfully.',
        ] );
    }

    private function enroll_assessment_via_plan( int $user_id, int $quiz_id ) {
        $shop = get_post_meta( $quiz_id, '_smc_quiz_shop', true );
        if ( is_string( $shop ) ) {
            $shop = json_decode( $shop, true );
        }
        if ( ! is_array( $shop ) || empty( $shop['enabled'] ) ) {
            return new WP_Error( 'assessment_unavailable', 'Assessment is not available in shop.', [ 'status' => 400 ] );
        }

        $mode = sanitize_key( (string) ( $shop['access_mode'] ?? 'standalone' ) );
        $assigned_plan = Plan_Tiers::normalize_or_default( (string) ( $shop['assigned_plan'] ?? 'free' ), 'free' );
        $user_plan = Enrollment_Manager::resolve_user_plan( $user_id );
        $has_plan_access = in_array( $mode, [ 'plan', 'both' ], true )
            && ( 'free' === $assigned_plan || $this->user_matches_required_plan( $user_plan, $assigned_plan ) );

        $enrolled_ids = $this->get_user_assessment_enrollments( $user_id );
        if ( in_array( $quiz_id, $enrolled_ids, true ) ) {
            return rest_ensure_response( [
                'success' => true,
                'already_enrolled' => true,
                'message' => 'Already enrolled.',
            ] );
        }

        if ( ! $has_plan_access ) {
            return new WP_Error( 'not_included_in_plan', 'This assessment is not included in your current plan.', [ 'status' => 403 ] );
        }

        $enrolled_ids[] = $quiz_id;
        $enrolled_ids = array_values( array_unique( array_map( 'intval', $enrolled_ids ) ) );
        update_user_meta( $user_id, '_smc_assessment_enrollments', $enrolled_ids );

        return rest_ensure_response( [
            'success' => true,
            'already_enrolled' => false,
            'message' => 'Enrolled successfully.',
        ] );
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
            
            if ( ! $product ) {
                continue;
            }

            if ( 'smc_product' === $product->post_type ) {
                $price = (float) get_post_meta( $product_id, '_price', true );
                $total += $price;

                $order_items[] = [
                    'product_id' => $product_id,
                    'name'       => $product->post_title,
                    'price'      => $price,
                    'item_type'  => 'product',
                ];
                continue;
            }

            if ( 'smc_quiz' === $product->post_type ) {
                $shop = get_post_meta( $product_id, '_smc_quiz_shop', true );
                if ( is_string( $shop ) ) {
                    $shop = json_decode( $shop, true );
                }
                if ( ! is_array( $shop ) || empty( $shop['enabled'] ) ) {
                    continue;
                }

                $price = isset( $shop['price'] ) ? (float) $shop['price'] : 0.0;
                $total += $price;

                $order_items[] = [
                    'product_id' => $product_id,
                    'name'       => $product->post_title,
                    'price'      => $price,
                    'item_type'  => 'assessment',
                ];
            }
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
            $item_type = sanitize_key( (string) ( $item['item_type'] ?? 'product' ) );

            if ( 'assessment' === $item_type ) {
                $enrollments = $this->get_user_assessment_enrollments( (int) $user_id );
                $enrollments[] = (int) $product_id;
                $enrollments = array_values( array_unique( array_map( 'intval', $enrollments ) ) );
                update_user_meta( (int) $user_id, '_smc_assessment_enrollments', $enrollments );
                continue;
            }

            $type = sanitize_key( (string) get_post_meta( $product_id, '_product_type', true ) );
            
            if ( 'plan' === $type ) {
                $level = sanitize_key( (string) get_post_meta( $product_id, '_plan_level', true ) );
                if ( 'free' !== Plan_Tiers::normalize_or_default( $level, 'free' ) ) {
                    // Only explicit paid plans can change membership level.
                    update_user_meta( $user_id, '_smc_user_plan', Plan_Tiers::normalize_or_default( $level, 'free' ) );
                }
            } else {
                // Check for linked training or course (Standalone/Course)
                $training_id = (int) get_post_meta( $product_id, '_linked_training_id', true );
                if ( ! $training_id ) {
                    $training_id = (int) get_post_meta( $product_id, '_linked_course_id', true );
                }

                if ( $training_id ) {
                    \SMC\Viable\Enrollment_Manager::enroll_user( 
                        (int) $user_id, 
                        $training_id, 
                        'purchase', 
                        [ 'order_id' => $order_id, 'product_id' => $product_id ] 
                    );
                }
            }
        }
        
        // Ensure student role?
        $user = get_userdata( $user_id );
        if ( $user && ! in_array( 'subscriber', $user->roles ) && ! in_array( 'administrator', $user->roles ) ) {
             $user->add_role( 'subscriber' );
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
                'owned_product_ids' => [],
                'remedial' => [],
            ] );
        }

        $user_id = get_current_user_id();
        Enrollment_Manager::reconcile_user_purchase_enrollments( $user_id );
        $plan = $this->resolve_current_user_plan( $user_id );
        $owned_product_ids = $this->get_user_purchased_product_ids( $user_id );
        
        // Get remedial recommendations (Mock logic for now)
        // Find latest lead linked to this user email?
        // Or assume user is logged in and we track their quiz attempts via user_id in future.
        // For now, just return plan.
        
        return rest_ensure_response( [
            'user_id' => $user_id,
            'plan'    => $plan,
            'owned_product_ids' => $owned_product_ids,
            'enrollments' => \SMC\Viable\Enrollment_Manager::get_formatted_user_enrollments( $user_id ),
            'remedial' => [], // TODO: implementations
        ] );
    }

    private function get_linked_course_id_from_product( int $product_id ): int {
        $course_id = (int) get_post_meta( $product_id, '_linked_training_id', true );
        if ( $course_id <= 0 ) {
            $course_id = (int) get_post_meta( $product_id, '_linked_course_id', true );
        }

        // Fallback for legacy data: course points to product, but product meta was never backfilled.
        if ( $course_id <= 0 ) {
            $courses = get_posts( [
                'post_type'      => 'smc_training',
                'post_status'    => [ 'publish', 'private', 'draft' ],
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'   => '_linked_product_id',
                        'value' => $product_id,
                    ],
                ],
                'fields'         => 'ids',
            ] );

            if ( ! empty( $courses ) ) {
                $course_id = (int) $courses[0];
            }
        }

        return $course_id;
    }

    private function get_course_access_flags( int $user_id, string $user_plan, int $course_id, string $type, bool $is_owned ): array {
        $defaults = [
            'can_enroll_now' => false,
            'requires_upgrade' => false,
            'required_plan_levels' => [],
            'recommended_upgrade_plan_level' => '',
            'recommended_upgrade_plan_product_id' => 0,
        ];

        if ( $course_id <= 0 || 'plan' === $type ) {
            return $defaults;
        }

        $required_plans = $this->get_course_required_plan_levels( $course_id );
        $can_enroll_now = ( $user_id > 0 && ! $is_owned && Enrollment_Manager::can_enroll_for_free( $user_id, $course_id ) );

        $requires_upgrade = false;
        $recommended_upgrade_plan_level = '';

        if ( ! $is_owned && ! $can_enroll_now && $this->course_has_plan_mode( $course_id ) ) {
            if ( ! empty( $required_plans ) && ! in_array( 'free', $required_plans, true ) ) {
                $requires_upgrade = ! $this->user_matches_any_required_plan( $user_plan, $required_plans );
                if ( $requires_upgrade ) {
                    $recommended_upgrade_plan_level = $this->select_recommended_upgrade_plan_level( $user_plan, $required_plans );
                }
            }
        }

        $recommended_upgrade_plan_product_id = 0;
        if ( '' !== $recommended_upgrade_plan_level ) {
            $recommended_upgrade_plan_product_id = (int) $this->find_plan_product_id_by_level( $recommended_upgrade_plan_level );
        }

        return [
            'can_enroll_now' => $can_enroll_now,
            'requires_upgrade' => $requires_upgrade,
            'required_plan_levels' => $required_plans,
            'recommended_upgrade_plan_level' => $recommended_upgrade_plan_level,
            'recommended_upgrade_plan_product_id' => (int) $recommended_upgrade_plan_product_id,
        ];
    }

    private function get_course_required_plan_levels( int $course_id ): array {
        $required = [];
        $terms = wp_get_post_terms( $course_id, 'smc_plan_access', [ 'fields' => 'slugs' ] );

        if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
            foreach ( $terms as $slug ) {
                $normalized = $this->normalize_plan_slug( (string) $slug );
                if ( '' !== $normalized ) {
                    $required[] = $normalized;
                }
            }
        }

        if ( empty( $required ) ) {
            $legacy = $this->normalize_plan_slug( (string) get_post_meta( $course_id, '_plan_level', true ) );
            if ( '' !== $legacy ) {
                $required[] = $legacy;
            }
        }

        return array_values( array_unique( $required ) );
    }

    private function course_has_plan_mode( int $course_id ): bool {
        $terms = wp_get_post_terms( $course_id, 'smc_access_mode', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && is_array( $terms ) && in_array( 'plan', $terms, true ) ) {
            return true;
        }

        $meta_modes = get_post_meta( $course_id, '_smc_access_modes', true );
        if ( is_array( $meta_modes ) && in_array( 'plan', array_map( 'sanitize_key', $meta_modes ), true ) ) {
            return true;
        }

        $legacy_mode = sanitize_key( (string) get_post_meta( $course_id, '_access_type', true ) );
        if ( 'plan' === $legacy_mode ) {
            return true;
        }

        return ! empty( $this->get_course_required_plan_levels( $course_id ) );
    }

    private function normalize_plan_slug( string $plan ): string {
        return Plan_Tiers::normalize( $plan );
    }

    private function plan_rank( string $plan ): int {
        return Plan_Tiers::rank( $plan );
    }

    private function user_matches_required_plan( string $user_plan, string $required_plan ): bool {
        return $this->plan_rank( $user_plan ) >= $this->plan_rank( $required_plan );
    }

    private function user_matches_any_required_plan( string $user_plan, array $required_plans ): bool {
        $user_rank = $this->plan_rank( $user_plan );
        foreach ( $required_plans as $required_plan ) {
            $required_rank = $this->plan_rank( (string) $required_plan );
            if ( $user_rank >= $required_rank ) {
                return true;
            }
        }
        return false;
    }

    private function select_recommended_upgrade_plan_level( string $user_plan, array $required_plans ): string {
        $normalized = array_values( array_unique( array_filter( array_map( [ $this, 'normalize_plan_slug' ], $required_plans ) ) ) );
        if ( empty( $normalized ) ) {
            return '';
        }

        usort( $normalized, function ( string $a, string $b ): int {
            return $this->plan_rank( $a ) <=> $this->plan_rank( $b );
        } );

        $user_rank = $this->plan_rank( $user_plan );
        foreach ( $normalized as $plan ) {
            if ( $this->plan_rank( $plan ) > $user_rank ) {
                return $plan;
            }
        }

        return end( $normalized ) ?: '';
    }

    private function find_plan_product_id_by_level( string $plan_level ): int {
        $plan_level = $this->normalize_plan_slug( $plan_level );
        if ( '' === $plan_level ) {
            return 0;
        }

        $plan_products = get_posts( [
            'post_type'      => 'smc_product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_product_type',
                    'value' => 'plan',
                ],
                [
                    'key'   => '_plan_level',
                    'value' => $plan_level,
                ],
            ],
            'fields' => 'ids',
        ] );

        return ! empty( $plan_products ) ? (int) $plan_products[0] : 0;
    }

    private function get_user_purchased_product_ids( int $user_id ): array {
        $orders = get_posts( [
            'post_type'      => 'smc_order',
            'post_status'    => 'any',
            'posts_per_page' => 100,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => '_customer_id',
                    'value' => $user_id,
                ],
            ],
            'fields'         => 'ids',
        ] );

        $product_ids = [];
        foreach ( $orders as $order_id ) {
            $order_status = sanitize_key( (string) get_post_meta( (int) $order_id, '_order_status', true ) );
            if ( ! in_array( $order_status, [ 'completed', 'paid', 'processing', 'active' ], true ) ) {
                continue;
            }

            $items = get_post_meta( (int) $order_id, '_order_items', true );
            if ( ! is_array( $items ) ) {
                continue;
            }

            if ( isset( $items['product_id'] ) || isset( $items['id'] ) ) {
                $items = [ $items ];
            }

            foreach ( $items as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $product_id = 0;
                if ( isset( $item['product_id'] ) ) {
                    $product_id = (int) $item['product_id'];
                } elseif ( isset( $item['id'] ) ) {
                    $product_id = (int) $item['id'];
                }

                if ( $product_id > 0 ) {
                    $product_ids[] = $product_id;
                }
            }
        }

        return array_values( array_unique( array_map( 'intval', $product_ids ) ) );
    }

    private function get_user_assessment_enrollments( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return [];
        }

        $raw = get_user_meta( $user_id, '_smc_assessment_enrollments', true );
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $ids = array_values( array_unique( array_map( 'intval', $raw ) ) );
        return array_values( array_filter( $ids, static function ( int $id ): bool {
            return $id > 0;
        } ) );
    }

    /**
     * Resolve current user plan from latest paid/active plan order.
     * Falls back to persisted user meta when no qualifying plan order exists.
     */
    private function resolve_current_user_plan( int $user_id ): string {
        return Enrollment_Manager::resolve_user_plan( $user_id );
    }

    /**
     * Permissions Check.
     */
    public function checkout_permissions_check( $request ) {
        return is_user_logged_in();
    }

    public function enroll_permissions_check( $request ) {
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
        $type = sanitize_key( (string) $type );
        update_post_meta( $post_id, '_price', (float) $price );
        update_post_meta( $post_id, '_product_type', $type );

        if ( 'plan' === $type ) {
            $normalized_plan = Plan_Tiers::normalize_or_default( (string) $plan_level, 'free' );
            update_post_meta( $post_id, '_plan_level', $normalized_plan );
        } else {
            delete_post_meta( $post_id, '_plan_level' );
        }

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
            'plan_level'   => ( 'plan' === sanitize_key( (string) get_post_meta( $post->ID, '_product_type', true ) ) )
                ? get_post_meta( $post->ID, '_plan_level', true )
                : '',
            'image'        => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: get_post_meta( $post->ID, '_smc_product_image_url', true ),
            'long_description' => get_post_meta( $post->ID, '_long_description', true ),
            'features'     => get_post_meta( $post->ID, '_features', true ) ?: [],
        ];
    }
}
