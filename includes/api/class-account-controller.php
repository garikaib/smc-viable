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
			// 1. Basic User Info
			$user_data = [
				'id'              => $user->ID,
				'display_name'    => $user->display_name,
				'email'           => $user->user_email,
				'avatar_url'      => get_avatar_url( $user->ID ),
				'registered_date' => date( 'M Y', strtotime( $user->user_registered ) ),
			];

			// 2. Plan Info
			$plan_slug = get_user_meta( $user_id, '_smc_user_plan', true ) ?: 'free';
			$plan_labels = [
				'free'    => 'Free Account',
				'basic'   => 'Basic Plan',
				'premium' => 'Premium Plan',
			];
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
				'total_orders'      => count( $orders ),
			];

			$response_data = [
				'user'              => $user_data,
				'plan'              => $plan_data,
				'business_identity' => $identity_data,
				'enrollments'       => $enrollments,
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
	private function get_enrollments( int $user_id ): array {
		$courses = Enrollment_Manager::get_accessible_courses( $user_id, false );
		$list = [];
		foreach ( $courses as $c ) {
			// Only include fully enrolled courses
			if ( ! Enrollment_Manager::is_enrolled( $user_id, $c->ID ) ) continue;

			$progress = LMS_Progress::get_full_course_progress( $user_id, $c->ID );
			$is_completed = $progress['overall_percent'] >= 100;
			
			$list[] = [
				'id'              => $c->ID,
				'title'           => $c->post_title,
				'thumbnail'       => get_the_post_thumbnail_url( $c->ID, 'thumbnail' ),
				'progress'        => $progress['overall_percent'],
				'status'          => $is_completed ? 'Completed' : 'In Progress',
				'action_label'    => $is_completed ? 'Review' : 'Continue',
				'last_accessed'   => get_post_meta( $c->ID, "_last_access_{$user_id}", true ) ?: $c->post_date,
				'link'            => home_url( '/learning/' . $c->post_name ),
				'certificate_url' => $is_completed ? home_url( "/certificates/{$c->ID}" ) : null,
			];
		}
		return $list;
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
}
