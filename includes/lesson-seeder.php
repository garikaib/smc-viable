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

// Check if linked Course/Training exists
$training_id = (int) get_post_meta( $course_product->ID, '_linked_training_id', true );
if ( ! $training_id ) {
    $existing_training = get_page_by_title( 'Lean Startup Mastery', OBJECT, 'smc_training' );
    if ( $existing_training ) {
        $training_id = $existing_training->ID;
        // Link them if not linked
        update_post_meta( $course_product->ID, '_linked_training_id', $training_id );
        update_post_meta( $training_id, '_linked_product_id', $course_product->ID );
    }
}

if ( ! $training_id ) {
     // Create it if missing (fallback)
     echo "Creating missing 'smc_training' post...\n";
     $training_id = wp_insert_post( [
        'post_title'   => 'Lean Startup Mastery',
        'post_type'    => 'smc_training',
        'post_status'  => 'publish',
        'post_content' => $course_product->post_content,
    ] );
    update_post_meta( $course_product->ID, '_linked_training_id', $training_id );
    update_post_meta( $training_id, '_linked_product_id', $course_product->ID );
}

echo "Seeding content for Training Course (ID: {$training_id})...\n";

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
    update_post_meta( $lid, '_parent_course_id', $training_id );
    
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
    
    update_post_meta( $training_id, '_course_sections', $sections );
    echo "Updated course sections for 'Lean Startup Mastery' (Training ID: $training_id).\n";
}

echo "Lesson seeding complete.\n";
