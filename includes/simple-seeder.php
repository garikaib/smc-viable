<?php
/**
 * Seeder for Shop and Training data.
 */

require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/class-plan-tiers.php';
\SMC\Viable\Plan_Tiers::set_levels( [ 'free', 'basic', 'standard' ] );

// 1. Update Quiz Levels
update_post_meta( 1055, '_smc_quiz_plan_level', 'basic' );
update_post_meta( 1053, '_smc_quiz_plan_level', 'standard' );

echo "Quizzes 1055 (Basic) and 1053 (Standard) updated.\n";

// 2. Create Products
$products = [
    [
        'title' => 'Basic Access Plan',
        'content' => 'Unlock basic training materials and assessment results.',
        'long_description' => 'Start your journey with our Basic Access Plan. This tier provides essential insights into your business viability, giving you access to foundational training modules and a simplified assessment report. Perfect for early-stage startups looking to get their footing.',
        'price' => 5,
        'type' => 'plan',
        'level' => 'basic',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/my-networking-apparel-XgBJkn4Y0pM-unsplash.jpg',
        'features' => ['Basic Assessment Report', 'Access to Foundation Training', 'Monthly Business Tips']
    ],
    [
        'title' => 'Standard Full Access',
        'content' => 'Complete access to all training, priority support, and deep-dive results.',
        'long_description' => 'Accelerate your growth with Premium Full Access. This comprehensive plan unlocks our entire library of advanced training modules, deep-dive analytical reports, and priority support. Ideal for scaling businesses ready to dominate their market.',
        'price' => 10,
        'type' => 'plan',
        'level' => 'standard',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/daniel-thomas-HA-0i0E7sq4-unsplash.jpg',
        'features' => ['Full Viability Report', 'All Advanced Training Modules', 'Priority Support', 'Quarterly Strategy Review', 'Downloadable Templates']
    ],
    // dummy products
    [ 
        'title' => 'Core Business Block', 
        'content' => 'One-off purchase for the advanced core business training module.', 
        'long_description' => 'The Core Business Block is the cornerstone of any successful enterprise. Learn the fundamental principles of business operations, from legal structures to value propositions.',
        'price' => 25, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/ali-mkumbwa-EOkN2pRjFsg-unsplash.jpg',
        'features' => ['Legal Structures', 'Value Proposition Design', 'Operational Basics']
    ],
    [ 
        'title' => 'Lean Startup Mastery', 
        'content' => 'Master the art of lean startup methodology.', 
        'long_description' => 'Stop wasting time and money. Lean Startup Mastery teaches you how to build, measure, and learn. Validate your ideas quickly and pivot with confidence.',
        'price' => 45, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/nick-morrison-FHnnjk1Yj7Y-unsplash.jpg',
        'features' => ['MVP Development', 'Build-Measure-Learn Loop', 'Pivot Strategies', 'Customer Validation']
    ],
    [ 
        'title' => 'Financial Modeling 101', 
        'content' => 'Essential financial modeling for new businesses.', 
        'long_description' => 'Numbers tell the story of your business. Financial Modeling 101 equips you with the skills to create robust financial projections, cash flow statements, and balance sheets.',
        'price' => 30, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/unseen-studio-s9CC2SKySJM-unsplash.jpg',
        'features' => ['Cash Flow Formatting', 'Projection Logic', 'Balance Sheet Basics']
    ],
    [ 
        'title' => 'Marketing for Scale', 
        'content' => 'Strategies to scale your marketing efforts.', 
        'long_description' => 'Ready to grow? Marketing for Scale dives into growth hacking, channel optimization, and brand positioning to help you reach a wider audience effectively.',
        'price' => 50, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/my-networking-apparel-XgBJkn4Y0pM-unsplash.jpg',
        'features' => ['Growth Hacking', 'Channel Optimization', 'Brand Positioning']
    ],
    // ... adding a few more for variety
    [ 
        'title' => 'Leadership Psychology', 
        'content' => 'Understanding the psychology behind effective leadership.', 
        'long_description' => 'Great leaders are made, not born. Explore the psychological principles that drive effective leadership, team motivation, and organizational culture.',
        'price' => 55, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/daniel-thomas-HA-0i0E7sq4-unsplash.jpg',
        'features' => ['Emotional Intelligence', 'Team Motivation', 'Conflict Resolution']
    ],
    [ 
        'title' => 'Data Analytics for Biz', 
        'content' => 'Leverage data to drive business decisions.', 
        'long_description' => 'Data is the new oil. Learn how to collect, analyze, and interpret business data to make informed strategic decisions.',
        'price' => 48, 
        'type' => 'single', 
        'level' => '',
        'image' => 'https://smc-wp.ddev.site/wp-content/uploads/2026/02/ali-mkumbwa-EOkN2pRjFsg-unsplash.jpg',
        'features' => ['KPI Tracking', 'Data Visualization', 'Decision Frameworks']
    ]
];

