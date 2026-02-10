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
        
        // Attempt to disable Avada (Fusion Builder) for this type.
        // Avada typically checks 'fusion_settings' or filters 'fusion_builder_allowed_post_types'.
        add_filter( 'fusion_builder_allowed_post_types', [ __CLASS__, 'disable_avada_for_training' ] );
        
        // Content restriction
        add_filter( 'the_content', [ __CLASS__, 'restrict_content' ] );
	}

	/**
	 * Register Training CPT.
	 */
	public static function register_training_cpt(): void {
		$labels = [
			'name'                  => _x( 'Training Material', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Training Module', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Training', 'smc-viable' ),
            'add_new'               => __( 'Add New Module', 'smc-viable' ),
            'edit_item'             => __( 'Edit Module', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Training', 'smc-viable' ),
			'description'           => __( 'SMC Training Materials', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'smc-quiz',
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'show_in_rest'          => true, // Enable Gutenberg
            'has_archive'           => true,
		];

		register_post_type( 'smc_training', $args );
	}

    /**
     * Register Taxonomy for tagging content (Generic tagging) if needed, 
     * but we rely mostly on Quiz linking.
     * Let's create 'Assessment Category' taxonomy to match Quiz Categories easily.
     */
    public static function register_access_level_taxonomy(): void {
         // We might not strictly need a taxonomy if we link directly to Quiz Categories (strings),
         // but a taxonomy 'smc_quiz_category' shared between Quizzes (meta) and Training (term) is cleaner?
         // Actually, Quiz Categories are just JSON strings in the Quiz Meta currently.
         // So for Training, we should just use a simple text field or dropdown populated by JS in editor.
         // For now, let's keep it simple and stick to Meta.
    }

    /**
     * Register Meta Fields.
     */
    public static function register_training_meta(): void {
        register_post_meta( 'smc_training', '_linked_quiz_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_training', '_linked_quiz_category', [
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

        // 1. Get Linked Quiz
        $quiz_id = (int) get_post_meta( $post->ID, '_linked_quiz_id', true );
        if ( ! $quiz_id ) {
            // content is public if no quiz linked? Or default strict?
            // Let's assume strict: if it's training, it needs a plan unless explicitly 'free'
            // For now, if not linked, let's allow (or user forgot to link).
            return $content; 
        }

        // 2. Get Quiz Plan Level
        $quiz_plan = get_post_meta( $quiz_id, '_smc_quiz_plan_level', true ) ?: 'free';
        if ( 'free' === $quiz_plan ) {
            return $content;
        }

        // 3. Check User Access
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
             return self::get_locked_message( 'login' );
        }

        if ( current_user_can( 'manage_options' ) ) {
            return $content; // Admin sees all
        }

        // Check active subscriptions (this logic will be robustified)
        // For now, check user meta '_smc_user_plan'
        $user_plan = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';

        // Hierarchy: premium > basic > free
        if ( $quiz_plan === 'premium' && $user_plan !== 'premium' ) {
             return self::get_locked_message( 'upgrade' );
        }

        if ( $quiz_plan === 'basic' && ( $user_plan === 'free' ) ) {
             return self::get_locked_message( 'upgrade' );
        }

        return $content;
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
    public static function can_access_course( int $user_id, int $course_id ): bool|WP_Error {
        // 1. Check Prerequisite
        $prereq_id = get_post_meta( $course_id, '_prerequisite', true );
        if ( ! empty( $prereq_id ) ) {
            // Check if user has completed prereq_id
            // Use user meta cache for efficiency
            $percent = get_user_meta( $user_id, "_smc_progress_{$prereq_id}", true );
            if ( (int) $percent < 100 ) {
                $prereq_nav = get_post( $prereq_id );
                return new \WP_Error( 'prerequisite_locked', 'You must complete ' . $prereq_nav->post_title . ' first.' );
            }
        }
        
        return true;
    }
}
