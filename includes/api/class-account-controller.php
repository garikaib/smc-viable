<?php
/**
 * REST API Controller for Account Management.
 *
 * @package SMC\Viable\API
 */

declare(strict_types=1);

namespace SMC\Viable\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use SMC\Viable\Enrollment_Manager;
use SMC\Viable\LMS_Progress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Account_Controller
 */
class Account_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'account';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/account/profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/profile',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
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
	 * Get User Profile Data (Consolidated).
	 */
	public function get_profile( $request ) {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', 'User data could not be retrieved.', [ 'status' => 404 ] );
		}

		try {
            Enrollment_Manager::reconcile_user_purchase_enrollments( $user_id );

			// 1. Basic User Info
			$user_data = [
				'id'              => $user->ID,
				'display_name'    => $user->display_name,
				'email'           => $user->user_email,
				'avatar_url'      => get_avatar_url( $user->ID ),
				'registered_date' => date( 'M Y', strtotime( $user->user_registered ) ),
				'can_manage_assessments' => $this->current_user_is_instructor(),
				'assessment_center_url' => $this->get_assessment_center_url(),
			];

			// 2. Plan Info
			$plan_slug = Enrollment_Manager::resolve_user_plan( $user_id );
			$plan_labels = \SMC\Viable\Plan_Tiers::get_level_labels();
			$plan_data = [
				'level' => $plan_slug,
				'label' => $plan_labels[ $plan_slug ] ?? ucfirst( $plan_slug ),
			];

			// 3. Business Identity (Assessment Results)
			// Try to find latest submission for 'free_public' or 'basic' quizzes
			$identity_data = $this->get_business_identity( $user_id );

			// 4. Enrollments (Summary)
			$enrollments = $this->get_enrollments( $user_id );
			
			// 5. Recommendations (if few/no enrollments)
			$recommendations = $this->get_recommendations( $user_id, $enrollments );

			// 6. Orders (History)
			$orders = $this->get_orders( $user_id );

			// 7. Stats
			$stats = [
				'courses_enrolled'  => count( $enrollments ),
				'courses_completed' => count( array_filter( $enrollments, fn($c) => $c['progress'] >= 100 ) ),
				'quizzes_completed' => $this->get_quizzes_completed( $user_id ),
				'total_orders'      => count( $orders ),
			];

			$response_data = [
				'user'              => $user_data,
				'plan'              => $plan_data,
				'business_identity' => $identity_data,
				'enrollments'       => $enrollments,
				'assessments'       => $this->get_assessments( $user_id, $user->user_email ),
				'recommendations'   => $recommendations,
				'orders'            => $orders,
				'stats'             => $stats,
			];

			return rest_ensure_response( $response_data );
		} catch ( \Exception $e ) {
			return new WP_Error( 'profile_error', $e->getMessage(), [ 'status' => 500 ] );
		} catch ( \Error $e ) {
			return new WP_Error( 'profile_fatal_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Resolve frontend assessment center URL.
	 */
	private function get_assessment_center_url(): string {
		$page = get_page_by_path( 'assessment-center' );
		if ( $page instanceof \WP_Post ) {
			$link = get_permalink( $page );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return home_url( '/assessment-center/' );
	}

	/**
	 * By default, editors and administrators are considered instructors.
	 */
	private function current_user_is_instructor(): bool {
		$user = wp_get_current_user();

		return $user instanceof \WP_User
			&& (
				in_array( 'administrator', (array) $user->roles, true )
				|| in_array( 'editor', (array) $user->roles, true )
			);
	}

	/**
	 * Update User Profile.
	 */
	public function update_profile( $request ) {
		$user_id = get_current_user_id();
		$params  = $request->get_json_params();

		// Require current password only for sensitive changes (email change or password change)
		$user = get_userdata( $user_id );
		$email_changed = ! empty( $params['email'] ) && $params['email'] !== $user->user_email;
		$password_changed = ! empty( $params['password'] );

		if ( $email_changed || $password_changed ) {
			if ( empty( $params['current_password'] ) ) {
				return new WP_Error( 'missing_password', 'Current password required for email or password changes.', [ 'status' => 400 ] );
			}
			if ( ! wp_check_password( $params['current_password'], $user->user_pass, $user_id ) ) {
				return new WP_Error( 'incorrect_password', 'Current password incorrect.', [ 'status' => 403 ] );
			}
		}

		$args = [ 'ID' => $user_id ];
		$updated = false;

		if ( ! empty( $params['display_name'] ) ) {
			$args['display_name'] = sanitize_text_field( $params['display_name'] );
			$updated = true;
		}

		if ( ! empty( $params['email'] ) && is_email( $params['email'] ) ) {
			$args['user_email'] = sanitize_email( $params['email'] );
			$updated = true;
		}

		if ( ! empty( $params['password'] ) ) {
			$args['user_pass'] = $params['password'];
			$updated = true;
		}

		if ( ! empty( $params['company_name'] ) ) {
			update_user_meta( $user_id, '_smc_company_name', sanitize_text_field( $params['company_name'] ) );
			$updated = true;
		}

		if ( ! empty( $params['industry'] ) ) {
			update_user_meta( $user_id, '_smc_industry', sanitize_text_field( $params['industry'] ) );
			$updated = true;
		}

		if ( $updated ) {
			$result = wp_update_user( $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return rest_ensure_response( [ 'success' => true, 'message' => 'Profile updated successfully.' ] );
		}

		return rest_ensure_response( [ 'success' => false, 'message' => 'No changes made.' ] );
	}

	/**
	 * Helper: Get Business Identity from Quiz Data.
	 */
	private function get_business_identity( int $user_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'smc_quiz_submissions';

		// Check if table exists before querying to prevent HTML error output
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
		if ( ! $table_exists ) {
			// Table doesn't exist yet — return unknown silently
			return [ 'status' => 'unknown' ];
		}

		// Suppress errors to prevent HTML output corrupting JSON responses
		$suppress = $wpdb->suppress_errors( true );
		$sub = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );
		$wpdb->suppress_errors( $suppress );

		if ( ! $sub ) {
			return [ 'status' => 'unknown' ];
		}

		$answers = json_decode( $sub->answers, true ) ?: [];
		$score   = $sub->score; // 0-100
		
		// Extract Company Name & Industry from User Meta
		$company  = get_user_meta( $user_id, '_smc_company_name', true ) ?: 'Private Entity';
		$industry = get_user_meta( $user_id, '_smc_industry', true ) ?: 'Unspecified Industry';

		// Stage calculation & descriptions
		$stage = 'Validation';
		$description = 'You are currently validating your core offer and finding product-market fit. Focus on customer feedback and lean operations.';
		
		if ( $score > 40 ) {
			$stage = 'Efficiency';
			$description = 'Your business is stable. Focus on optimizing internal processes, reducing waste, and building scalable systems.';
		}
		if ( $score > 70 ) {
			$stage = 'Scaling';
			$description = 'You have a proven model. Focus on aggressive growth, market expansion, and leadership development.';
		}

		return [
			'status'            => 'assessed',
			'viability_score'   => (int) $score,
			'stage'             => $stage,
			'stage_description' => $description,
			'company'           => $company,
			'industry'          => $industry,
			'date'              => date( 'M d, Y', strtotime( $sub->created_at ) ),
			'report_link'       => home_url( '/results/' . $sub->id ),
		];
	}

	/**
	 * Helper: Get Enrollments.
	 */
	/**
	 * Helper: Get Enrollments.
	 */
	private function get_enrollments( int $user_id ): array {
		return Enrollment_Manager::get_formatted_user_enrollments( $user_id );
	}

	/**
	 * Helper: Get Recommended Courses.
	 */
	private function get_recommendations( int $user_id, array $existing_enrollments ): array {
		$enrolled_ids = array_column( $existing_enrollments, 'id' );
		$all_accessible = Enrollment_Manager::get_accessible_courses( $user_id, true ); // true to include locked but accessible via plan?
		
		$list = [];
		foreach ( $all_accessible as $c ) {
			if ( in_array( $c->ID, $enrolled_ids ) ) continue;
			
			// Only recommend if they can access it on their current plan
			if ( Enrollment_Manager::can_access_course( $user_id, $c->ID ) ) {
				$list[] = [
					'id'        => $c->ID,
					'title'     => $c->post_title,
					'thumbnail' => get_the_post_thumbnail_url( $c->ID, 'medium' ),
					'link'      => home_url( '/learning/' . $c->post_name ),
					'type'      => 'included',
				];
			}
			
			if ( count( $list ) >= 3 ) break;
		}

		return $list;
	}

	/**
	 * Helper: Get Orders.
	 */
	private function get_orders( int $user_id ): array {
		// Fetch 'smc_order' posts where _customer_id = user_id
		$args = [
			'post_type'      => 'smc_order',
			'post_status'    => 'publish', // or 'any'
			'posts_per_page' => 10,
			'meta_key'       => '_customer_id',
			'meta_value'     => $user_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		$posts = get_posts( $args );
		$list = [];

		foreach ( $posts as $p ) {
			$total  = get_post_meta( $p->ID, '_order_total', true );
			$status = get_post_meta( $p->ID, '_order_status', true );
			$items  = get_post_meta( $p->ID, '_order_items', true ) ?: [];
			
			$item_names = array_map( function($i) { return get_the_title( $i['product_id'] ); }, $items );

			$list[] = [
				'id'          => $p->ID,
				'date'        => get_the_date( 'M d, Y', $p->ID ),
				'total'       => $total,
				'status'      => ucfirst( $status ),
				'summary'     => implode( ', ', $item_names ),
				'items_count' => count( $items ),
				'view_url'    => home_url( "/my-account/view-order/{$p->ID}/" ),
				'invoice_url' => home_url( "/my-account/invoice/{$p->ID}/" ),
			];
		}
		return $list;
	}

	/**
	 * Helper: Get saved assessments for dashboard history.
	 */
	private function get_assessments( int $user_id, string $email ): array {
		$this->ensure_reports_from_submissions( $user_id );

		$args = [
			'post_type'      => 'smc_lead',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'   => '_smc_lead_user_id',
					'value' => $user_id,
				],
				[
					'key'   => '_smc_lead_email',
					'value' => sanitize_email( $email ),
				],
			],
		];

		$reports = get_posts( $args );
		$list = [];

		foreach ( $reports as $report ) {
			$quiz_id = (int) get_post_meta( $report->ID, '_smc_lead_quiz_id', true );
			$quiz = $quiz_id > 0 ? get_post( $quiz_id ) : null;
			$score_data = json_decode( (string) get_post_meta( $report->ID, '_smc_lead_score_data', true ), true );
			$result_title = (string) get_post_meta( $report->ID, '_smc_lead_result_title', true );
			$result_color = (string) get_post_meta( $report->ID, '_smc_lead_result_color', true );
			$report_token = (string) get_post_meta( $report->ID, '_smc_lead_report_token', true );
			$total_score = is_array( $score_data ) ? (int) ( $score_data['total_score'] ?? 0 ) : 0;
			$download_url = rest_url( sprintf( 'smc/v1/report/download/%d', (int) $report->ID ) );
			if ( '' !== $report_token ) {
				$download_url = add_query_arg( 'token', rawurlencode( $report_token ), $download_url );
			}

			$list[] = [
				'id'           => (int) $report->ID,
				'quiz_id'      => $quiz_id,
				'quiz_title'   => $quiz ? $quiz->post_title : __( 'Assessment', 'smc-viable' ),
				'score'        => $total_score,
				'result_title' => '' !== $result_title ? $result_title : __( 'Overall Score', 'smc-viable' ),
				'color'        => '' !== $result_color ? $result_color : '#0E7673',
				'date'         => get_the_date( 'M d, Y', $report->ID ),
				'download_url' => $download_url,
			];
		}

		return $list;
	}

	/**
	 * Backfill report snapshots from historical quiz submissions.
	 */
	private function ensure_reports_from_submissions( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'smc_quiz_submissions';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			return;
		}

		$subs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, quiz_id, answers, score, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 40",
				$user_id
			)
		);
		if ( ! is_array( $subs ) || empty( $subs ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$name = $user instanceof \WP_User ? (string) $user->display_name : '';
		$email = $user instanceof \WP_User ? (string) $user->user_email : '';

		foreach ( $subs as $sub ) {
			$quiz_id = isset( $sub->quiz_id ) ? (int) $sub->quiz_id : 0;
			if ( $quiz_id <= 0 ) {
				continue;
			}

			$existing = get_posts(
				[
					'post_type'      => 'smc_lead',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [
						'relation' => 'AND',
						[
							'key'   => '_smc_lead_user_id',
							'value' => $user_id,
						],
						[
							'key'   => '_smc_lead_quiz_id',
							'value' => $quiz_id,
						],
					],
				]
			);
			if ( ! empty( $existing ) ) {
				continue;
			}

			$score = isset( $sub->score ) ? (int) $sub->score : 0;
			$answers = json_decode( (string) $sub->answers, true );
			if ( ! is_array( $answers ) ) {
				$answers = [];
			}

			$quiz = get_post( $quiz_id );
			$quiz_title = $quiz instanceof \WP_Post ? $quiz->post_title : __( 'Assessment', 'smc-viable' );
			$result_title = $score >= 70 ? 'Strong readiness' : 'Needs improvement';
			$result_message = $score >= 70
				? 'Your previous submission indicates strong readiness across core dimensions.'
				: 'Your previous submission indicates gaps that can be improved through guided modules.';
			$result_color = $score >= 70 ? '#0E7673' : '#A1232A';

			wp_insert_post(
				[
					'post_title'  => sprintf( 'Assessment Report - %s - %s', $quiz_title, gmdate( 'Y-m-d H:i', strtotime( (string) $sub->created_at ) ) ),
					'post_type'   => 'smc_lead',
					'post_status' => 'publish',
					'meta_input'  => [
						'_smc_lead_name'             => $name,
						'_smc_lead_email'            => $email,
						'_smc_lead_phone'            => '',
						'_smc_lead_quiz_id'          => $quiz_id,
						'_smc_lead_user_id'          => $user_id,
						'_smc_lead_score_data'       => wp_json_encode(
							[
								'total_score'      => $score,
								'answers'          => $answers,
								'scores_by_stage'  => [
									'Overall' => [
										'total' => $score,
										'max'   => 100,
										'flags' => ( $score < 40 ) ? 1 : 0,
									],
								],
							]
						),
						'_smc_lead_stage_summary'    => wp_json_encode(
							[
								[
									'stage'   => 'Overall',
									'total'   => $score,
									'max'     => 100,
									'percent' => $score,
									'tone'    => $score >= 70 ? 'Advanced' : 'Foundation',
									'comment' => $result_message,
									'color'   => $result_color,
								],
							]
						),
						'_smc_lead_flags'            => wp_json_encode(
							$score < 40
								? [ [ 'stage' => 'Overall', 'message' => 'Historical score indicates high-priority intervention required.', 'score' => $score ] ]
								: []
						),
						'_smc_lead_result_title'     => $result_title,
						'_smc_lead_result_message'   => $result_message,
						'_smc_lead_result_color'     => $result_color,
						'_smc_lead_report_token'     => wp_generate_password( 32, false, false ),
						'_smc_lead_report_generated' => current_time( 'mysql' ),
					],
				]
			);
		}
	}

	/**
	 * Helper: Count distinct completed quizzes for user.
	 */
	private function get_quizzes_completed( int $user_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'smc_quiz_submissions';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT quiz_id) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		return (int) $count;
	}
}
