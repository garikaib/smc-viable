<?php
namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hero_Seeder {

    public static function seed_defaults(): void {
        $quizzes = get_posts( [
            'post_type'      => 'smc_quiz',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );

        foreach ( $quizzes as $quiz ) {
            $existing_title = get_post_meta( $quiz->ID, '_smc_quiz_hero_title', true );
            
            // Only seed if empty
            if ( empty( $existing_title ) ) {
                update_post_meta( $quiz->ID, '_smc_quiz_hero_title', 'Viability Assessment' );
                update_post_meta( $quiz->ID, '_smc_quiz_hero_subtitle', 'Measure your business against world-class standards.' );
                update_post_meta( $quiz->ID, '_smc_quiz_hero_bg', content_url( '/uploads/2024/01/business-science-hero.jpg' ) ); // Placeholder or default
            }
        }
    }
}
