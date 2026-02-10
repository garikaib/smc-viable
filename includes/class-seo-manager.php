<?php
/**
 * SEO Manager for JSON-LD.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SEO_Manager
 */
class SEO_Manager {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'inject_course_schema' ] );
	}

    /**
     * Inject Course Schema.
     */
    public static function inject_course_schema(): void {
        if ( ! is_singular( 'smc_product' ) ) {
            return;
        }

        global $post;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $post->post_title,
            'description' => wp_strip_all_tags( $post->post_content ),
            'provider' => [
                '@type' => 'Organization',
                'name' => get_bloginfo( 'name' ),
                'sameAs' => home_url(),
            ],
            'offers' => [
                '@type' => 'Offer',
                'category' => 'Paid',
            ],
        ];

        echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
    }
}
