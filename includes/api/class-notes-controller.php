<?php
/**
 * REST API Controller for Notes.
 *
 * @package SMC\Viable\API
 */

declare(strict_types=1);

namespace SMC\Viable\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use SMC\Viable\LMS_DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notes_Controller
 */
class Notes_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'notes';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/notes?lesson_id=123
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_note' ],
					'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'lesson_id' => [
                            'required'    => true,
                            'validate_callback' => function($param) {
                                return is_numeric($param);
                            }
                        ]
                    ]
				],
                [
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_note' ],
					'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => [
                        'lesson_id' => [
                            'required'    => true,
                            'validate_callback' => function($param) {
                                return is_numeric($param);
                            }
                        ],
                        'content' => [
                            'required'    => false, // Can be empty to clear?
                            'type'        => 'string',
                        ]
                    ]
				],
			]
		);
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Get Note for a Lesson.
	 */
	public function get_note( $request ) {
        global $wpdb;
		$user_id = get_current_user_id();
        $lesson_id = (int) $request->get_param( 'lesson_id' );
        $table = $wpdb->prefix . LMS_DB::TABLE_NOTES;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT content, updated_at FROM $table WHERE user_id = %d AND lesson_id = %d",
            $user_id,
            $lesson_id
        ) );

        if ( ! $row ) {
            return rest_ensure_response( [
                'content' => '',
                'updated_at' => null,
            ] );
        }

		return rest_ensure_response( [
            'content' => $row->content,
            'updated_at' => $row->updated_at,
        ] );
	}

	/**
	 * Save Note.
	 */
	public function save_note( $request ) {
        global $wpdb;
		$user_id = get_current_user_id();
        $lesson_id = (int) $request->get_param( 'lesson_id' );
        $content = $request->get_param( 'content' ) ?: ''; // Allow empty string
        
        // Sanitize content - allow HTML? Or text only? user notes
        // Ideally we should allow some HTML or rich text if editor supports it.
        // For now, let's treat it as safe stored text, but sanitize for XSS.
        $content = wp_kses_post( $content );

        $table = $wpdb->prefix . LMS_DB::TABLE_NOTES;

        // Check if exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND lesson_id = %d",
            $user_id, $lesson_id
        ) );

        if ( $existing ) {
            $result = $wpdb->update(
                $table,
                [ 'content' => $content ],
                [ 'id' => $existing ]
            );
        } else {
            $result = $wpdb->insert(
                $table,
                [
                    'user_id'   => $user_id,
                    'lesson_id' => $lesson_id,
                    'content'   => $content,
                ]
            );
        }

        if ( false === $result ) {
            return new WP_Error( 'db_error', 'Could not save note', [ 'status' => 500 ] );
        }

		return rest_ensure_response( [
            'success' => true,
            'content' => $content,
            'timestamp' => current_time( 'mysql' )
        ] );
	}
}
