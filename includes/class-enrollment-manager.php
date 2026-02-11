<?php
/**
 * Enrollment Manager Class.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Enrollment_Manager
 */
class Enrollment_Manager {

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
             // If inactive, reactivate. If active, update source details (merge meta if needed, or just overwrite/append?)
             // For simplicity, we update source to the latest one enrolling, and Reactivate if inactive.
             // If source is 'quiz' and existing was 'quiz', we might want to merge scores. 
             // But for MVP, let's just update.
             
             $wpdb->update(
                 "{$wpdb->prefix}smc_enrollments",
                 [ 
                     'status' => 'active', 
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
        $access_type = get_post_meta( $course_id, '_access_type', true ) ?: 'standalone';

        // 1. Standalone: Must be enrolled
        if ( $access_type === 'standalone' ) {
            return self::is_enrolled( $user_id, $course_id );
        }

        // 2. Plan: Check plan level OR if explicitly enrolled (overrides plan)
        if ( self::is_enrolled( $user_id, $course_id ) ) {
            return true;
        }

        // Check plan level
        $course_level = get_post_meta( $course_id, '_plan_level', true ) ?: 'free';
        $user_plan    = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';

        return self::compare_plan_levels( $user_plan, $course_level );
    }

    /**
     * Check if user can enroll for free (Plan Access).
     */
    public static function can_enroll_for_free( int $user_id, int $course_id ): bool {
        $access_type = get_post_meta( $course_id, '_access_type', true ) ?: 'standalone';
        if ( $access_type !== 'plan' ) {
            return false;
        }
        
        $course_level = get_post_meta( $course_id, '_plan_level', true ) ?: 'free';
        $user_plan    = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
        
        return self::compare_plan_levels( $user_plan, $course_level );
    }

    /**
     * Compare plan levels.
     * Returns true if user_level >= course_level.
     */
    private static function compare_plan_levels( string $user_level, string $course_level ): bool {
        $levels = [ 'free' => 0, 'basic' => 1, 'premium' => 2 ];
        $u_val = $levels[ $user_level ] ?? 0;
        $c_val = $levels[ $course_level ] ?? 0;
        return $u_val >= $c_val;
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
        $user_plan = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
        
        $args = [
            'post_type'      => 'smc_training',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];
        
        $all_courses = get_posts( $args );
        $final_list = [];

        foreach ( $all_courses as $course ) {
            $is_enrolled = in_array( $course->ID, $enrolled_ids );
            $access_type = get_post_meta( $course->ID, '_access_type', true ) ?: 'standalone';
            
            $has_access = false;
            $is_locked_plan = false;
            $source_label = '';
            $can_enroll = false;

            if ( $is_enrolled ) {
                $has_access = true;
                $source_label = self::get_source_label( 'enrolled' ); // Simplified for now
            } elseif ( $access_type === 'plan' ) {
                $course_level = get_post_meta( $course->ID, '_plan_level', true ) ?: 'free';
                if ( self::compare_plan_levels( $user_plan, $course_level ) ) {
                    // Plan Match: User CAN enroll for free, but is NOT enrolled yet.
                    // This separates "My Learning" from "Recommended".
                    $can_enroll = true;
                    $source_label = 'Included in ' . ucfirst( $user_plan ) . ' Plan';
                } else {
                    $is_locked_plan = true;
                    $source_label = 'Requires ' . ucfirst( $course_level ) . ' Plan';
                }
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
     * Creates accounts if needed. Enrolls them. Sends email.
     *
     * @param array  $emails List of email strings.
     * @param int    $course_id
     * @param string $message Custom message.
     * @return array Results per email.
     */
    public static function invite_by_email( array $emails, int $course_id, string $message = '' ): array {
        // Simple Rate Limiting: Max 20 emails per batch
        if ( count( $emails ) > 20 ) {
            $emails = array_slice( $emails, 0, 20 );
        }

        $results = [];
        
        foreach ( $emails as $email ) {
            $email = sanitize_email( $email );
            if ( ! is_email( $email ) ) {
                $results[ $email ] = 'Invalid email';
                continue;
            }

            $user = get_user_by( 'email', $email );
            $is_new_user = false;

            if ( ! $user ) {
                // Create user
                $username = strstr( $email, '@', true ); // simple username
                $password = wp_generate_password();
                $user_id = wp_create_user( $username, $password, $email );
                
                if ( is_wp_error( $user_id ) ) {
                    $results[ $email ] = 'Failed to create user: ' . $user_id->get_error_message();
                    continue;
                }
                
                $user = get_userdata( $user_id );
                $is_new_user = true;
                // Set role
                $user->set_role( 'subscriber' );
            }

            // Enroll
            $enroll_id = self::enroll_user( $user->ID, $course_id, 'invitation' );
            
            if ( $enroll_id ) {
                // Send Email via Service
                Email_Service::send_invitation( $user, $course_id, $message );
                $results[ $email ] = 'Invited';
            } else {
                $results[ $email ] = 'Enrollment failed';
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