foreach ( $products as $p ) {
    $existing = get_page_by_title( $p['title'], OBJECT, 'smc_product' );
    $id = 0;
    $slug = sanitize_title( $p['title'] );
    
    if ( ! $existing ) {
        $id = wp_insert_post( [
            'post_title' => $p['title'],
            'post_content' => $p['content'],
            'post_type' => 'smc_product',
            'post_status' => 'publish',
            'post_name' => $slug
        ] );
    } else {
        $id = $existing->ID;
        // Update content if existing (optional, but good for refreshing data)
        wp_update_post([
            'ID' => $id,
            'post_content' => $p['content'],
            'post_name' => $slug
        ]);
    }
    
    if ( $id ) {
        update_post_meta( $id, '_price', $p['price'] );
        update_post_meta( $id, '_product_type', $p['type'] );
        update_post_meta( $id, '_plan_level', $p['level'] );
        
        // Update new meta
        if ( isset( $p['long_description'] ) ) update_post_meta( $id, '_long_description', $p['long_description'] );
        if ( isset( $p['features'] ) ) update_post_meta( $id, '_features', $p['features'] );
        
        // Update Featured Image (Sideload from URL might be needed if not local, but we'll try strictly if user provided WP uploads URL)
        // Since the URLs are local to the site, we can try to find the attachment ID by URL or just set it if we had a helper.
        // For now, let's assume valid attachment ID lookup is complex without a helper.
        // We'll skip actual attachment assignment for now unless we write a helper, 
        // BUT we can use the URL directly in our React app if we pass it as a meta or just handle it.
        // Standard WP way: set featured image. 
        // Let's store the image URL in meta for easy access if not setting featured image formally.
        if ( isset( $p['image'] ) ) update_post_meta( $id, '_smc_product_image_url', $p['image'] );
    }
}

echo "Dummy products created.\n";

// 3. Create Training Materials
$training = [
    [
        'title' => 'Market Entry Strategy',
        'quiz_id' => 1055,
        'category' => 'Market & Offering'
    ],
    [
        'title' => 'Advanced Business Optimization',
        'quiz_id' => 1053,
        'category' => 'Execution'
    ]
];

foreach ( $training as $t ) {
    $existing = get_page_by_title( $t['title'], OBJECT, 'smc_training' );
    if ( ! $existing ) {
        $id = wp_insert_post( [
            'post_title' => $t['title'],
            'post_content' => '<!-- wp:paragraph --><p>This is a seeded training module for ' . $t['title'] . '. It is locked to members.</p><!-- /wp:paragraph -->',
            'post_type' => 'smc_training',
            'post_status' => 'publish'
        ] );
        update_post_meta( $id, '_linked_quiz_id', $t['quiz_id'] );
        update_post_meta( $id, '_linked_quiz_category', $t['category'] );
    }
}

echo "Training modules seeded.\n";

// 4. Create Shop Page
$pages_to_create = [
    [
        'title'   => 'Shop',
        'content' => '[smc_shop]',
        'slug'    => 'shop'
    ],
    [
        'title'   => 'Student Hub',
        'content' => '[smc_student_hub]',
        'slug'    => 'learning'
    ],
    [
        'title'   => 'Instructor Hub',
        'content' => '[smc_instructor_hub]',
        'slug'    => 'instructor'
    ]
];

$shop_id = 0; // Keep track of shop ID for menu

foreach ( $pages_to_create as $page_data ) {
    $existing = get_page_by_path( $page_data['slug'] );
    if ( ! $existing ) {
        $page_id = wp_insert_post( [
            'post_title'   => $page_data['title'],
            'post_content' => $page_data['content'],
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_name'    => $page_data['slug']
        ] );
        echo "{$page_data['title']} page created.\n";
        
        if ( $page_data['slug'] === 'shop' ) {
            $shop_id = $page_id;
        }
    } else {
        if ( $page_data['slug'] === 'shop' ) {
            $shop_id = $existing->ID;
        }
    }
}

// 5. Add to Menu (Attempting to find main menu)
$menus = wp_get_nav_menus();
if ( ! empty( $menus ) ) {
    $menu = $menus[0]; // Take first one or find by name 'Primary'
    foreach($menus as $m) {
        if ( stripos($m->name, 'primary') !== false || stripos($m->name, 'main') !== false ) {
            $menu = $m;
            break;
        }
    }
    
    // Check if Shop is already in menu
    $items = wp_get_nav_menu_items( $menu->term_id );
    $exists = false;
    foreach ( $items as $item ) {
        if ( (int) $item->object_id === $shop_id ) {
            $exists = true;
            break;
        }
    }
    
    if ( ! $exists ) {
        wp_update_nav_menu_item( $menu->term_id, 0, [
            'menu-item-title'     => 'Shop',
            'menu-item-object-id' => $shop_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ] );
        echo "Shop added to " . $menu->name . " menu.\n";
    }

    // Add to Footer Training Menu
    $footer_menu = wp_get_nav_menu_object( 'SMC Footer - Training' );
    if ( $footer_menu ) {
         $items = wp_get_nav_menu_items( $footer_menu->term_id );
         $exists = false;
         foreach ( $items as $item ) {
             if ( (int) $item->object_id === $shop_id ) {
                 $exists = true;
                 break;
             }
         }
         if ( ! $exists ) {
             wp_update_nav_menu_item( $footer_menu->term_id, 0, [
                 'menu-item-title'     => 'Shop',
                 'menu-item-object-id' => $shop_id,
                 'menu-item-object'    => 'page',
                 'menu-item-type'      => 'post_type',
                 'menu-item-status'    => 'publish',
             ] );
             echo "Shop added to Footer Training menu.\n";
         }
    }
}
