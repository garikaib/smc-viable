<?php
/**
 * Email Automation Handler.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Automation
 */
class Email_Automation {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'smc_daily_progress_check', [ __CLASS__, 'daily_progress_report' ] );
        add_action( 'smc_lesson_completed', [ __CLASS__, 'trigger_lesson_completion_email' ], 10, 2 );

        // Schedule daily check if not already scheduled
        if ( function_exists( 'as_next_scheduled_action' ) && ! \as_next_scheduled_action( 'smc_daily_progress_check' ) ) {
            \as_schedule_recurring_action( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'smc_daily_progress_check' );
        }
	}

    /**
     * Daily Progress Report.
     */
    public static function daily_progress_report(): void {
        // Logic to find students who haven't logged in for 3 days and send an email
        error_log( 'SMC Viable: Running daily progress check...' );
    }

    /**
     * Trigger Lesson Completion Email.
     */
    public static function trigger_lesson_completion_email( int $user_id, int $lesson_id ): void {
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            \as_enqueue_async_action( 'smc_send_lesson_completion_email_task', [
                'user_id' => $user_id,
                'lesson_id' => $lesson_id,
            ] );
        }
    }
}
