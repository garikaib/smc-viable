<?php
/**
 * Content Manager Class.
 * Handles access control for standard posts and other content types.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

use SMC\Viable\Enrollment_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Content_Manager
 */
class Content_Manager {

    /**
     * Init Hooks.
     */
    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_content_meta' ] );
        add_filter( 'the_content', [ __CLASS__, 'restrict_content' ], 20 ); // Priority 20 to run after standard formatting
        add_filter( 'rest_prepare_post', [ __CLASS__, 'filter_rest_content' ], 10, 3 );
    }

    /**
     * Register Meta for Access Control on Posts.
     */
    public static function register_content_meta(): void {
        // Restrict by Plan Level
        register_post_meta( 'post', '_smc_plan_level', [
            'type'         => 'string', // 'free', 'basic', 'premium'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Link to specific Course (optional - e.g. "Must be enrolled in X")
        register_post_meta( 'post', '_smc_linked_course_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );
    }

    /**
     * Restrict Content Display (Frontend).
     *
     * @param string $content The post content.
     * @return string Modified content.
     */
    public static function restrict_content( string $content ): string {
        // Only check main query and inside loop? Or all calls? 
        // For safety, check global post.
        global $post;

        if ( ! $post || 'post' !== $post->post_type ) {
            return $content;
        }
        
        // Admin always sees content
        if ( current_user_can( 'manage_options' ) ) {
            return $content;
        }

        if ( self::can_view_post( $post->ID ) ) {
            return $content;
        }

        return self::get_restriction_message( $post->ID );
    }
    
    /**
     * Filter REST API Content.
     */
    public static function filter_rest_content( $response, $post, $request ) {
        // Admin check?
        if ( current_user_can( 'manage_options' ) ) {
            return $response;
        }

        if ( ! self::can_view_post( $post->ID ) ) {
            $data = $response->get_data();
            $data['content']['rendered'] = self::get_restriction_message( $post->ID );
            $data['excerpt']['rendered'] = ''; // Hide excerpt too? Maybe keep for teaser.
            
            // Add a flag for frontend to handle UI
            $data['is_locked'] = true;
            
            $response->set_data( $data );
        }
        
        return $response;
    }

    /**
     * Check if current user can view a post.
     */
    public static function can_view_post( int $post_id ): bool {
        $user_id = get_current_user_id();
        $plan_level = get_post_meta( $post_id, '_smc_plan_level', true );
        $linked_course = (int) get_post_meta( $post_id, '_smc_linked_course_id', true );

        // 1. Check Plan Level
        if ( $plan_level && $plan_level !== 'free' && $plan_level !== 'public' ) {
            if ( ! $user_id ) return false; // Must be logged in
            
            $user_plan = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
            if ( ! self::compare_plan_levels( $user_plan, $plan_level ) ) {
                return false;
            }
        }

        // 2. Check Linked Course (Must be ACTIVE enrolled)
        if ( $linked_course ) {
            if ( ! $user_id ) return false;
            
            if ( ! class_exists( '\SMC\Viable\Enrollment_Manager' ) ) return false;
            
            if ( ! Enrollment_Manager::is_enrolled( $user_id, $linked_course ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get Restriction Message.
     */
    private static function get_restriction_message( int $post_id ): string {
        $plan_level = get_post_meta( $post_id, '_smc_plan_level', true );
        $msg = '<div class="smc-content-locked">';
        $msg .= '<h3>Content Locked</h3>';
        
        if ( $plan_level ) {
             $msg .= '<p>This content is available to <strong>' . ucfirst( $plan_level ) . '</strong> members.</p>';
        } else {
             $msg .= '<p>This content is restricted.</p>';
        }
        
        $msg .= '<p><a href="/shop" class="smc-btn">Upgrade Plan</a> or <a href="/login">Log In</a></p>';
        $msg .= '</div>';
        
        return $msg;
    }

    /**
     * Helper: Compare Plan Levels (Duplicate of Enrollment Logic, should centralize?)
     * We'll keep local for now to avoid hard dependency loop if Enrollment Manager is missing, though we use it above.
     */
    private static function compare_plan_levels( string $user_level, string $course_level ): bool {
        $levels = [ 'free' => 0, 'basic' => 1, 'premium' => 2 ];
        $u_val = $levels[ $user_level ] ?? 0;
        $c_val = $levels[ $course_level ] ?? 0;
        return $u_val >= $c_val;
    }
}
