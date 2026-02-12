<?php
/**
 * Enrollment Manager Class.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

use SMC\Viable\LMS_Progress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Enrollment_Manager
 */
class Enrollment_Manager {
    private const PLAN_CLEANUP_OPTION = 'smc_plan_data_cleanup_v2_done';
    /**
     * In-request cache of resolved user plans.
     *
     * @var array<int,string>
     */
    private static array $resolved_plan_cache = [];

    /**
     * Validate and normalize a plan slug.
     */
    private static function normalize_plan_slug( string $plan ): string {
        return Plan_Tiers::normalize_or_default( $plan, 'free' );
    }

    /**
     * Resolve user's current plan from explicit plan orders.
     * Non-plan products never influence this value.
     */
    public static function resolve_user_plan( int $user_id, bool $persist_meta = true ): string {
        if ( $user_id <= 0 ) {
            return 'free';
        }

        if ( isset( self::$resolved_plan_cache[ $user_id ] ) ) {
            return self::$resolved_plan_cache[ $user_id ];
        }

        $meta_plan = self::normalize_plan_slug( (string) get_user_meta( $user_id, '_smc_user_plan', true ) );
        $resolved_plan = $meta_plan;

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

        foreach ( $orders as $order_id ) {
            $order_status = sanitize_key( (string) get_post_meta( (int) $order_id, '_order_status', true ) );
            if ( ! in_array( $order_status, [ 'completed', 'paid', 'processing', 'active' ], true ) ) {
                continue;
            }

            $items = get_post_meta( (int) $order_id, '_order_items', true );
            if ( ! is_array( $items ) ) {
                continue;
            }

            // Normalize legacy/single-item payloads to an indexed list.
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

                if ( $product_id <= 0 ) {
                    continue;
                }

                $product_type = sanitize_key( (string) get_post_meta( $product_id, '_product_type', true ) );
                if ( 'plan' !== $product_type ) {
                    continue;
                }

                $order_plan = self::normalize_plan_slug( (string) get_post_meta( $product_id, '_plan_level', true ) );

                // Prevent accidental downgrades caused by free/default records.
                if ( 'free' === $order_plan ) {
                    continue;
                }

                $resolved_plan = $order_plan;
                break 2;
            }
        }

        if ( $persist_meta ) {
            $existing_meta = self::normalize_plan_slug( (string) get_user_meta( $user_id, '_smc_user_plan', true ) );
            if ( $existing_meta !== $resolved_plan ) {
                update_user_meta( $user_id, '_smc_user_plan', $resolved_plan );
            }
        }

