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
	public const VERSION = '1.0.0';

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
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Register Admin Menu.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'SMC Quiz', 'smc-viable' ),
			__( 'SMC Quiz', 'smc-viable' ),
			'edit_posts',
			'smc-quiz',
			[ $this, 'render_admin_page' ],
			'dashicons-clipboard',
			30
		);

        // Add "Dashboard" submenu (same as parent) to prevent "SMC Quiz" appearing twice if submenu is added
        add_submenu_page(
            'smc-quiz',
            __( 'Dashboard', 'smc-viable' ),
            __( 'Dashboard', 'smc-viable' ),
            'edit_posts',
            'smc-quiz',
            [ $this, 'render_admin_page' ]
        );

        // Add "Leads" Submenu
        add_submenu_page(
            'smc-quiz',
            __( 'Leads', 'smc-viable' ),
            __( 'Leads', 'smc-viable' ),
            'edit_posts',
            'edit.php?post_type=smc_lead'
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
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		require_once __DIR__ . '/includes/api/class-quiz-controller.php';
        require_once __DIR__ . '/includes/class-seeder.php';
		$controller = new \SMC\Viable\API\Quiz_Controller();
		$controller->register_routes();
	}

	/**
	 * Enqueue Admin Scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'toplevel_page_smc-quiz' !== $hook ) {
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
