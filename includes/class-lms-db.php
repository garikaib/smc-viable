<?php
/**
 * LMS Database Manager
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMS_DB
 */
class LMS_DB {

	/**
	 * Table Names
	 */
	const TABLE_PROGRESS         = 'smc_progress';
	const TABLE_NOTES            = 'smc_notes';
    const TABLE_ENROLLMENTS      = 'smc_enrollments';
    const TABLE_QUIZ_SUBMISSIONS = 'smc_quiz_submissions';

	/**
	 * Init - Check for DB updates.
	 */
	public static function init(): void {
		$installed_ver = get_option( 'smc_lms_db_version' );
		if ( $installed_ver !== SMC_Quiz_Plugin::VERSION ) {
			self::create_tables();
			update_option( 'smc_lms_db_version', SMC_Quiz_Plugin::VERSION );
		}
	}

	/**
	 * Create or Update Tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$progress_table  = $wpdb->prefix . self::TABLE_PROGRESS;
		$notes_table     = $wpdb->prefix . self::TABLE_NOTES;
        $enroll_table    = $wpdb->prefix . self::TABLE_ENROLLMENTS;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Progress Table (with composite unique key for transactions)
		$sql_progress = "CREATE TABLE $progress_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'not_started',
			progress_percent int(3) NOT NULL DEFAULT 0,
			completed_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY course_user (course_id, user_id)
		) $charset_collate;";

		dbDelta( $sql_progress );

		// 2. Notes Table
		$sql_notes = "CREATE TABLE $notes_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			content longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_lesson (user_id, lesson_id)
		) $charset_collate;";

		dbDelta( $sql_notes );

        // 3. Enrollments Table
        $sql_enroll = "CREATE TABLE $enroll_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL DEFAULT 'active',
            source varchar(50) DEFAULT 'manual',
            source_id bigint(20) unsigned DEFAULT NULL,
            source_meta longtext DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_course (user_id, course_id),
            KEY course_id (course_id),
            KEY source (source)
        ) $charset_collate;";

        dbDelta( $sql_enroll );

        // 4. Quiz Submissions Table
        $quiz_sub_table = $wpdb->prefix . self::TABLE_QUIZ_SUBMISSIONS;
        $sql_quiz_sub = "CREATE TABLE $quiz_sub_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            quiz_id bigint(20) unsigned NOT NULL,
            answers longtext DEFAULT NULL,
            score int(3) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";

        dbDelta( $sql_quiz_sub );
	}
}
