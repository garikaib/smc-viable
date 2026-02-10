<?php
/**
 * Seeder for Lessons and Course Sections.
 * Usage: ddev exec php wp-content/plugins/smc-viable/includes/lesson-seeder.php
 */

require_once __DIR__ . '/../../../../wp-load.php';

// Check if Course Exists
$course_product = get_page_by_title( 'Lean Startup Mastery', OBJECT, 'smc_product' );

if ( ! $course_product ) {
    die( "Error: 'Lean Startup Mastery' product not found. Run simple-seeder.php first.\n" );
}

echo "Seeding content for 'Lean Startup Mastery' (ID: {$course_product->ID})...\n";

// Define Lessons
$lessons_data = [
    [
        'title' => 'Welcome to Lean Startup',
        'type' => 'video',
        'duration' => 5,
        'content' => 'Introduction video content here...',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', // Dummy URL
    ],
    [
            'title' => 'The Build-Measure-Learn Loop',
            'type' => 'text',
            'duration' => 10,
            'content' => '<!-- wp:paragraph --><p>The <strong>Build-Measure-Learn</strong> feedback loop is at the core of the Lean Startup model.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>It emphasizes speed as a critical ingredient to product development.</p><!-- /wp:paragraph -->',
    ],
    [
            'title' => 'MVP Definition',
            'type' => 'video',
            'duration' => 15,
            'content' => 'Video about MVPs.',
             'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ],
        [
            'title' => 'Pivot or Persevere',
            'type' => 'quiz', 
            'duration' => 20,
            'content' => 'Quiz placeholder.',
    ]
];

$created_lessons = [];

foreach ( $lessons_data as $l ) {
    $existing_lesson = get_page_by_title( $l['title'], OBJECT, 'smc_lesson' );
    $lid = 0;
    
    if ( ! $existing_lesson ) {
        $lid = wp_insert_post( [
            'post_title' => $l['title'],
            'post_content' => $l['content'],
            'post_type' => 'smc_lesson',
            'post_status' => 'publish'
        ] );
        echo "Created lesson: {$l['title']}\n";
    } else {
        $lid = $existing_lesson->ID;
        echo "Found lesson: {$l['title']}\n";
    }
    
    // Update Meta
    update_post_meta( $lid, '_lesson_type', $l['type'] );
    update_post_meta( $lid, '_lesson_duration', $l['duration'] );
    update_post_meta( $lid, '_parent_course_id', $course_product->ID );
    
    if ( ! empty( $l['video_url'] ) ) {
        update_post_meta( $lid, '_lesson_video_url', $l['video_url'] );
    }
    
    $created_lessons[] = $lid;
}

// Structure Sections
// Distribute lessons into sections
if ( count( $created_lessons ) >= 4 ) {
        $sections = [
        [
            'title' => 'Module 1: Foundations',
            'lessons' => [ $created_lessons[0], $created_lessons[1] ]
        ],
        [
            'title' => 'Module 2: Advanced Techniques',
            'lessons' => [ $created_lessons[2], $created_lessons[3] ]
        ]
    ];
    
    update_post_meta( $course_product->ID, '_course_sections', $sections );
    echo "Updated course sections for 'Lean Startup Mastery'.\n";
}

echo "Lesson seeding complete.\n";
