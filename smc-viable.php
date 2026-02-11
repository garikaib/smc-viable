<?php
/**
 * Plugin Name: SMC Viable Quiz
 * Description: A React-powered quiz plugin with scored dropdowns and open-ended questions.
 * Version: 1.0.0
 * Author: SMC
 * Text Domain: smc-viable
 * Requires PHP: 8.2
 * Requires at least: 6.4
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
final class SMC_Quiz_Plugin {

	/**
	 * Plugin version.
	 */
	public const VERSION = '1.1.0';

	/**
	 * Instance of the class.
	 */
	private static ?self $instance = null;

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'init', [ $this, 'register_shortcodes' ] );
        add_action( 'init', function() {
            require_once __DIR__ . '/includes/class-hero-seeder.php';
            Hero_Seeder::seed_defaults();
        } );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once __DIR__ . '/includes/class-seeder.php';
            Seeder::register_commands();
        }
        
        // Load Shop and Training Managers
        require_once __DIR__ . '/includes/class-shop-cpt.php';
        require_once __DIR__ . '/includes/class-training-manager.php';
        require_once __DIR__ . '/includes/class-lms-db.php';
        require_once __DIR__ . '/includes/class-enrollment-manager.php';
        require_once __DIR__ . '/includes/class-lms-progress.php';
        require_once __DIR__ . '/includes/class-email-automation.php';
        require_once __DIR__ . '/includes/class-seo-manager.php';
        require_once __DIR__ . '/includes/class-content-manager.php';
        require_once __DIR__ . '/includes/api/class-instructor-controller.php';
        
        Shop_CPT::init();
        Training_Manager::init();
        LMS_DB::init();
        Email_Automation::init();
        SEO_Manager::init();
        Content_Manager::init();
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_account_scripts' ] );
        add_filter( 'nav_menu_item_title', [ $this, 'add_shop_menu_icon' ], 10, 2 );
	}

	/**
	 * Enqueue Account Dashboard Scripts.
	 */
	public function enqueue_account_scripts() {
		if ( ! is_page() || ! is_page_template( 'template-my-account.php' ) ) {
			// Check if slug is 'my-account' as fallback
			if ( ! is_page( 'my-account' ) ) {
				return;
			}
		}

		$asset_path = __DIR__ . '/build/account.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'smc-account-js',
			plugins_url( 'build/account.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'smc-account-css',
			plugins_url( 'build/style-account.css', __FILE__ ),
			[],
			$asset_file['version']
		);

		wp_localize_script( 'smc-account-js', 'smcAccountData', [
			'root'      => esc_url_raw( rest_url() ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'logoutUrl' => wp_logout_url( home_url() ),
			'baseUrl'   => home_url( '/my-account/' ),
		] );
	}

    /**
     * Add icon to Shop menu item.
     */
    public function add_shop_menu_icon( $title, $item ) {
        if ( trim( $item->title ) === 'Shop' ) {
            return '<i data-lucide="shopping-cart" class="smc-menu-icon" style="width: 1.2em; height: 1.2em; vertical-align: -0.2em; margin-right: 0.4em; display: inline-block;"></i>' . $title;
        }
        return $title;
    }

    /**
     * Enqueue Frontend Scripts.
     */
    public function enqueue_frontend_scripts() {
        // Enqueue Lucide for static icons in menus
        wp_enqueue_script( 'lucide-icons', 'https://unpkg.com/lucide@latest', [], '1.0.0', true );
        wp_add_inline_script( 'lucide-icons', 'document.addEventListener("DOMContentLoaded", function() { if(window.lucide) { lucide.createIcons(); } });' );
    }

	/**
	 * Register Admin Menu.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'SMC Hub', 'smc-viable' ),
			__( 'SMC Hub', 'smc-viable' ),
			'edit_posts',
			'smc-hub',
			[ $this, 'render_admin_page' ],
			'dashicons-superhero-alt',
			30
		);

        add_submenu_page(
            'smc-hub',
            __( 'Assessments', 'smc-viable' ),
            __( 'Assessments', 'smc-viable' ),
            'edit_posts',
            'smc-hub',
            [ $this, 'render_admin_page' ]
        );

        add_submenu_page(
            'smc-hub',
            __( 'Products', 'smc-viable' ),
            __( 'Products', 'smc-viable' ),
            'edit_posts',
            'smc-products',
            [ $this, 'render_admin_page' ]
        );

        add_submenu_page(
            'smc-hub',
            __( 'Orders', 'smc-viable' ),
            __( 'Orders', 'smc-viable' ),
            'edit_posts',
            'smc-orders',
            [ $this, 'render_admin_page' ]
        );

        add_submenu_page(
            'smc-hub',
            __( 'Leads', 'smc-viable' ),
            __( 'Leads', 'smc-viable' ),
            'edit_posts',
            'smc-leads',
            [ $this, 'render_admin_page' ]
        );
	}

	/**
	 * Render Admin Page HTML.
	 */
	public function render_admin_page(): void {
		echo '<div id="smc-quiz-admin-root"><h2>' . esc_html__( 'Loading Quiz Admin...', 'smc-viable' ) . '</h2></div>';
	}

	/**
	 * Register the Quiz Custom Post Type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'                  => _x( 'Quizzes', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Quiz', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Quizzes', 'smc-viable' ),
			'name_admin_bar'        => __( 'Quiz', 'smc-viable' ),
			'archives'              => __( 'Quiz Archives', 'smc-viable' ),
			'attributes'            => __( 'Quiz Attributes', 'smc-viable' ),
			'parent_item_colon'     => __( 'Parent Quiz:', 'smc-viable' ),
			'all_items'             => __( 'All Quizzes', 'smc-viable' ),
			'add_new_item'          => __( 'Add New Quiz', 'smc-viable' ),
			'add_new'               => __( 'Add New', 'smc-viable' ),
			'new_item'              => __( 'New Quiz', 'smc-viable' ),
			'edit_item'             => __( 'Edit Quiz', 'smc-viable' ),
			'update_item'           => __( 'Update Quiz', 'smc-viable' ),
			'view_item'             => __( 'View Quiz', 'smc-viable' ),
			'view_items'            => __( 'View Quizzes', 'smc-viable' ),
			'search_items'          => __( 'Search Quiz', 'smc-viable' ),
			'not_found'             => __( 'Not found', 'smc-viable' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'smc-viable' ),
			'featured_image'        => __( 'Featured Image', 'smc-viable' ),
			'set_featured_image'    => __( 'Set featured image', 'smc-viable' ),
			'remove_featured_image' => __( 'Remove featured image', 'smc-viable' ),
			'use_featured_image'    => __( 'Use as featured image', 'smc-viable' ),
			'insert_into_item'      => __( 'Insert into quiz', 'smc-viable' ),
			'uploaded_to_this_item' => __( 'Uploaded to this quiz', 'smc-viable' ),
			'items_list'            => __( 'Quizzes list', 'smc-viable' ),
			'items_list_navigation' => __( 'Quizzes list navigation', 'smc-viable' ),
			'filter_items_list'     => __( 'Filter quizzes list', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Quiz', 'smc-viable' ),
			'description'           => __( 'SMC Quizzes', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'custom-fields' ],
			'taxonomies'            => [],
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => false, // Hidden from default menu, using custom admin page
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true, // Important for Gutenberg and React Admin
		];

		register_post_type( 'smc_quiz', $args );
		$this->register_lead_post_type();
        $this->register_quiz_meta();
	}

    /**
     * Register Post Meta for Hero Section.
     */
    public function register_quiz_meta(): void {
        $meta_fields = [
            '_smc_quiz_hero_title'    => 'string',
            '_smc_quiz_hero_subtitle' => 'string',
            '_smc_quiz_hero_bg'       => 'string', // URL or ID
            '_smc_quiz_plan_level'    => 'string', // 'free', 'basic', 'premium'
        ];

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'smc_quiz', $key, [
                'type'         => $type,
                'single'       => true,
                'show_in_rest' => true,
            ] );
        }
    }
    
    // ... (rest of methods)
    
	public function register_lead_post_type(): void {
		$labels = [
			'name'                  => _x( 'Leads', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Lead', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Leads', 'smc-viable' ),
			'all_items'             => __( 'All Leads', 'smc-viable' ),
			'view_item'             => __( 'View Lead', 'smc-viable' ),
			'search_items'          => __( 'Search Leads', 'smc-viable' ),
			'not_found'             => __( 'No leads found', 'smc-viable' ),
			'items_list'            => __( 'Leads list', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Lead', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'custom-fields' ], // Title will be Name
			'taxonomies'            => [],
			'hierarchical'          => false,
			'public'                => false, // Internal use only
			'show_ui'               => true,  // Show in admin
			'show_in_menu'          => false, // We manually add it to submenu
			'menu_position'         => 10,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'show_in_rest'          => false, 
		];

		register_post_type( 'smc_lead', $args );
	}

	/**
	 * Register Block assets.
	 */
	public function register_blocks(): void {
		// Register the block. Points to src for now as build might not locate block.json without extra config
		// But in production we should access build. Let's try to assume src for metadata reading in dev.
		register_block_type( __DIR__ . '/src/blocks/quiz', [
			'render_callback' => [ $this, 'render_quiz_block' ],
		] );

        register_block_type( 'smc-viable/training-list', [
            'render_callback' => [ $this, 'render_training_list_shortcode' ],
            'attributes' => [
                'limit' => [ 'type' => 'number', 'default' => 6 ],
                'quiz'  => [ 'type' => 'number', 'default' => 0 ],
            ],
        ] );

        register_block_type( 'smc-viable/product-list', [
            'render_callback' => [ $this, 'render_product_list_shortcode' ],
            'attributes' => [
                'limit' => [ 'type' => 'number', 'default' => 6 ],
            ],
        ] );
	}

	/**
	 * Render the Quiz Block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_quiz_block( array $attributes ): string {
		$quiz_id = $attributes['quizId'] ?? 0;
		if ( ! $quiz_id ) {
			return '<p>' . __( 'Please select a quiz.', 'smc-viable' ) . '</p>';
		}

		// Enqueue the frontend view script
		// Note: 'view' entry point in webpack creates view.js
		$asset_file = include __DIR__ . '/build/view.asset.php';
		
		wp_enqueue_script(
			'smc-quiz-view',
			plugins_url( 'build/view.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
		
		wp_localize_script( 'smc-quiz-view', 'wpApiSettings', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
		
		wp_enqueue_style(
			'smc-quiz-view',
			plugins_url( 'build/view.css', __FILE__ ),
			[],
			$asset_file['version']
		);

		return sprintf(
			'<div class="smc-quiz-root" data-quiz-id="%d">Loading Quiz...</div>',
			esc_attr( $quiz_id )
		);
	}

	/**
	 * Register Shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'smc_quiz', [ $this, 'render_quiz_shortcode' ] );
        
        // Hook for Course Completion Emails
        add_action( 'smc_training_completed', function( $user_id, $course_id ) {
            $user = get_userdata( $user_id );
            if ( $user && class_exists( '\SMC\Viable\Email_Service' ) ) {
                \SMC\Viable\Email_Service::send_completion( $user, $course_id );
            }
        }, 10, 2 );

        add_shortcode( 'smc_shop', [ $this, 'render_shop_shortcode' ] );
        add_shortcode( 'smc_student_hub', [ $this, 'render_student_hub_shortcode' ] );
        add_shortcode( 'smc_instructor_hub', [ $this, 'render_instructor_hub_shortcode' ] );
        add_shortcode( 'smc_course_builder', [ $this, 'render_course_builder_shortcode' ] );
        add_shortcode( 'smc_training_list', [ $this, 'render_training_list_shortcode' ] );
        add_shortcode( 'smc_product_list', [ $this, 'render_product_list_shortcode' ] );
	}

	/**
	 * Render the Quiz Shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_quiz_shortcode( $atts ): string {
		$atts = shortcode_atts( [
			'id' => 0,
		], $atts, 'smc_quiz' );

		// Reuse logic from block renderer (it expects array with quizId)
		// We'll just manually call it or replicate the logic since it's simple.
		// Let's replicate for clarity and since block attrs keys differ slightly (camelCase vs snake_case).
		
		$quiz_id = (int) $atts['id'];
		
		if ( ! $quiz_id ) {
			return '<p>' . __( 'Please provide a quiz ID.', 'smc-viable' ) . '</p>';
		}

		// Enqueue the frontend view script same as block
		$asset_file = include __DIR__ . '/build/view.asset.php';
		
		wp_enqueue_script(
			'smc-quiz-view',
			plugins_url( 'build/view.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
		
		wp_localize_script( 'smc-quiz-view', 'wpApiSettings', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
		
		wp_enqueue_style(
			'smc-quiz-view',
			plugins_url( 'build/view.css', __FILE__ ),
			[],
			$asset_file['version']
		);

		return sprintf(
			'<div class="smc-quiz-root" data-quiz-id="%d">Loading Quiz...</div>',
			esc_attr( $quiz_id )
		);
	}

    /**
     * Render the Shop Shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_shop_shortcode( $atts ): string {
        $asset_path = __DIR__ . '/build/shop.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return '<p>Shop module not found (build missing).</p>';
        }

        $asset_file = include $asset_path;

        wp_enqueue_script(
            'smc-shop-js',
            plugins_url( 'build/shop.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_enqueue_style(
            'smc-shop-css',
            plugins_url( 'build/style-shop.css', __FILE__ ), // If styles exist
            [],
            $asset_file['version']
        );
        
        // Pass necessary data
        wp_localize_script( 'smc-shop-js', 'wpApiSettings', [
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ] );

        wp_localize_script( 'smc-shop-js', 'smcShopData', [
            'shop_url' => home_url( '/shop/' ),
        ] );

        return '<div id="smc-shop-root">Loading Shop...</div>';
    }

    /**
     * Render the Student Hub Shortcode.
     */
    public function render_student_hub_shortcode( $atts ): string {
        $asset_path = __DIR__ . '/build/student.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return '<p>Student Hub module not found (build missing).</p>';
        }

        $asset_file = include $asset_path;

        wp_enqueue_script(
            'smc-student-js',
            plugins_url( 'build/student.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_enqueue_style(
            'smc-student-css',
            plugins_url( 'build/style-student.css', __FILE__ ),
            [],
            $asset_file['version']
        );
        
        wp_localize_script( 'smc-student-js', 'wpApiSettings', [
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ] );

        return '<div id="smc-student-root">Loading Student Hub...</div>';
    }

    /**
     * Render the Instructor Hub Shortcode.
     */
    public function render_instructor_hub_shortcode( $atts ): string {
        // Access Control: Only for users with 'edit_posts' capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            return '<p>Access Denied. Instructor privileges required.</p>';
        }

        $asset_path = __DIR__ . '/build/instructor.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return '<p>Instructor Hub module not found (build missing).</p>';
        }

        $asset_file = include $asset_path;

        wp_enqueue_script(
            'smc-instructor-js',
            plugins_url( 'build/instructor.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_enqueue_style(
            'smc-instructor-css',
            plugins_url( 'build/style-instructor.css', __FILE__ ),
            [],
            $asset_file['version']
        );
        
        wp_localize_script( 'smc-instructor-js', 'wpApiSettings', [
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'siteName' => get_bloginfo( 'name' ),
            'siteLogo' => function_exists( 'get_custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '',
            'user' => [
                'name'   => wp_get_current_user()->display_name,
                'avatar' => get_avatar_url( get_current_user_id() ),
            ]
        ] );

        return '<div id="smc-instructor-root">Loading Instructor Hub...</div>';
    }

    /**
     * Render the Course Builder Shortcode.
     */
    public function render_course_builder_shortcode( $atts ): string {
        // Access Control: Only for users with 'edit_posts' capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            return '<p>Access Denied. Instructor privileges required.</p>';
        }

        // Reuse instructor bundle since it contains the builder code
        $asset_path = __DIR__ . '/build/instructor.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return '<p>Course Builder module not found (build missing).</p>';
        }

        $asset_file = include $asset_path;

        wp_enqueue_script(
            'smc-instructor-js',
            plugins_url( 'build/instructor.js', __FILE__ ),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_enqueue_style(
            'smc-instructor-css',
            plugins_url( 'build/style-instructor.css', __FILE__ ),
            [],
            $asset_file['version']
        );
        
        wp_localize_script( 'smc-instructor-js', 'wpApiSettings', [
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'siteName' => get_bloginfo( 'name' ),
            'siteLogo' => function_exists( 'get_custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '',
            'user' => [
                'name'   => wp_get_current_user()->display_name,
                'avatar' => get_avatar_url( get_current_user_id() ),
            ]
        ] );

        return '<div id="smc-course-builder-root" class="smc-premium-layout">Loading Course Builder...</div>';
    }

    /**
     * Render Training List Shortcode.
     */
    public function render_training_list_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'limit' => 6,
            'quiz'  => 0,
        ], $atts, 'smc_training_list' );

        $args = [
            'post_type'      => 'smc_training',
            'posts_per_page' => (int) $atts['limit'],
            'post_status'    => 'publish',
        ];

        if ( $atts['quiz'] ) {
            $args['meta_query'] = [
                [
                    'key'   => '_linked_quiz_id',
                    'value' => (int) $atts['quiz'],
                ]
            ];
        }

        $query = new \WP_Query( $args );
        if ( ! $query->have_posts() ) {
            return '<p>' . __( 'No training modules found.', 'smc-viable' ) . '</p>';
        }

        ob_start();
        echo '<div class="smc-training-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $quiz_id = get_post_meta( get_the_ID(), '_linked_quiz_id', true );
            $level = $quiz_id ? get_post_meta( $quiz_id, '_smc_quiz_plan_level', true ) : 'free';
            ?>
            <article class="smc-training-card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff;">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="smc-training-thumb"><?php the_post_thumbnail( 'medium' ); ?></div>
                <?php endif; ?>
                <div class="smc-training-content" style="padding: 15px;">
                    <span class="smc-badge" style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #eee; font-size: 0.8em; text-transform: uppercase; margin-bottom: 10px;">
                        <?php echo esc_html( $level ); ?>
                    </span>
                    <h4 style="margin: 0 0 10px;"><?php the_title(); ?></h4>
                    <p style="font-size: 0.9em; color: #666;"><?php echo wp_trim_words( get_the_excerpt(), 15 ); ?></p>
                    <a href="<?php the_permalink(); ?>" class="button" style="text-decoration: none; color: #007cba; font-weight: bold;"><?php _e( 'Learn More', 'smc-viable' ); ?></a>
                </div>
            </article>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Render Product List Shortcode.
     */
    public function render_product_list_shortcode( $atts ): string {
         $atts = shortcode_atts( [
            'limit' => 6,
        ], $atts, 'smc_product_list' );

        $args = [
            'post_type'      => 'smc_product',
            'posts_per_page' => (int) $atts['limit'],
            'post_status'    => 'publish',
        ];

        $query = new \WP_Query( $args );
        if ( ! $query->have_posts() ) {
            return '<p>' . __( 'No products found.', 'smc-viable' ) . '</p>';
        }

        ob_start();
        echo '<div class="smc-product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $price = get_post_meta( get_the_ID(), '_price', true );
            ?>
            <div class="smc-product-card" style="border: 1px solid #eee; padding: 20px; text-align: center; border-radius: 10px; background: #fdfdfd;">
                <h4 style="margin-bottom: 5px;"><?php the_title(); ?></h4>
                <div class="price" style="font-size: 1.5em; font-weight: bold; color: #222; margin-bottom: 15px;">$<?php echo esc_html( $price ); ?></div>
                <a href="<?php echo home_url('/shop'); ?>" class="button button-primary" style="display: block; background: #007cba; color: #fff; padding: 10px; border-radius: 5px; text-decoration: none;"><?php _e( 'Buy Now', 'smc-viable' ); ?></a>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
        require_once __DIR__ . '/includes/api/class-quiz-controller.php';
        require_once __DIR__ . '/includes/api/class-shop-controller.php';
        require_once __DIR__ . '/includes/api/class-student-controller.php';
        require_once __DIR__ . '/includes/api/class-course-controller.php';
        require_once __DIR__ . '/includes/class-seeder.php';
        
		$quiz_controller = new \SMC\Viable\API\Quiz_Controller();
		$quiz_controller->register_routes();
        
        $shop_controller = new \SMC\Viable\API\Shop_Controller();
        $shop_controller->register_routes();

        $student_controller = new \SMC\Viable\API\Student_Controller();
        $student_controller->register_routes();
        
        $course_controller = new \SMC\Viable\API\Course_Controller();
        $course_controller->register_routes();

        $instructor_controller = new \SMC\Viable\API\Instructor_Controller();
        $instructor_controller->register_routes();

        require_once __DIR__ . '/includes/api/class-account-controller.php';
        $account_controller = new \SMC\Viable\API\Account_Controller();
        $account_controller->register_routes();
	}

	/**
	 * Enqueue Admin Scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Load scripts on all SMC Hub admin pages
		$allowed_hooks = [
			'toplevel_page_smc-hub',
			'smc-hub_page_smc-products',
			'smc-hub_page_smc-orders',
			'smc-hub_page_smc-leads',
		];
		
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		// 'admin' entry point creates admin.asset.php
		$asset_path = __DIR__ . '/build/admin.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'smc-quiz-admin',
			plugins_url( 'build/admin.js', __FILE__ ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_localize_script( 'smc-quiz-admin', 'smcQuizSettings', [
			'root'    => esc_url_raw( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		] );

		wp_enqueue_style(
			'smc-quiz-admin',
			plugins_url( 'build/style-admin.css', __FILE__ ),
			[],
			$asset_file['version']
		);
	}
}

// Start the plugin.
SMC_Quiz_Plugin::get_instance();

/**
 * Rename menu items based on identity status.
 */
add_filter( 'wp_nav_menu_objects', function( $items ) {
	if ( ! is_user_logged_in() ) {
		return $items;
	}

	foreach ( $items as $item ) {
		if ( strpos( strtolower( $item->title ), 'free assessment' ) !== false ) {
			$item->title = 'Business Health Scorecard';
		}
	}

	return $items;
} );
