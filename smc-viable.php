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
	public const VERSION = '1.2.1';

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
		add_action( 'init', [ $this, 'add_shop_slug_rewrite_rules' ] );
		add_action( 'init', [ $this, 'add_learning_slug_rewrite_rules' ] );
		add_action( 'init', [ $this, 'add_account_rewrite_rules' ] );
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules_for_shop_slugs' ], 99 );
		add_action( 'wp_ajax_smc_refresh_rest_nonce', [ $this, 'ajax_refresh_rest_nonce' ] );
        add_action( 'init', function() {
            require_once __DIR__ . '/includes/class-hero-seeder.php';
            Hero_Seeder::seed_defaults();
        } );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once __DIR__ . '/includes/class-plan-tiers.php';
            require_once __DIR__ . '/includes/class-seeder.php';
            Seeder::register_commands();
        }
        
        require_once __DIR__ . '/includes/class-plan-tiers.php';
        Plan_Tiers::ensure_default_levels();
        // Load Shop and Training Managers
        require_once __DIR__ . '/includes/class-shop-cpt.php';
        require_once __DIR__ . '/includes/class-training-manager.php';
        require_once __DIR__ . '/includes/class-lms-db.php';
        require_once __DIR__ . '/includes/class-enrollment-manager.php';
        require_once __DIR__ . '/includes/class-lms-progress.php';
        require_once __DIR__ . '/includes/class-quiz-question-schema.php';
        require_once __DIR__ . '/includes/class-quiz-grader.php';
        require_once __DIR__ . '/includes/class-email-automation.php';
        require_once __DIR__ . '/includes/class-seo-manager.php';
        require_once __DIR__ . '/includes/class-content-manager.php';
        require_once __DIR__ . '/includes/class-account-documents.php';
        require_once __DIR__ . '/includes/api/class-instructor-controller.php';
        
	        Shop_CPT::init();
	        Training_Manager::init();
	        LMS_DB::init();
	        add_action( 'init', [ Enrollment_Manager::class, 'run_plan_data_cleanup_once' ], 25 );
	        Email_Automation::init();
	        SEO_Manager::init();
        Content_Manager::init();
        Account_Documents::init();
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_account_scripts' ] );
        add_filter( 'nav_menu_item_title', [ $this, 'add_shop_menu_icon' ], 10, 2 );
        add_filter( 'authenticate', [ $this, 'block_disabled_user_authentication' ], 100, 3 );
        add_action( 'init', [ $this, 'enforce_disabled_user_sessions' ], 1 );
        add_filter( 'login_message', [ $this, 'inject_disabled_login_notice' ] );
        add_action( 'admin_init', [ $this, 'restrict_student_admin_access' ] );
        add_filter( 'show_admin_bar', [ $this, 'filter_student_admin_bar' ] );
		add_action( 'user_register', [ $this, 'claim_guest_assessment_reports' ] );
		add_filter( 'query_vars', [ $this, 'register_shop_query_var' ] );
		add_filter( 'redirect_canonical', [ $this, 'maybe_disable_shop_slug_canonical_redirect' ], 10, 2 );
	}

	/**
	 * Add rewrite rules on plugin activation.
	 */
	public static function activate(): void {
		self::add_shop_slug_rewrite_rule_internal();
		self::add_learning_slug_rewrite_rule_internal();
		self::add_account_rewrite_rules_internal();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Register rewrite rules for shop product slug routes.
	 */
	public function add_shop_slug_rewrite_rules(): void {
		self::add_shop_slug_rewrite_rule_internal();
	}

	/**
	 * Register rewrite rules for learning course slug routes.
	 */
	public function add_learning_slug_rewrite_rules(): void {
		self::add_learning_slug_rewrite_rule_internal();
	}

	/**
	 * Register rewrite rules for my-account sub-routes.
	 */
	public function add_account_rewrite_rules(): void {
		self::add_account_rewrite_rules_internal();
	}

	/**
	 * Flush rewrite rules one time per plugin version so new shop slug routes work immediately.
	 */
	public function maybe_flush_rewrite_rules_for_shop_slugs(): void {
		$version_key = 'smc_viable_rewrite_version';
		$stored      = (string) get_option( $version_key, '' );

		if ( self::VERSION === $stored ) {
			return;
		}

		self::add_shop_slug_rewrite_rule_internal();
		self::add_learning_slug_rewrite_rule_internal();
		self::add_account_rewrite_rules_internal();
		flush_rewrite_rules( false );
		update_option( $version_key, self::VERSION, false );
	}

	/**
	 * Register custom query var for shop product slugs.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string>
	 */
	public function register_shop_query_var( array $vars ): array {
		$vars[] = 'smc_shop_product';
		$vars[] = 'smc_learning_course';
		$vars[] = 'smc_account_action';
		$vars[] = 'smc_account_order_id';
		$vars[] = 'smc_view';
		$vars[] = 'smc_quiz';
		$vars[] = 'quiz_id';
		$vars[] = 'smc_slug';
		return $vars;
	}

	/**
	 * Prevent canonical redirects from stripping valid shop product slug routes.
	 *
	 * @param string|false $redirect_url Proposed redirect URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function maybe_disable_shop_slug_canonical_redirect( $redirect_url, string $requested_url ) {
		if ( '' !== (string) get_query_var( 'smc_shop_product', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'smc_learning_course', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'smc_account_action', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'smc_view', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'smc_quiz', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'quiz_id', '' ) ) {
			return false;
		}
		if ( '' !== (string) get_query_var( 'smc_slug', '' ) ) {
			return false;
		}

		return $redirect_url;
	}


	/**
	 * Build and register the rewrite rule for /shop/{product-slug}/.
	 */
	private static function add_shop_slug_rewrite_rule_internal(): void {
		$shop_path = self::get_shop_page_path();
		if ( '' === $shop_path ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $shop_path, '#' ) . '/([^/]+)/?$',
			'index.php?pagename=' . $shop_path . '&smc_shop_product=$matches[1]',
			'top'
		);
	}

	/**
	 * Build and register the rewrite rule for /learning/{course-slug}/.
	 */
	private static function add_learning_slug_rewrite_rule_internal(): void {
		$learning_path = self::get_learning_page_path();
		if ( '' === $learning_path ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $learning_path, '#' ) . '/([^/]+)/?$',
			'index.php?pagename=' . $learning_path . '&smc_learning_course=$matches[1]',
			'top'
		);
	}

	/**
	 * Build and register rewrite rules for /my-account/view-order/{id}/ and /my-account/invoice/{id}/.
	 */
	private static function add_account_rewrite_rules_internal(): void {
		$account_path = self::get_my_account_page_path();
		if ( '' === $account_path ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $account_path, '#' ) . '/(view-order|invoice)/([0-9]+)/?$',
			'index.php?pagename=' . $account_path . '&smc_account_action=$matches[1]&smc_account_order_id=$matches[2]',
			'top'
		);
	}

	/**
	 * Resolve the shop page path.
	 */
	private static function get_shop_page_path(): string {
		$shop_page = get_page_by_path( 'shop' );
		if ( $shop_page instanceof \WP_Post ) {
			$uri = trim( (string) get_page_uri( $shop_page ), '/' );
			if ( '' !== $uri ) {
				return $uri;
			}
		}

		return 'shop';
	}

	/**
	 * Resolve the learning page path.
	 */
	private static function get_learning_page_path(): string {
		$learning_page = get_page_by_path( 'learning' );
		if ( $learning_page instanceof \WP_Post ) {
			$uri = trim( (string) get_page_uri( $learning_page ), '/' );
			if ( '' !== $uri ) {
				return $uri;
			}
		}

		return 'learning';
	}

	/**
	 * Resolve the my-account page path.
	 */
	private static function get_my_account_page_path(): string {
		$account_page = get_page_by_path( 'my-account' );
		if ( $account_page instanceof \WP_Post ) {
			$uri = trim( (string) get_page_uri( $account_page ), '/' );
			if ( '' !== $uri ) {
				return $uri;
			}
		}

		return 'my-account';
	}

	/**
	 * Check if account is marked disabled.
	 */
	private function is_user_disabled( int $user_id ): bool {
		return '1' === (string) get_user_meta( $user_id, '_smc_user_disabled', true );
	}

	/**
	 * Block disabled users from authenticating.
	 */
	public function block_disabled_user_authentication( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
			return $user;
		}

		if ( $this->is_user_disabled( (int) $user->ID ) ) {
			return new \WP_Error(
				'smc_user_disabled',
				__( '<strong>Access denied.</strong> Your account has been disabled. Please contact support.', 'smc-viable' )
			);
		}

		return $user;
	}

	/**
	 * Immediately terminate active sessions for disabled users.
	 */
	public function enforce_disabled_user_sessions(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$current_user_id = get_current_user_id();
		if ( $current_user_id <= 0 || ! $this->is_user_disabled( $current_user_id ) ) {
			return;
		}

		wp_logout();

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$login_url = add_query_arg( 'smc_disabled', '1', wp_login_url() );
		wp_safe_redirect( $login_url );
		exit;
	}

	/**
	 * Show a disabled-account notice on wp-login.php.
	 */
	public function inject_disabled_login_notice( string $message ): string {
		if ( ! isset( $_GET['smc_disabled'] ) ) {
			return $message;
		}

		$notice  = '<div id="login_error">';
		$notice .= esc_html__( 'Your account has been disabled. Please contact support.', 'smc-viable' );
		$notice .= '</div>';

		return $message . $notice;
	}

	/**
	 * Check whether current user should be treated as a student.
	 */
	private function is_student_user(): bool {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof \WP_User ) || 0 === (int) $user->ID ) {
			return false;
		}

		$roles = (array) $user->roles;
		return in_array( 'subscriber', $roles, true ) || in_array( 'student', $roles, true );
	}

	/**
	 * Restrict students from the admin dashboard.
	 */
	public function restrict_student_admin_access(): void {
		if ( ! is_user_logged_in() || ! $this->is_student_user() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		$student_hub = get_page_by_path( 'student-hub' );
		$redirect_to = ( $student_hub instanceof \WP_Post ) ? get_permalink( $student_hub ) : home_url( '/' );

		if ( ! is_string( $redirect_to ) || '' === $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Hide admin bar for student accounts.
	 */
	public function filter_student_admin_bar( bool $show ): bool {
		if ( $this->is_student_user() ) {
			return false;
		}

		return $show;
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
			'loginUrl'  => wp_login_url( home_url( '/my-account/' ) ),
			'registerUrl' => add_query_arg( 'redirect_to', home_url( '/my-account/' ), wp_registration_url() ),
			'currentUserId' => get_current_user_id(),
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
            '_smc_quiz_plan_level'    => 'string', // 'free', 'basic', 'standard'
        ];

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'smc_quiz', $key, [
                'type'         => $type,
                'single'       => true,
                'show_in_rest' => true,
            ] );
        }

		register_post_meta(
			'smc_quiz',
			'_smc_quiz_shop',
			[
				'type'         => 'object',
				'single'       => true,
				'show_in_rest' => true,
			]
		);
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
		$block_path = __DIR__ . '/build/blocks/quiz';
		if ( ! file_exists( $block_path . '/block.json' ) ) {
			$block_path = __DIR__ . '/src/blocks/quiz';
		}

		register_block_type( $block_path, [
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
			'isLoggedIn' => is_user_logged_in(),
			'loginUrl'   => wp_login_url( home_url( '/my-account/' ) ),
			'registerUrl' => wp_registration_url(),
		] );
		
		wp_enqueue_style(
			'smc-quiz-view',
			plugins_url( 'build/view.css', __FILE__ ),
			[],
			$asset_file['version']
		);
		wp_enqueue_style(
			'smc-quiz-view-style',
			plugins_url( 'build/style-view.css', __FILE__ ),
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
		add_shortcode( 'smc_assessment_center', [ $this, 'render_assessment_center_shortcode' ] );
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
			'isLoggedIn' => is_user_logged_in(),
			'loginUrl'   => wp_login_url( home_url( '/my-account/' ) ),
			'registerUrl' => wp_registration_url(),
		] );
		
		wp_enqueue_style(
			'smc-quiz-view',
			plugins_url( 'build/view.css', __FILE__ ),
			[],
			$asset_file['version']
		);
		wp_enqueue_style(
			'smc-quiz-view-style',
			plugins_url( 'build/style-view.css', __FILE__ ),
			[],
			$asset_file['version']
		);

		return sprintf(
			'<div class="smc-quiz-root" data-quiz-id="%d">Loading Quiz...</div>',
			esc_attr( $quiz_id )
		);
	}

	/**
	 * Render assessment center (frontend quiz manager).
	 */
	public function render_assessment_center_shortcode( $atts ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '<p>' . esc_html__( 'Assessment Center is restricted to authorized team members.', 'smc-viable' ) . '</p>';
		}

		$asset_path = __DIR__ . '/build/admin.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return '<p>Assessment Center module not found (build missing).</p>';
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
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
            'planTiers' => Plan_Tiers::get_level_options(),
		] );

		wp_enqueue_style(
			'smc-quiz-admin',
			plugins_url( 'build/style-admin.css', __FILE__ ),
			[],
			$asset_file['version']
		);

		return '<div id="smc-quiz-admin-root"><h2>' . esc_html__( 'Loading Assessment Center...', 'smc-viable' ) . '</h2></div>';
	}

	/**
	 * Link guest assessment reports to newly registered users.
	 */
	public function claim_guest_assessment_reports( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$email = sanitize_email( (string) $user->user_email );
		if ( '' === $email ) {
			return;
		}

		$reports = get_posts(
			[
				'post_type'      => 'smc_lead',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_smc_lead_email',
						'value' => $email,
					],
				],
			]
		);

		foreach ( $reports as $report_id ) {
			$current_user_link = (int) get_post_meta( $report_id, '_smc_lead_user_id', true );
			if ( $current_user_link > 0 ) {
				continue;
			}

			update_post_meta( $report_id, '_smc_lead_user_id', $user_id );

			$quiz_id = (int) get_post_meta( $report_id, '_smc_lead_quiz_id', true );
			$score_data = json_decode( (string) get_post_meta( $report_id, '_smc_lead_score_data', true ), true );
			$score = is_array( $score_data ) ? (int) ( $score_data['total_score'] ?? 0 ) : 0;
			$answers = is_array( $score_data ) ? (array) ( $score_data['answers'] ?? [] ) : [];

			if ( $quiz_id > 0 ) {
				global $wpdb;
				$table = $wpdb->prefix . LMS_DB::TABLE_QUIZ_SUBMISSIONS;
				$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				if ( $table_exists ) {
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
		}
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
            'shop_url'        => get_permalink( get_queried_object_id() ) ?: home_url( '/shop/' ),
            'account_url'     => home_url( '/my-account/' ),
            'login_url'       => wp_login_url( home_url( '/my-account/' ) ),
            'register_url'    => add_query_arg( 'redirect_to', home_url( '/my-account/' ), wp_registration_url() ),
            'current_user_id' => get_current_user_id(),
            'planTiers'       => Plan_Tiers::get_level_labels(),
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
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'currentCourseSlug' => sanitize_title( (string) get_query_var( 'smc_learning_course', '' ) ),
        ] );

        return '<div id="smc-student-root">Loading Student Hub...</div>';
    }

	/**
	 * Refresh the current user's REST nonce for frontend retries.
	 */
	public function ajax_refresh_rest_nonce(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in.' ], 401 );
		}

		wp_send_json_success(
			[
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'user_id' => get_current_user_id(),
			]
		);
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

        wp_enqueue_media();

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
            'planTiers' => Plan_Tiers::get_level_options(),
            'user' => [
                'id'     => get_current_user_id(),
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

        wp_enqueue_media();

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
            'planTiers' => Plan_Tiers::get_level_options(),
            'user' => [
                'id'     => get_current_user_id(),
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

        require_once __DIR__ . '/includes/api/class-notes-controller.php';
        $notes_controller = new \SMC\Viable\API\Notes_Controller();
        $notes_controller->register_routes();

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
            'planTiers' => Plan_Tiers::get_level_options(),
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
register_activation_hook( __FILE__, [ SMC_Quiz_Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ SMC_Quiz_Plugin::class, 'deactivate' ] );
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
