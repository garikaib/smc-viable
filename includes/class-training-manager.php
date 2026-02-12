<?php
/**
 * Training Manager Class.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Training_Manager
 */
class Training_Manager {

	/**
	 * Init Hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_training_cpt' ] );
        add_action( 'init', [ __CLASS__, 'register_training_meta' ] );
        add_action( 'init', [ __CLASS__, 'register_access_level_taxonomy' ] );
        add_action( 'template_redirect', [ __CLASS__, 'redirect_training_to_learning_player' ] );
        
        // Attempt to disable Avada (Fusion Builder) for this type.
        // Avada typically checks 'fusion_settings' or filters 'fusion_builder_allowed_post_types'.
        add_filter( 'fusion_builder_allowed_post_types', [ __CLASS__, 'disable_avada_for_training' ] );
        
        // Content restriction
        add_filter( 'the_content', [ __CLASS__, 'restrict_content' ] );
	}

	/**
	 * Register Training CPT (The Course Object).
	 */
	public static function register_training_cpt(): void {
		$labels = [
			'name'                  => _x( 'Training Programs', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Training Program', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Training', 'smc-viable' ),
			'add_new'               => __( 'Add New Program', 'smc-viable' ),
			'edit_item'             => __( 'Edit Program', 'smc-viable' ),
			'view_item'             => __( 'View Program', 'smc-viable' ),
			'search_items'          => __( 'Search Programs', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Training', 'smc-viable' ),
			'description'           => __( 'SMC Training Programs', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'smc-hub', // Move under SMC Hub
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'show_in_rest'          => true,
			'has_archive'           => true,
			'rewrite'               => [ 'slug' => 'training' ],
			'map_meta_cap'          => true,
		];

		register_post_type( 'smc_training', $args );
	}

    /**
     * Register Taxonomy.
     */
    public static function register_access_level_taxonomy(): void {
        // Access modes can be combined in Gutenberg (e.g. plan + standalone).
        register_taxonomy(
            'smc_access_mode',
            [ 'smc_training' ],
            [
                'labels' => [
                    'name'          => __( 'Access Modes', 'smc-viable' ),
                    'singular_name' => __( 'Access Mode', 'smc-viable' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'hierarchical'      => false,
                'rewrite'           => false,
            ]
        );

        // Explicit plan tags for course entitlement (free, basic, standard, etc.).
        register_taxonomy(
            'smc_plan_access',
            [ 'smc_training' ],
            [
                'labels' => [
                    'name'          => __( 'Plan Access', 'smc-viable' ),
                    'singular_name' => __( 'Plan Access', 'smc-viable' ),
                ],
                'public'            => false,
                'show_ui'           => true,
                'show_in_rest'      => true,
                'show_admin_column' => true,
                'hierarchical'      => false,
                'rewrite'           => false,
            ]
        );

        // Seed common terms if missing (safe no-op when they exist).
        foreach ( [ 'standalone', 'plan' ] as $mode_slug ) {
            if ( ! term_exists( $mode_slug, 'smc_access_mode' ) ) {
                wp_insert_term( ucfirst( $mode_slug ), 'smc_access_mode', [ 'slug' => $mode_slug ] );
            }
        }

        foreach ( Plan_Tiers::get_levels() as $plan_slug ) {
            if ( ! term_exists( $plan_slug, 'smc_plan_access' ) ) {
                wp_insert_term( ucfirst( $plan_slug ), 'smc_plan_access', [ 'slug' => $plan_slug ] );
            }
        }
    }

    /**
     * Register Meta Fields.
     */
    public static function register_training_meta(): void {
        // Linked Quiz
        register_post_meta( 'smc_training', '_linked_quiz_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Course Structure (Sections & Lessons)
        register_post_meta( 'smc_training', '_course_sections', [
            'type'         => 'object', 
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

        // Access Control
        register_post_meta( 'smc_training', '_access_type', [
            'type'         => 'string', // 'standalone', 'plan'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_training', '_plan_level', [
            'type'         => 'string', // 'free', 'basic', 'standard'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Gutenberg-friendly multi-value fields (used with taxonomies, kept for API consumers).
        register_post_meta( 'smc_training', '_smc_access_modes', [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
            'default'      => [],
        ] );

        register_post_meta( 'smc_training', '_smc_allowed_plans', [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
            'default'      => [],
        ] );

        register_post_meta( 'smc_training', '_prerequisite_course_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_training', '_formatted_price', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_training', '_course_status', [
             'type'         => 'string', // 'draft', 'published', 'archived'
             'single'       => true,
             'show_in_rest' => true,
         ] );

        register_post_meta( 'smc_training', '_linked_product_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_training', '_course_thumbnail_url', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );
    }

    /**
     * Disable Avada for this post type.
     * 
     * @param array $post_types Allowed post types.
     * @return array
     */
    public static function disable_avada_for_training( array $post_types ): array {
        $key = array_search( 'smc_training', $post_types, true );
        if ( false !== $key ) {
            unset( $post_types[ $key ] );
        }
        return $post_types;
    }

    /**
     * Restrict Content based on Plan.
     *
     * @param string $content Post content.
     * @return string
     */
	public static function restrict_content( string $content ): string {
        global $post;

        if ( ! $post || 'smc_training' !== $post->post_type ) {
            return $content;
        }

        $user_id = get_current_user_id();
        if ( current_user_can( 'manage_options' ) ) {
            return $content; // Admin sees all
        }

        if ( ! $user_id ) {
            return self::get_locked_message( 'login' );
        }

        return Enrollment_Manager::can_access_course( $user_id, (int) $post->ID )
            ? $content
            : self::get_locked_message( 'upgrade' );
    }

    /**
     * Get Locked Message.
     * 
     * @param string $type Type of lock.
     * @return string
     */
    private static function get_locked_message( string $type ): string {
        $login_url = wp_login_url( get_permalink() );
        $shop_url = home_url( '/smc-shop' ); // Placeholder for Shop Page

        if ( 'login' === $type ) {
            return '<div class="smc-locked-content">'
                 . '<p>' . __( 'You must be logged in to view this training material.', 'smc-viable' ) . '</p>'
                 . '<a href="' . esc_url( $login_url ) . '" class="button">' . __( 'Log In', 'smc-viable' ) . '</a>'
                 . '</div>';
        }

        return '<div class="smc-locked-content">'
             . '<p>' . __( 'This content requires a higher plan level.', 'smc-viable' ) . '</p>'
             . '<a href="' . esc_url( $shop_url ) . '" class="button">' . __( 'Upgrade Plan', 'smc-viable' ) . '</a>'
             . '</div>';
    }

    /**
     * Check if user can access course (Entitlement + Prerequisites).
     */
    public static function can_access_course( int $user_id, int $course_id ): bool|\WP_Error {
        // 1. Check prerequisite completion when configured.
        $prereq_id = (int) get_post_meta( $course_id, '_prerequisite', true );
        if ( $prereq_id > 0 ) {
            $percent = (int) get_user_meta( $user_id, "_smc_progress_{$prereq_id}", true );
            if ( $percent < 100 ) {
                $prereq = get_post( $prereq_id );
                $title = ( $prereq instanceof \WP_Post && ! empty( $prereq->post_title ) )
                    ? $prereq->post_title
                    : __( 'the prerequisite course', 'smc-viable' );
                return new \WP_Error( 'prerequisite_locked', sprintf( __( 'You must complete %s first.', 'smc-viable' ), $title ) );
            }
        }

        // 2. Delegate entitlement checks to Enrollment Manager.
        return Enrollment_Manager::can_access_course( $user_id, $course_id );
    }

    /**
     * Redirect legacy/public training single URLs to the learning player URL.
     */
    public static function redirect_training_to_learning_player(): void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }

        if ( ! is_singular( 'smc_training' ) ) {
            return;
        }

        $course = get_queried_object();
        if ( ! ( $course instanceof \WP_Post ) || empty( $course->post_name ) ) {
            return;
        }

        $target = home_url( '/' . self::get_learning_page_path() . '/' . $course->post_name . '/' );
        wp_safe_redirect( $target, 301 );
        exit;
    }

    /**
     * Resolve the student learning page path.
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
}
