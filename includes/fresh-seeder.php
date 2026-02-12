<?php
/**
 * Fresh Seeder - Nukes and re-seeds SMC data.
 * Usage: ddev exec php wp-content/plugins/smc-viable/includes/fresh-seeder.php
 */

require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/class-plan-tiers.php';
\SMC\Viable\Plan_Tiers::set_levels( [ 'free', 'basic', 'standard' ] );

if ( php_sapi_name() !== 'cli' ) {
    die( "This script must be run from the CLI.\n" );
}

global $wpdb;

/**
 * 1. Nuke everything
 */
echo "Nuking existing SMC data...\n";

// Delete all relevant post types
$post_types = ['smc_product', 'smc_training', 'smc_lesson', 'smc_order'];
foreach ( $post_types as $pt ) {
    $posts = get_posts( [
        'post_type'      => $pt,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ] );

    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }
    echo "Deleted " . count( $posts ) . " posts of type: $pt\n";
}

// Truncate custom tables
$tables = [
    $wpdb->prefix . 'smc_enrollments',
    $wpdb->prefix . 'smc_quiz_submissions',
    $wpdb->prefix . 'smc_notes',
    $wpdb->prefix . 'smc_progress'
];

foreach ( $tables as $table ) {
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
        $wpdb->query( "TRUNCATE TABLE $table" );
        echo "Truncated table: $table\n";
    }
}

/**
 * 2. Seed Plans
 */
echo "Seeding Plans...\n";

$plans = [
    [
        'title' => 'Basic Plan',
        'level' => 'basic',
        'price' => 5,
        'desc'  => 'Get started with our basic selection of courses.'
    ],
    [
        'title' => 'Standard Plan',
        'level' => 'standard',
        'price' => 10,
        'desc'  => 'The most popular plan with access to all standard curriculum.'
    ]
];

foreach ( $plans as $p ) {
    $plan_id = wp_insert_post( [
        'post_title'   => $p['title'],
        'post_type'    => 'smc_product',
        'post_status'  => 'publish',
        'post_content' => $p['desc'],
    ] );

    update_post_meta( $plan_id, '_product_type', 'plan' );
    update_post_meta( $plan_id, '_plan_level', $p['level'] );
    update_post_meta( $plan_id, '_price', $p['price'] );
    
    echo "Created {$p['title']} (Level: {$p['level']}, Price: \${$p['price']})\n";
}

/**
 * 3. Seed Courses
 */
echo "Seeding Standalone Courses...\n";

$courses = [
    [
        'title' => 'Lean Startup Mastery',
        'price' => 49,
        'image' => 'https://images.unsplash.com/photo-1519389950473-acc7b968b3d1?auto=format&fit=crop&w=400&q=80'
    ],
    [
        'title' => 'Advanced SEO Tactics 2026',
        'price' => 99,
        'image' => 'https://images.unsplash.com/photo-1504868584819-f8e905263a93?auto=format&fit=crop&w=400&q=80'
    ],
    [
        'title' => 'Copywriting for Conversions',
        'price' => 29,
        'image' => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=400&q=80'
    ],
    [
        'title' => 'Growth Hacking Fundamentals',
        'price' => 79,
        'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=400&q=80'
    ],
    [
        'title' => 'Full Stack Architecture',
        'price' => 199,
        'image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=400&q=80'
    ]
];

foreach ( $courses as $c ) {
    // A. Create Training Post
    $training_id = wp_insert_post( [
        'post_title'   => $c['title'],
        'post_type'    => 'smc_training',
        'post_status'  => 'publish',
        'post_content' => 'Comprehensive course on ' . $c['title'] . '. Includes certification.',
        'post_author'  => 1,
    ] );

    // B. Create Product Post
    $product_id = wp_insert_post( [
        'post_title'   => $c['title'],
        'post_type'    => 'smc_product',
        'post_status'  => 'publish',
        'post_content' => 'Buy lifetime access to ' . $c['title'] . '.',
    ] );

    // C. Linkage & Meta
    update_post_meta( $training_id, '_linked_product_id', $product_id );
    update_post_meta( $training_id, '_access_type', 'standalone' );
    update_post_meta( $training_id, '_smc_access_modes', ['standalone'] );
    
    update_post_meta( $product_id, '_linked_training_id', $training_id );
    update_post_meta( $product_id, '_linked_course_id', $training_id );
    update_post_meta( $product_id, '_product_type', 'single' );
    update_post_meta( $product_id, '_price', $c['price'] );

    // D. Lessons
    $lessons = [];
    $lesson_titles = [
        'Getting Started with ' . $c['title'],
        'Core Principles and Frameworks',
        'Practical Exercise: First Steps',
        'Advanced Implementation Strategies'
    ];

    foreach ( $lesson_titles as $index => $lt ) {
        $lesson_id = wp_insert_post( [
            'post_title'   => $lt,
            'post_type'    => 'smc_lesson',
            'post_status'  => 'publish',
            'post_content' => "<!-- wp:paragraph --><p>Welcome to lesson " . ($index + 1) . " of " . $c['title'] . ".</p><!-- /wp:paragraph -->" .
                            "<!-- wp:paragraph --><p>In this lesson, we will cover the fundamental concepts required to master this subject.</p><!-- /wp:paragraph -->",
        ] );

        update_post_meta( $lesson_id, '_lesson_type', ( $index % 2 === 0 ) ? 'video' : 'text' );
        update_post_meta( $lesson_id, '_lesson_duration', 10 + ( $index * 5 ) );
        update_post_meta( $lesson_id, '_parent_course_id', $training_id );
        
        if ( ( $index % 2 === 0 ) ) {
            update_post_meta( $lesson_id, '_lesson_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
        }

        $lessons[] = $lesson_id;
    }

    // E. Sections
    $sections = [
        [
            'title'   => 'Module 1: Introduction',
            'lessons' => [ $lessons[0], $lessons[1] ]
        ],
        [
            'title'   => 'Module 2: Depth and Practice',
            'lessons' => [ $lessons[2], $lessons[3] ]
        ]
    ];
    update_post_meta( $training_id, '_course_sections', $sections );

    echo "Seeded Course: {$c['title']} (Training ID: $training_id, Product ID: $product_id)\n";
}

echo "\n--- Seeding Complete ---\n";
