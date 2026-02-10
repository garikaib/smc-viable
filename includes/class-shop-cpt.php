<?php
/**
 * Shop Custom Post Types Manager.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shop_CPT
 */
class Shop_CPT {

	/**
	 * Init Hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_product_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_order_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_product_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_order_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_lesson_cpt' ] );
        add_action( 'init', [ __CLASS__, 'register_shop_meta' ] );
	}

	/**
	 * Register Product CPT.
	 */
	public static function register_product_cpt(): void {
		$labels = [
			'name'                  => _x( 'Products', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Product', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Products', 'smc-viable' ),
            'add_new'               => __( 'Add New Product', 'smc-viable' ),
            'edit_item'             => __( 'Edit Product', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Product', 'smc-viable' ),
			'description'           => __( 'SMC Shop Products', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => false, // Handled by SMC Hub SPA
			'capability_type'       => 'post',
			'show_in_rest'          => true,
            'map_meta_cap'          => true,
		];

		register_post_type( 'smc_product', $args );
	}

	/**
	 * Register Order CPT.
	 */
	public static function register_order_cpt(): void {
		$labels = [
			'name'                  => _x( 'Orders', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Order', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Orders', 'smc-viable' ),
            'search_items'          => __( 'Search Orders', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Order', 'smc-viable' ),
			'description'           => __( 'SMC Shop Orders', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'custom-fields' ], // Title is Order ID
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => false, // Handled by SMC Hub SPA
            'capabilities'          => [
                'create_posts' => 'do_not_allow', // Orders are created programmatically
            ],
            'map_meta_cap'          => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
		];

		register_post_type( 'smc_order', $args );
	}

	/**
	 * Register Lesson CPT.
	 */
	public static function register_lesson_cpt(): void {
		$labels = [
			'name'                  => _x( 'Lessons', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Lesson', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Lessons', 'smc-viable' ),
            'add_new'               => __( 'Add New Lesson', 'smc-viable' ),
            'edit_item'             => __( 'Edit Lesson', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Lesson', 'smc-viable' ),
			'description'           => __( 'Course Lessons', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true, // Visible in admin for now
			'capability_type'       => 'post',
			'show_in_rest'          => true,
            'map_meta_cap'          => true,
		];

		register_post_type( 'smc_lesson', $args );
	}

    /**
     * Register Meta Fields.
     */
    public static function register_shop_meta(): void {
        // Product Meta
        register_post_meta( 'smc_product', '_price', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_product', '_product_type', [
            'type'         => 'string', // 'plan', 'single'
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        // If type is 'plan', which level does it grant?
        register_post_meta( 'smc_product', '_plan_level', [
            'type'         => 'string', // 'basic', 'premium'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // If type is 'single', what does it unlock? (e.g., specific Training ID)
         register_post_meta( 'smc_product', '_linked_training_id', [
            'type'         => 'integer', 
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Rich Product Data
        register_post_meta( 'smc_product', '_long_description', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_product', '_features', [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
        ] );

        // LMS Course Meta
        register_post_meta( 'smc_product', '_course_sections', [
            'type'         => 'object', // JSON array of sections
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title'   => [ 'type' => 'string' ],
                            'lessons' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        ],
                    ],
                ],
            ],
        ] );

        register_post_meta( 'smc_product', '_prerequisite_course_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_product', '_course_average_rating', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Lesson Meta
        register_post_meta( 'smc_lesson', '_lesson_type', [
            'type'         => 'string', // 'video', 'text', 'quiz', 'assignment'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_lesson', '_lesson_video_url', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        register_post_meta( 'smc_lesson', '_lesson_quiz_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

         register_post_meta( 'smc_lesson', '_lesson_duration', [ // Minutes
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_lesson', '_parent_course_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Order Meta
        register_post_meta( 'smc_order', '_customer_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_order', '_order_total', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_order', '_order_status', [
            'type'         => 'string', // 'pending', 'completed', 'failed'
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        register_post_meta( 'smc_order', '_order_items', [
            'type'         => 'object', // Array of items
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [ 'type' => 'integer' ],
                            'price'      => [ 'type' => 'number' ],
                            'name'       => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
        ] );
    }
}
