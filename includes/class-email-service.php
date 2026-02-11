<?php
/**
 * Email Service for SMC Viable LMS.
 * Handles transactional emails with HTML templates.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Service {

    /**
     * Send Course Invitation.
     *
     * @param \WP_User $user
     * @param int $course_id
     * @param string $custom_message
     * @return bool
     */
    public static function send_invitation( \WP_User $user, int $course_id, string $custom_message = '' ): bool {
        $course = get_post( $course_id );
        if ( ! $course ) return false;

        $subject = sprintf( 'You have been invited to: %s', $course->post_title );
        
        $login_url = wp_login_url(); // In a real app, use a magic link generator if available
        $course_url = home_url( '/student-hub' ); // Redirect to dashboard

        $message = self::get_template_html( 'invitation', [
            'name' => $user->display_name,
            'course_title' => $course->post_title,
            'custom_message' => $custom_message,
            'action_url' => $course_url,
            'login_url' => $login_url
        ] );

        return self::send( $user->user_email, $subject, $message );
    }

    /**
     * Send Quiz Enrollment Notification.
     *
     * @param \WP_User $user
     * @param array $enrolled_course_ids
     * @return bool
     */
    public static function send_quiz_enrollment( \WP_User $user, array $enrolled_course_ids ): bool {
        if ( empty( $enrolled_course_ids ) ) return false;

        $course_titles = [];
        foreach ( $enrolled_course_ids as $id ) {
            $course_titles[] = get_the_title( $id );
        }

        $subject = 'New Modules Unlocked!';
        $action_url = home_url( '/student-hub' );

        $message = self::get_template_html( 'quiz-unlock', [
            'name' => $user->display_name,
            'courses' => $course_titles,
            'action_url' => $action_url
        ] );

        return self::send( $user->user_email, $subject, $message );
    }

    /**
     * Send Course Completion Notification.
     *
     * @param \WP_User $user
     * @param int $course_id
     * @return bool
     */
    public static function send_completion( \WP_User $user, int $course_id ): bool {
        $course = get_post( $course_id );
        $subject = sprintf( 'Course Completed: %s', $course->post_title );
        
        $message = self::get_template_html( 'completion', [
            'name' => $user->display_name,
            'course_title' => $course->post_title,
            'action_url' => home_url( '/student-hub' ) // Maybe certificate link later
        ] );

        return self::send( $user->user_email, $subject, $message );
    }

    /**
     * Send Email Helper.
     */
    private static function send( string $to, string $subject, string $message ): bool {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        return wp_mail( $to, $subject, $message, $headers );
    }

    /**
     * Get HTML Template.
     */
    private static function get_template_html( string $type, array $args ): string {
        // Simple inline styling for email compatibility
        $style_container = "font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333; line-height: 1.6;";
        $style_btn = "display: inline-block; background-color: #0d9488; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px;";
        $style_footer = "margin-top: 30px; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px;";

        $content = '';

        switch ( $type ) {
            case 'invitation':
                $custom_msg_html = $args['custom_message'] ? "<div style='background: #f9fafb; padding: 15px; border-left: 4px solid #0d9488; margin: 20px 0; font-style: italic;'>{$args['custom_message']}</div>" : '';
                $content = "
                    <h1 style='color: #111827; font-size: 24px;'>You're Invited!</h1>
                    <p>Hi {$args['name']},</p>
                    <p>You have been invited to join the course: <strong>{$args['course_title']}</strong>.</p>
                    {$custom_msg_html}
                    <p>Click the button below to access your Learning Hub.</p>
                    <a href='{$args['action_url']}' style='{$style_btn}'>Start Learning</a>
                    <p style='margin-top: 20px;'>If you need to log in, please <a href='{$args['login_url']}'>click here</a>.</p>
                ";
                break;

            case 'quiz-unlock':
                $list_items = implode( '', array_map( fn($c) => "<li>{$c}</li>", $args['courses'] ) );
                $content = "
                    <h1 style='color: #111827; font-size: 24px;'>Modules Unlocked!</h1>
                    <p>Congratulations {$args['name']},</p>
                    <p>Based on your recent assessment results, we've unlocked the following modules for you:</p>
                    <ul style='background: #f0fdf4; padding: 20px 40px; border-radius: 8px;'>{$list_items}</ul>
                    <a href='{$args['action_url']}' style='{$style_btn}'>Go to Dashboard</a>
                ";
                break;

            case 'completion':
                $content = "
                    <h1 style='color: #111827; font-size: 24px;'>Course Completed!</h1>
                    <p>Hi {$args['name']},</p>
                    <p>You have successfully completed <strong>{$args['course_title']}</strong>.</p>
                    <p>Great work deepening your expertise.</p>
                    <a href='{$args['action_url']}' style='{$style_btn}'>Back to Learning Hub</a>
                ";
                break;
        }

        return "
            <div style='{$style_container}'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <span style='font-weight: 900; letter-spacing: 1px; color: #111827; font-size: 18px;'>SMC VIABLE</span>
                </div>
                {$content}
                <div style='{$style_footer}'>
                    <p>&copy; " . date('Y') . " SMC Viable. All rights reserved.</p>
                </div>
            </div>
        ";
    }
}
