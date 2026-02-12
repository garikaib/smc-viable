<?php
/**
 * REST API Controller for Courses.
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
 * Class Course_Controller
 */
class Course_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'smc/v1';
		$this->rest_base = 'courses';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		// GET /smc/v1/courses/{id}/structure
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/structure',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_structure' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/courses/slug/{slug}/structure
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slug/(?P<slug>[a-z0-9-]+)/structure',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_structure' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/courses/{id}/instructor-profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/instructor-profile',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_instructor_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		// GET /smc/v1/courses/slug/{slug}/instructor-profile
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slug/(?P<slug>[a-z0-9-]+)/instructor-profile',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_instructor_profile' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check permissions.
	 */
	public function permissions_check( $request ) {
        $post = $this->resolve_course_post( $request );
        
        if ( ! $post || 'smc_training' !== $post->post_type ) {
            return new WP_Error( 'rest_course_invalid', 'Invalid course ID.', [ 'status' => 404 ] );
        }

        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', 'You must be logged in.', [ 'status' => 401 ] );
        }

        // Allow instructors/editors to view structure even if not enrolled
        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        // Use Enrollment Manager to gate access for students
        if ( ! Enrollment_Manager::can_access_course( get_current_user_id(), (int) $post->ID ) ) {
             return new WP_Error( 'rest_forbidden', 'You do not have access to this course.', [ 'status' => 403 ] );
        }
        
		return true;
	}

	/**
	 * Get Course Structure.
	 */
	public function get_structure( $request ) {
        $course = $this->resolve_course_post( $request );
        if ( ! $course || 'smc_training' !== $course->post_type ) {
            return new WP_Error( 'rest_course_invalid', 'Invalid course ID.', [ 'status' => 404 ] );
        }

		$id = (int) $course->ID;
        $user_id = get_current_user_id();
        
        $sections = get_post_meta( $id, '_course_sections', true ) ?: [];

        // Hydrate progress
        $progress_data = LMS_Progress::get_full_course_progress( $user_id, $id );
        $lesson_status_map = $progress_data['lessons'];

        // Get Recommendations (if enrolled via quiz)
        $enrollment = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare(
            "SELECT source, source_meta FROM {$GLOBALS['wpdb']->prefix}smc_enrollments WHERE user_id = %d AND course_id = %d",
            $user_id, $id
        ) );

        $recommended_sections = [];
        if ( $enrollment && 'quiz' === $enrollment->source && $enrollment->source_meta ) {
            $meta = json_decode( $enrollment->source_meta, true );
            $recommended_sections = $meta['recommended_sections'] ?? [];
        }
        
        // Hydrate lessons content
        $hydrated_sections = [];
        foreach ( $sections as $index => $section ) {
            $section_data = [
                'index' => $index,
                'title' => $section['title'],
                'lessons' => [],
                'is_recommended' => false,
            ];

            if ( ! empty( $recommended_sections ) ) {
                foreach ( $recommended_sections as $rec ) {
                     // Check if this recommendation block applies to current course
                     if ( isset( $rec['course_id'] ) && (int) $rec['course_id'] === $id ) {
                         if ( isset( $rec['section_indices'] ) && in_array( $index, $rec['section_indices'] ) ) {
                             $section_data['is_recommended'] = true;
                         }
                     }
                }
            }

            if ( ! empty( $section['lessons'] ) ) {
                foreach ( $section['lessons'] as $lesson_id ) {
                    $lesson = get_post( $lesson_id );
                    if ( ! $lesson ) continue;
                    
                    $type = get_post_meta( $lesson->ID, '_lesson_type', true ) ?: 'text';
                    $duration = get_post_meta( $lesson->ID, '_lesson_duration', true ) ?: 0;
                    $video_url = get_post_meta( $lesson->ID, '_lesson_video_url', true ) ?: '';
                    $video_caption = get_post_meta( $lesson->ID, '_lesson_video_caption', true ) ?: '';
                    $embed_settings = get_post_meta( $lesson->ID, '_lesson_embed_settings', true );
                    if ( ! is_array( $embed_settings ) ) {
                        $embed_settings = [
                            'autoplay' => false,
                            'loop'     => false,
                            'muted'    => false,
                            'controls' => true,
                        ];
                    }
                    
                    $status = $lesson_status_map[ $lesson->ID ] ?? 'not_started';

                    $section_data['lessons'][] = [
                        'id' => $lesson->ID,
                        'title' => $lesson->post_title,
                        'type' => $type,
                        'duration' => (int) $duration,
                        'status' => $status, 
                        'is_locked' => false, // Could implement sequential unlock here
                        'content' => apply_filters( 'the_content', $lesson->post_content ),
                        'video_url' => $video_url,
                        'video_caption' => sanitize_text_field( (string) $video_caption ),
                        'embed_settings' => [
                            'autoplay' => (bool) ( $embed_settings['autoplay'] ?? false ),
                            'loop'     => (bool) ( $embed_settings['loop'] ?? false ),
                            'muted'    => (bool) ( $embed_settings['muted'] ?? false ),
                            'controls' => (bool) ( $embed_settings['controls'] ?? true ),
                        ],
                    ];
                }
            }
            $hydrated_sections[] = $section_data;
        }

		return rest_ensure_response( [
            'id' => $id,
            'title' => $course->post_title,
            'sections' => $hydrated_sections,
            'progress' => $progress_data['overall_percent'],
            'instructor_profile' => $this->build_instructor_profile_payload( (int) $course->post_author ),
        ] );
	}

	/**
	 * Get course instructor profile by course lookup.
	 */
	public function get_instructor_profile( $request ) {
		$course = $this->resolve_course_post( $request );
		if ( ! $course || 'smc_training' !== $course->post_type ) {
			return new WP_Error( 'rest_course_invalid', 'Invalid course ID.', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->build_instructor_profile_payload( (int) $course->post_author ) );
	}

	/**
	 * Build normalized instructor profile payload from user meta.
	 */
	private function build_instructor_profile_payload( int $instructor_id ): array {
		$user = get_userdata( $instructor_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			return [
				'id'           => 0,
				'name'         => '',
				'avatar'       => '',
				'intro'        => '',
				'bio'          => '',
				'experience'   => '',
				'skills'       => [],
				'social_links' => [],
			];
		}

		$raw = get_user_meta( $instructor_id, '_smc_instructor_profile', true );
		$data = is_array( $raw ) ? $raw : [];
		$skills = [];
		if ( isset( $data['skills'] ) ) {
			if ( is_string( $data['skills'] ) ) {
				$data['skills'] = preg_split( '/[\n,]+/', $data['skills'] );
			}
			if ( is_array( $data['skills'] ) ) {
				foreach ( $data['skills'] as $skill ) {
					$label = sanitize_text_field( (string) $skill );
					if ( '' !== $label ) {
						$skills[] = $label;
					}
				}
			}
		}

		$social_keys = [ 'website', 'linkedin', 'twitter', 'facebook', 'instagram', 'youtube', 'tiktok' ];
		$social_input = ( isset( $data['social_links'] ) && is_array( $data['social_links'] ) ) ? $data['social_links'] : [];
		$social_links = [];
		foreach ( $social_keys as $key ) {
			$social_links[ $key ] = isset( $social_input[ $key ] ) ? esc_url_raw( (string) $social_input[ $key ] ) : '';
		}

		return [
			'id'           => (int) $user->ID,
			'name'         => (string) $user->display_name,
			'avatar'       => $this->resolve_instructor_avatar( $user, $data ),
			'intro'        => sanitize_text_field( (string) ( $data['intro'] ?? '' ) ),
			'bio'          => sanitize_textarea_field( (string) ( $data['bio'] ?? '' ) ),
			'experience'   => sanitize_textarea_field( (string) ( $data['experience'] ?? '' ) ),
			'skills'       => array_values( array_unique( $skills ) ),
			'social_links' => $social_links,
		];
	}

	/**
	 * Resolve instructor avatar from profile meta with fallback.
	 *
	 * @param \WP_User $user Instructor user.
	 * @param array    $profile Raw profile meta array.
	 * @return string
	 */
	private function resolve_instructor_avatar( \WP_User $user, array $profile ): string {
		$avatar_id = absint( $profile['avatar_id'] ?? 0 );
		if ( $avatar_id > 0 ) {
			$attachment_url = wp_get_attachment_image_url( $avatar_id, 'medium' );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				return esc_url_raw( $attachment_url );
			}
		}

		$avatar_url = esc_url_raw( (string) ( $profile['avatar'] ?? '' ) );
		if ( '' !== $avatar_url ) {
			return $avatar_url;
		}

		return get_avatar_url( $user->ID );
	}

	/**
	 * Resolve a course post from either numeric ID or slug route params.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_Post|null
	 */
	private function resolve_course_post( $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( $id > 0 ) {
			$post = get_post( $id );
			return $post instanceof \WP_Post ? $post : null;
		}

		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		if ( '' === $slug ) {
			return null;
		}

		$post = get_page_by_path( $slug, OBJECT, 'smc_training' );
		return $post instanceof \WP_Post ? $post : null;
	}
}