        self::$resolved_plan_cache[ $user_id ] = $resolved_plan;
        return $resolved_plan;
    }

    /**
     * One-time cleanup for legacy plan metadata.
     * - Removes _plan_level from non-plan products.
     * - Rebuilds user plan meta from authoritative plan orders.
     */
    public static function run_plan_data_cleanup_once(): void {
        if ( get_option( self::PLAN_CLEANUP_OPTION ) ) {
            return;
        }

        $product_ids = get_posts( [
            'post_type'      => 'smc_product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $product_ids as $product_id ) {
            $product_type = sanitize_key( (string) get_post_meta( (int) $product_id, '_product_type', true ) );
            if ( 'plan' !== $product_type ) {
                delete_post_meta( (int) $product_id, '_plan_level' );
            }
        }

        $user_ids = get_users( [
            'fields' => 'ids',
            'number' => -1,
        ] );

        foreach ( $user_ids as $user_id ) {
            self::resolve_user_plan( (int) $user_id, true );
        }

        update_option( self::PLAN_CLEANUP_OPTION, gmdate( 'c' ), false );
    }

    /**
     * Normalize access modes for a course.
     * Supports new taxonomy/meta and legacy _access_type fallback.
     */
    private static function get_course_access_modes( int $course_id ): array {
        $modes = [];

        $terms = wp_get_post_terms( $course_id, 'smc_access_mode', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $modes = $terms;
        }

        if ( empty( $modes ) ) {
            $meta_modes = get_post_meta( $course_id, '_smc_access_modes', true );
            if ( is_array( $meta_modes ) ) {
                $modes = $meta_modes;
            }
        }

        if ( empty( $modes ) ) {
            $legacy = (string) get_post_meta( $course_id, '_access_type', true );
            if ( in_array( $legacy, [ 'standalone', 'plan' ], true ) ) {
                $modes = [ $legacy ];
            }
        }

        if ( empty( $modes ) ) {
            $modes = [ 'standalone' ];
        }

        // If course has explicit plan gates but no plan mode persisted, infer plan access.
        if ( ! in_array( 'plan', $modes, true ) ) {
            $allowed_plans = self::get_course_allowed_plans( $course_id );
            if ( ! empty( $allowed_plans ) ) {
                $modes[] = 'plan';
            }
        }

        return array_values( array_unique( array_filter( array_map( 'sanitize_key', $modes ) ) ) );
    }

    /**
     * Normalize allowed plans for a course.
     * Supports new taxonomy/meta and legacy _plan_level fallback.
     */
    private static function get_course_allowed_plans( int $course_id ): array {
        $plans = [];

        $terms = wp_get_post_terms( $course_id, 'smc_plan_access', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $plans = $terms;
        }

        if ( empty( $plans ) ) {
            $meta_plans = get_post_meta( $course_id, '_smc_allowed_plans', true );
            if ( is_array( $meta_plans ) ) {
                $plans = $meta_plans;
            }
        }

        if ( empty( $plans ) ) {
            $legacy = self::normalize_plan_slug( (string) get_post_meta( $course_id, '_plan_level', true ) );
            if ( '' !== $legacy ) {
                $plans = [ $legacy ];
            }
        }

        $normalized = [];
        foreach ( $plans as $plan ) {
            $slug = self::normalize_plan_slug( (string) $plan );
            if ( '' !== $slug ) {
                $normalized[] = $slug;
            }
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * Check user plan against explicit allowed plans (or legacy hierarchical fallback).
     */
    private static function user_matches_course_plan_rules( string $user_plan, array $allowed_plans, int $course_id ): bool {
        $user_plan = self::normalize_plan_slug( $user_plan ?: 'free' );

        if ( ! empty( $allowed_plans ) ) {
            foreach ( $allowed_plans as $required_plan ) {
                if ( self::compare_plan_levels( $user_plan, (string) $required_plan ) ) {
                    return true;
                }
            }
            return false;
        }

        // Legacy fallback if no explicit plan list exists.
        $legacy_level = self::normalize_plan_slug( (string) get_post_meta( $course_id, '_plan_level', true ) );
        if ( '' === $legacy_level ) {
            return false;
        }

        return self::compare_plan_levels( $user_plan, $legacy_level );
    }

    /**
     * Enroll a user in a course.
     *
     * @param int    $user_id User ID.
     * @param int    $course_id Course ID.
     * @param string $source Source of enrollment (purchase, invitation, quiz, manual).
     * @param array  $source_meta Optional metadata (scores, etc.).
     * @return int|false Enrollment ID or false on failure.
     */
    public static function enroll_user( int $user_id, int $course_id, string $source = 'manual', array $source_meta = [] ) {
        global $wpdb;

        // Check if already enrolled (active)
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, status FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        ) );

        $meta_json = ! empty( $source_meta ) ? wp_json_encode( $source_meta ) : null;

        if ( $existing ) {
             $next_status = ( 'completed' === (string) $existing->status ) ? 'completed' : 'active';
             // If inactive, reactivate. If active, update source details (merge meta if needed, or just overwrite/append?)
             // For simplicity, we update source to the latest one enrolling, and Reactivate if inactive.
             // If source is 'quiz' and existing was 'quiz', we might want to merge scores. 
             // But for MVP, let's just update.
             
             $wpdb->update(
                 "{$wpdb->prefix}smc_enrollments",
                 [ 
                     'status' => $next_status,
                     'source' => $source, 
                     'source_meta' => $meta_json,
                     'enrolled_at' => current_time( 'mysql' ) // Renew date? Or keep original? Let's renew.
                 ],
                 [ 'id' => $existing->id ]
             );
             return $existing->id;
        }

        // Insert new
        $result = $wpdb->insert(
            "{$wpdb->prefix}smc_enrollments",
            [
                'user_id'     => $user_id,
                'course_id'   => $course_id,
                'status'      => 'active',
                'source'      => $source,
                'source_meta' => $meta_json,
                'enrolled_at' => current_time( 'mysql' ),
            ]
        );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Reconcile completed orders to explicit enrollment rows.
     * Ensures purchased course products always map to course enrollments.
     */
    public static function reconcile_user_purchase_enrollments( int $user_id ): void {
        if ( $user_id <= 0 ) {
            return;
        }

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

        foreach ( $orders as $order_id ) {
            $order_status = sanitize_key( (string) get_post_meta( (int) $order_id, '_order_status', true ) );
            if ( ! in_array( $order_status, [ 'completed', 'paid', 'processing', 'active' ], true ) ) {
                continue;
            }

            $items = get_post_meta( (int) $order_id, '_order_items', true );
            if ( ! is_array( $items ) ) {
                continue;
            }

            // Normalize legacy/single-item payloads.
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

                if ( $product_id <= 0 ) {
                    continue;
                }

                $product_type = sanitize_key( (string) get_post_meta( $product_id, '_product_type', true ) );
                if ( 'plan' === $product_type ) {
                    continue;
                }

                $course_id = (int) get_post_meta( $product_id, '_linked_training_id', true );
                if ( $course_id <= 0 ) {
                    $course_id = (int) get_post_meta( $product_id, '_linked_course_id', true );
                }

                if ( $course_id <= 0 ) {
                    continue;
                }

                self::enroll_user(
                    $user_id,
                    $course_id,
                    'purchase',
                    [
                        'order_id'   => (int) $order_id,
                        'product_id' => $product_id,
                    ]
                );
            }
        }
    }

    /**
     * Unenroll a user (set status to inactive).
     *
     * @param int $user_id User ID.
     * @param int $course_id Course ID.
     * @return bool Success.
     */
    public static function unenroll_user( int $user_id, int $course_id ): bool {
        global $wpdb;
        $result = $wpdb->update(
            "{$wpdb->prefix}smc_enrollments",
            [ 'status' => 'inactive' ],
            [ 'user_id' => $user_id, 'course_id' => $course_id ]
        );
        return $result !== false;
    }

    /**
     * Check if user has an active enrollment record.
     *
     * @param int $user_id User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function is_enrolled( int $user_id, int $course_id ): bool {
        global $wpdb;
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id, $course_id
        ) );
        return ( $status === 'active' || $status === 'completed' );
    }

    /**
     * Check if user can access a course.
     * Handles both 'standalone' (enrollment required) and 'plan' (plan level check) access types.
     *
     * @param int $user_id User ID.
     * @param int $course_id Course ID.
     * @return bool
     */
    public static function can_access_course( int $user_id, int $course_id ): bool {
        // Explicit enrollment/purchase/invite always grants access.
        if ( self::is_enrolled( $user_id, $course_id ) ) {
            return true;
        }

        $modes = self::get_course_access_modes( $course_id );
        if ( ! in_array( 'plan', $modes, true ) ) {
            // Standalone-only courses require explicit enrollment.
            return false;
        }

        $allowed_plans = self::get_course_allowed_plans( $course_id );
        $user_plan = self::resolve_user_plan( $user_id );

        return self::user_matches_course_plan_rules( $user_plan, $allowed_plans, $course_id );
    }

    /**
     * Check if user can enroll for free (Plan Access).
     */
    public static function can_enroll_for_free( int $user_id, int $course_id ): bool {
        $modes = self::get_course_access_modes( $course_id );
        if ( ! in_array( 'plan', $modes, true ) ) {
            return false;
        }

        if ( self::is_enrolled( $user_id, $course_id ) ) {
            return false;
        }

        $allowed_plans = self::get_course_allowed_plans( $course_id );
        $user_plan = self::resolve_user_plan( $user_id );

        return self::user_matches_course_plan_rules( $user_plan, $allowed_plans, $course_id );
    }

    /**
     * Compare plan levels.
     * Returns true if user_level >= course_level.
     */
    private static function compare_plan_levels( string $user_level, string $course_level ): bool {
        return Plan_Tiers::compare( $user_level, $course_level );
    }

    /**
     * Get all courses accessible to a user.
     * Merges enrolled courses + plan-accessible courses.
     *
     * @param int $user_id User ID.
     * @return array List of course posts with added 'access_source' property.
     */
    public static function get_accessible_courses( int $user_id, bool $include_locked = false ): array {
        global $wpdb;

        // 1. Get enrolled courses
        $enrolled_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d AND (status = 'active' OR status = 'completed')",
            $user_id
        ) );

        // 2. Get plan accessible courses
        $user_plan = self::resolve_user_plan( $user_id );
        
        $args = [
            'post_type'      => 'smc_training',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        
        $all_courses = get_posts( $args );
        $final_list = [];

        foreach ( $all_courses as $course ) {
            $is_enrolled = in_array( $course->ID, $enrolled_ids );
            $modes = self::get_course_access_modes( $course->ID );
            $allowed_plans = self::get_course_allowed_plans( $course->ID );
            
            $has_access = false;
            $is_locked_plan = false;
            $source_label = '';
            $can_enroll = false;

            if ( $is_enrolled ) {
                $has_access = true;
                $source_label = self::get_source_label( 'enrolled' ); // Simplified for now
            } elseif ( in_array( 'plan', $modes, true ) ) {
                if ( self::user_matches_course_plan_rules( $user_plan, $allowed_plans, $course->ID ) ) {
                    // Plan Match: User CAN enroll for free, but is NOT enrolled yet.
                    // This separates "My Learning" from "Recommended".
                    $can_enroll = true;
                    $source_label = 'Included in ' . ucfirst( $user_plan ) . ' Plan';
                } else {
                    $is_locked_plan = true;
                    $required = ! empty( $allowed_plans ) ? implode( ', ', array_map( 'ucfirst', $allowed_plans ) ) : 'Eligible Plan';
                    $source_label = 'Requires ' . $required;
                }
            } elseif ( in_array( 'standalone', $modes, true ) ) {
                $is_locked_plan = true;
                $source_label = 'Available as standalone purchase';
            }

            // Structure return data
            if ( $is_enrolled ) {
                $course->access_source_label = $source_label;
                $course->is_locked = false;
                $course->is_enrolled = true;
                $final_list[] = $course;
            } elseif ( $can_enroll ) {
                // Not enrolled, but available via plan
                $course->access_source_label = $source_label;
                $course->is_locked = true; // technically locked content until enrollment, but "unlocked" capacity
                $course->can_enroll = true; // Flag for UI to show "Enroll Now" instead of "Buy"
                $course->is_enrolled = false;
                $final_list[] = $course;
            } elseif ( $include_locked && $is_locked_plan ) {
                $course->access_source_label = $source_label;
                $course->is_locked = true;
                $course->is_enrolled = false;
                $final_list[] = $course;
            }
        }

        return $final_list;
    }

    private static function get_source_label( $source ) {
        switch ( $source ) {
            case 'purchase': return 'Purchased';
            case 'invitation': return 'Invited';
            case 'quiz': return 'Via Assessment';
            case 'manual': return 'Assigned';
            default: return 'Enrolled';
        }
    }

    /**
     * Get user enrollments (raw data).
     *
     * @param int $user_id
     * @return array
     */
    public static function get_user_enrollments( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}smc_enrollments WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get students enrolled in a course.
     *
     * @param int $course_id
     * @return array List of user objects with progress info.
     */
    public static function get_course_students( int $course_id ): array {
        global $wpdb;
        
        // Fetch explicit enrollments
        $enrollments = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}smc_enrollments WHERE course_id = %d AND status != 'inactive'",
            $course_id
        ) );

        $students = [];
        foreach ( $enrollments as $rec ) {
            $user = get_userdata( $rec->user_id );
            if ( ! $user ) continue;

            $progress_data = LMS_Progress::get_full_course_progress( (int) $rec->user_id, (int) $course_id );
            $progress = $progress_data['overall_percent'];

            $students[] = [
                'id'       => $user->ID,
                'name'     => $user->display_name,
                'email'    => $user->user_email,
                'enrolled_at' => $rec->enrolled_at,
                'source'   => $rec->source,
                'progress' => $progress, // %
                'status'   => $rec->status
            ];
        }

        return $students;
    }

    /**
     * Mark course as completed manually.
     */
    public static function mark_course_completed( int $user_id, int $course_id ): bool {
        global $wpdb;

        // Verify access first
        if ( ! self::can_access_course( $user_id, $course_id ) ) {
            return false;
        }

        // If not enrolled (e.g. plan access), create an enrollment record marked as completed
        if ( ! self::is_enrolled( $user_id, $course_id ) ) {
             return (bool) self::enroll_user( $user_id, $course_id, 'manual', [ 'completed_via' => 'manual_plan_access' ] );
        }

        // Update status
        $wpdb->update(
            "{$wpdb->prefix}smc_enrollments",
            [ 
                'status' => 'completed',
                'completed_at' => current_time( 'mysql' ),
                'source_meta' => json_encode( ['completed_via' => 'manual'] ) // Merge? Simplified for now.
            ],
            [ 'user_id' => $user_id, 'course_id' => $course_id ]
        );

        do_action( 'smc_training_completed', $user_id, $course_id );

        return true;
    }

    /**
     * Invite users by email.
     * Creates accounts if needed. Enrolls them in a course OR assigns a plan. Sends email.
     * 
     * @param array  $emails List of email strings.
     * @param int    $item_id Course ID or Plan (Product) ID.
     * @param string $message Custom message.
     * @return array Results per email.
     */
    public static function invite_by_email( array $emails, int $item_id, string $message = '' ): array {
        // Simple Rate Limiting: Max 20 emails per batch
        if ( count( $emails ) > 20 ) {
            $emails = array_slice( $emails, 0, 20 );
        }

        $results = [];
        $item    = get_post( $item_id );

        if ( ! $item ) {
            return [ 'error' => 'Invalid course or plan ID' ];
        }

        $is_plan = ( $item->post_type === 'smc_product' && get_post_meta( $item_id, '_product_type', true ) === 'plan' );
        
        foreach ( $emails as $email ) {
            $email = sanitize_email( $email );
            if ( ! is_email( $email ) ) {
                $results[ $email ] = 'Invalid email';
                continue;
            }

            try {
                $user = get_user_by( 'email', $email );

                if ( ! $user ) {
                    // Create user.
                    $username = strstr( $email, '@', true ); // simple username
                    $password = wp_generate_password();
                    $user_id = wp_create_user( $username, $password, $email );

                    if ( is_wp_error( $user_id ) ) {
                        $results[ $email ] = 'Failed to create user: ' . $user_id->get_error_message();
                        continue;
                    }

                    $user = get_userdata( $user_id );
                }

                if ( ! ( $user instanceof \WP_User ) ) {
                    $results[ $email ] = 'Failed to load user account';
                    continue;
                }

                // Ensure student role for invited users.
                if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
                    $user->set_role( 'subscriber' );
                }

                if ( $is_plan ) {
                    // Assign Plan.
                    $plan_level = self::normalize_plan_slug( (string) get_post_meta( $item_id, '_plan_level', true ) );
                    if ( 'free' !== $plan_level ) {
                        update_user_meta( $user->ID, '_smc_user_plan', $plan_level );
                    }

                    // Send Email via Service.
                    Email_Service::send_invitation( $user, $item_id, $message );
                    $results[ $email ] = 'Plan Assigned & Invited';
                } else {
                    // Enroll in Course.
                    $enroll_id = self::enroll_user( $user->ID, $item_id, 'invitation' );

                    if ( $enroll_id ) {
                        // Send Email via Service.
                        Email_Service::send_invitation( $user, $item_id, $message );
                        $results[ $email ] = 'Enrolled & Invited';
                    } else {
                        $results[ $email ] = 'Enrollment failed';
                    }
                }
            } catch ( \Throwable $e ) {
                $results[ $email ] = 'Invitation failed: ' . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Evaluate Quiz Rules (Dry Run).
     * Returns matching courses based on score and optionally checks user access.
     */
    public static function evaluate_quiz_rules( int $quiz_id, array $score_data, ?int $user_id = null ): array {
        $rules_json = get_post_meta( $quiz_id, '_smc_quiz_enrollment_rules', true );
        if ( ! $rules_json ) return [];

        $rules = is_array( $rules_json ) ? $rules_json : json_decode( $rules_json, true );
        if ( ! is_array( $rules ) ) return [];

        $total_score = $score_data['total_score'] ?? 0;
        $matches = [];

        foreach ( $rules as $rule ) {
            if ( self::evaluate_rule( $rule['condition'], $total_score ) ) {
                $courses = $rule['courses'] ?? [];
                
                foreach ( $courses as $course_id ) {
                    // If user_id provided, check access
                    if ( $user_id && ! self::can_access_course( $user_id, $course_id ) ) {
                        continue;
                    }
                    
                    $matches[] = [
                        'course_id' => $course_id,
                        'recommended_sections' => $rule['recommended_sections'] ?? [],
                        'course_title' => get_the_title( $course_id ), // Helper for frontend
                    ];
                }
            }
        }
        
        return $matches;
    }

    /**
     * Process Quiz Enrollment Rules.
     * Matches quiz score to courses and enrolls user if they have plan access.
     */
    public static function process_quiz_enrollment( int $user_id, int $quiz_id, array $score_data ): array {
        $matches = self::evaluate_quiz_rules( $quiz_id, $score_data, $user_id );
        $enrolled_courses = [];
        $total_score = $score_data['total_score'] ?? 0;

        foreach ( $matches as $match ) {
            $course_id = $match['course_id'];
            
            // Prepare source meta for recommendation
            $meta = [
                'quiz_id' => $quiz_id,
                'score'   => $total_score,
                'recommended_sections' => $match['recommended_sections']
            ];

            self::enroll_user( $user_id, $course_id, 'quiz', $meta );
            $enrolled_courses[] = $course_id;
        }
        
        return $enrolled_courses;
    }

    /**
     * Get Formatted User Enrollments.
     * Shared logic for API responses.
     */
    public static function get_formatted_user_enrollments( int $user_id ): array {
        $list = [];

        $enrollments = self::get_user_enrollments( $user_id );
        $course_ids = [];
        $source_by_course = [];
        $meta_by_course = [];
        $enrolled_at_by_course = [];

        foreach ( $enrollments as $enrollment ) {
            $course_id = isset( $enrollment->course_id ) ? (int) $enrollment->course_id : 0;
            $status = isset( $enrollment->status ) ? (string) $enrollment->status : '';

            if ( $course_id <= 0 || ! in_array( $status, [ 'active', 'completed' ], true ) ) {
                continue;
            }

            $course_ids[] = $course_id;
            $source_by_course[ $course_id ] = isset( $enrollment->source ) ? (string) $enrollment->source : 'manual';
            $decoded_meta = json_decode( (string) ( $enrollment->source_meta ?? '' ), true );
            $meta_by_course[ $course_id ] = is_array( $decoded_meta ) ? $decoded_meta : [];
            $enrolled_at_by_course[ $course_id ] = isset( $enrollment->enrolled_at ) ? (string) $enrollment->enrolled_at : '';
        }

        $course_ids = array_values( array_unique( array_map( 'intval', $course_ids ) ) );
        if ( empty( $course_ids ) ) {
            return $list;
        }

        $courses = get_posts( [
            'post_type'      => 'smc_training',
            'post_status'    => [ 'publish', 'private' ],
            'post__in'       => $course_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => -1,
        ] );

        foreach ( $courses as $c ) {
            $course_id = (int) $c->ID;
            $progress = LMS_Progress::get_full_course_progress( $user_id, $course_id );
            $is_completed = ( isset( $progress['overall_percent'] ) ? (int) $progress['overall_percent'] : 0 ) >= 100;

            $list[] = [
                'id'              => $course_id,
                'title'           => self::resolve_course_display_title(
                    $course_id,
                    $source_by_course[ $course_id ] ?? 'manual',
                    $meta_by_course[ $course_id ] ?? []
                ),
                'thumbnail'       => get_the_post_thumbnail_url( $course_id, 'thumbnail' ),
                'progress'        => isset( $progress['overall_percent'] ) ? (int) $progress['overall_percent'] : 0,
                'status'          => $is_completed ? 'Completed' : 'In Progress',
                'action_label'    => $is_completed ? 'Review' : 'Continue',
                'last_accessed'   => get_post_meta( $course_id, "_last_access_{$user_id}", true ) ?: ( $enrolled_at_by_course[ $course_id ] ?? $c->post_date ),
                'link'            => home_url( '/learning/' . $c->post_name ),
                'certificate_url' => $is_completed ? home_url( "/certificates/{$course_id}" ) : null,
            ];
        }

        return $list;
    }

    private static function resolve_course_display_title( int $course_id, string $source, array $source_meta ): string {
        if ( 'purchase' === $source ) {
            $product_id = isset( $source_meta['product_id'] ) ? (int) $source_meta['product_id'] : 0;
            if ( $product_id > 0 ) {
                $product = get_post( $product_id );
                if ( $product && 'smc_product' === $product->post_type && ! empty( $product->post_title ) ) {
                    return $product->post_title;
                }
            }
        }

        $course = get_post( $course_id );
        return $course && ! empty( $course->post_title ) ? $course->post_title : __( 'Course', 'smc-viable' );
    }

    private static function evaluate_rule( $condition, $score ): bool {
        $op = $condition['operator'] ?? '';
        $val = $condition['value'] ?? 0;
        
        switch ( $op ) {
            case 'gt': return $score > $val;
            case 'gte': return $score >= $val;
            case 'lt': return $score < $val;
            case 'lte': return $score <= $val;
            case 'between': 
                return $score >= ($condition['min'] ?? 0) && $score <= ($condition['max'] ?? 0);
            default: return false;
        }
    }
}
