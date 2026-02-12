<?php
namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seeder class to import comprehensive test data.
 *
 * Covers:
 *  - Lessons (video + text types)
 *  - Courses (standalone + plan-based at free/basic/standard)
 *  - Assessments (Basic + Advanced quizzes)
 *  - Quiz enrollment rules (score → course mapping)
 *  - Test users (free / basic / standard plan levels)
 *  - Enrollments (manual, invitation source types)
 *  - Products (plan subscription + standalone course purchase)
 *  - Partial progress data
 */
class Seeder {

    /**
     * Find a post by exact title and post type (replacement for deprecated get_page_by_title).
     *
     * @param string $title Post title.
     * @param string $post_type Post type.
     * @return \WP_Post|null
     */
    private static function find_post_by_title( string $title, string $post_type ): ?\WP_Post {
        $query = new \WP_Query( [
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'title'          => $title,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ] );

        if ( empty( $query->posts ) ) {
            return null;
        }

        $post_id = (int) $query->posts[0];
        return $post_id ? get_post( $post_id ) : null;
    }

    /**
     * Register WP-CLI commands.
     */
    public static function register_commands() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'smc seed', [ __CLASS__, 'seed_content' ] );
            require_once __DIR__ . '/class-legacy-migration-seeder.php';
            \WP_CLI::add_command( 'smc migrate-legacy', [ '\SMC\Viable\Legacy_Migration_Seeder', 'run_cli' ] );
        }
    }

    /**
     * Run legacy migration seeder and return logs.
     *
     * @return array<int,string>
     */
    public static function migrate_legacy_data(): array {
        require_once __DIR__ . '/class-legacy-migration-seeder.php';
        return Legacy_Migration_Seeder::run();
    }

    /**
     * Seed content.  Idempotent — skips items that already exist by title.
     *
     * @return array Logs of the operation.
     */
    public static function seed_content() {
        global $wpdb;
        Plan_Tiers::set_levels( [ 'free', 'basic', 'standard' ] );
        $data = self::get_data();
        $logs = [];

        // ──────────────────────────────────────────────
        // 1. Quizzes
        // ──────────────────────────────────────────────
        $quiz_map = []; // key → post ID
        foreach ( $data['assessments'] as $key => $quiz_data ) {
            $existing = self::find_post_by_title( $quiz_data['title'], 'smc_quiz' );
            if ( $existing ) {
                $quiz_map[ $key ] = $existing->ID;
                $logs[] = "Quiz '{$quiz_data['title']}' exists (ID: {$existing->ID}). Skipping.";
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'  => $quiz_data['title'],
                'post_type'   => 'smc_quiz',
                'post_status' => 'publish',
            ] );

            if ( ! is_wp_error( $post_id ) ) {
                $questions = array_map( function ( $item ) {
                    if ( ! isset( $item['options'] ) ) $item['options'] = [];
                    return $item;
                }, $quiz_data['items'] );

                if ( class_exists( '\SMC\Viable\Quiz_Question_Schema' ) ) {
                    update_post_meta( $post_id, '_smc_quiz_questions', \SMC\Viable\Quiz_Question_Schema::normalize_questions( $questions ) );
                } else {
                    update_post_meta( $post_id, '_smc_quiz_questions', $questions );
                }

                if ( isset( $quiz_data['plan_level'] ) ) {
                    update_post_meta( $post_id, '_smc_quiz_plan_level', Plan_Tiers::normalize_or_default( (string) $quiz_data['plan_level'], 'free' ) );
                }

                $quiz_map[ $key ] = $post_id;
                $logs[] = "✓ Created quiz: {$quiz_data['title']} (ID: $post_id)";
            }
        }

        // ──────────────────────────────────────────────
        // 2. Lessons
        // ──────────────────────────────────────────────
        $lesson_map = []; // key → post ID
        foreach ( $data['lessons'] as $key => $lesson_data ) {
            $existing = self::find_post_by_title( $lesson_data['title'], 'smc_lesson' );
            if ( $existing ) {
                $lesson_map[ $key ] = $existing->ID;
                $logs[] = "Lesson '{$lesson_data['title']}' exists (ID: {$existing->ID}). Skipping.";
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'   => $lesson_data['title'],
                'post_type'    => 'smc_lesson',
                'post_status'  => 'publish',
                'post_content' => $lesson_data['content'] ?? '',
            ] );

            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_lesson_type', $lesson_data['type'] ?? 'text' );
                if ( ! empty( $lesson_data['video'] ) ) {
                    update_post_meta( $post_id, '_lesson_video_url', $lesson_data['video'] );
                }
                if ( ! empty( $lesson_data['duration'] ) ) {
                    update_post_meta( $post_id, '_lesson_duration', (int) $lesson_data['duration'] );
                }
                $lesson_map[ $key ] = $post_id;
                $logs[] = "✓ Created lesson: {$lesson_data['title']} (ID: $post_id)";
            }
        }

        // ──────────────────────────────────────────────
        // 3. Courses — with sections wired to real lesson IDs
        // ──────────────────────────────────────────────
        $course_map = []; // key → post ID
        foreach ( $data['courses'] as $key => $course_data ) {
            $existing = self::find_post_by_title( $course_data['title'], 'smc_training' );
            if ( $existing ) {
                $course_map[ $key ] = $existing->ID;
                $logs[] = "Course '{$course_data['title']}' exists (ID: {$existing->ID}). Skipping.";
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'   => $course_data['title'],
                'post_type'    => 'smc_training',
                'post_status'  => 'publish',
                'post_content' => $course_data['description'] ?? '',
            ] );

            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_access_type', $course_data['access_type'] );
                if ( isset( $course_data['plan_level'] ) ) {
                    update_post_meta( $post_id, '_plan_level', Plan_Tiers::normalize_or_default( (string) $course_data['plan_level'], 'free' ) );
                }

                // Build sections array with resolved lesson IDs
                $sections = [];
                foreach ( $course_data['sections'] as $s ) {
                    $ids = [];
                    foreach ( $s['lesson_keys'] as $lk ) {
                        if ( isset( $lesson_map[ $lk ] ) ) {
                            $ids[] = $lesson_map[ $lk ];
                            update_post_meta( $lesson_map[ $lk ], '_parent_course_id', $post_id );
                        }
                    }
                    $sections[] = [
                        'title'   => $s['title'],
                        'lessons' => $ids,
                    ];
                }
                update_post_meta( $post_id, '_course_sections', $sections );

                $course_map[ $key ] = $post_id;
                $logs[] = "✓ Created course: {$course_data['title']} (ID: $post_id)";
            }
        }

        // ──────────────────────────────────────────────
        // 4. Products (Plan Subscriptions + Standalone Purchases)
        // ──────────────────────────────────────────────
        foreach ( $data['products'] as $key => $prod ) {
            $existing = self::find_post_by_title( $prod['title'], 'smc_product' );
            if ( $existing ) {
                $logs[] = "Product '{$prod['title']}' exists. Skipping.";
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'   => $prod['title'],
                'post_type'    => 'smc_product',
                'post_status'  => 'publish',
                'post_content' => $prod['description'] ?? '',
            ] );

            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_price', $prod['price'] );
                update_post_meta( $post_id, '_product_type', $prod['product_type'] );

                if ( $prod['product_type'] === 'plan' && isset( $prod['plan_level'] ) ) {
                    update_post_meta( $post_id, '_plan_level', Plan_Tiers::normalize_or_default( (string) $prod['plan_level'], 'free' ) );
                }
                if ( $prod['product_type'] === 'single' && isset( $prod['linked_course'] ) ) {
                    $cid = $course_map[ $prod['linked_course'] ] ?? 0;
                    if ( $cid ) {
                        update_post_meta( $post_id, '_linked_course_id', $cid );
                        update_post_meta( $post_id, '_linked_training_id', $cid );
                        update_post_meta( $cid, '_linked_product_id', $post_id );
                    }
                }
                if ( isset( $prod['features'] ) ) {
                    update_post_meta( $post_id, '_features', $prod['features'] );
                }

                $logs[] = "✓ Created product: {$prod['title']} (ID: $post_id)";
            }
        }

        // ──────────────────────────────────────────────
        // 5. Test Users (free / basic / standard)
        // ──────────────────────────────────────────────
        $user_map = []; // key → user ID
        foreach ( $data['test_users'] as $key => $u ) {
            $existing = get_user_by( 'email', $u['email'] );
            if ( $existing ) {
                $user_map[ $key ] = $existing->ID;
                // Ensure plan is set even if user already exists
                update_user_meta( $existing->ID, '_smc_user_plan', Plan_Tiers::normalize_or_default( (string) $u['plan'], 'free' ) );
                $logs[] = "User '{$u['email']}' exists (ID: {$existing->ID}). Plan set to '{$u['plan']}'.";
                continue;
            }

            $uid = wp_create_user( $u['username'], $u['password'], $u['email'] );
            if ( ! is_wp_error( $uid ) ) {
                $wp_user = get_userdata( $uid );
                $wp_user->set_role( $u['role'] );
                wp_update_user( [ 'ID' => $uid, 'display_name' => $u['display_name'] ] );
                update_user_meta( $uid, '_smc_user_plan', Plan_Tiers::normalize_or_default( (string) $u['plan'], 'free' ) );
                $user_map[ $key ] = $uid;
                $logs[] = "✓ Created user: {$u['display_name']} ({$u['email']}) — plan: {$u['plan']}";
            }
        }

        // ──────────────────────────────────────────────
        // 6. Enrollments (manual + invitation sources)
        // ──────────────────────────────────────────────
        if ( class_exists( '\SMC\Viable\Enrollment_Manager' ) ) {
            foreach ( $data['enrollments'] as $enr ) {
                $uid = $user_map[ $enr['user_key'] ] ?? 0;
                $cid = $course_map[ $enr['course_key'] ] ?? 0;
                if ( ! $uid || ! $cid ) continue;

                if ( Enrollment_Manager::is_enrolled( $uid, $cid ) ) {
                    $logs[] = "Enrollment exists: user {$enr['user_key']} in {$enr['course_key']}. Skipping.";
                    continue;
                }

                $eid = Enrollment_Manager::enroll_user( $uid, $cid, $enr['source'], $enr['meta'] ?? [] );
                if ( $eid ) {
                    $logs[] = "✓ Enrolled user {$enr['user_key']} in {$enr['course_key']} via {$enr['source']}";
                }
            }
        }

        // ──────────────────────────────────────────────
        // 7. Quiz Enrollment Rules (score → course unlock)
        // ──────────────────────────────────────────────
        foreach ( $data['quiz_enrollment_rules'] as $rule_set ) {
            $qid = $quiz_map[ $rule_set['quiz_key'] ] ?? 0;
            if ( ! $qid ) continue;

            // Only seed if rules are empty
            $existing_rules = get_post_meta( $qid, '_smc_quiz_enrollment_rules', true );
            if ( ! empty( $existing_rules ) ) {
                $logs[] = "Quiz rules for '{$rule_set['quiz_key']}' already set. Skipping.";
                continue;
            }

            $rules = [];
            foreach ( $rule_set['rules'] as $r ) {
                $course_ids = [];
                foreach ( $r['course_keys'] as $ck ) {
                    if ( isset( $course_map[ $ck ] ) ) {
                        $course_ids[] = $course_map[ $ck ];
                    }
                }
                $rules[] = [
                    'condition'            => $r['condition'],
                    'courses'              => $course_ids,
                    'recommended_sections' => $r['recommended_sections'] ?? [],
                ];
            }

            update_post_meta( $qid, '_smc_quiz_enrollment_rules', $rules );
            $logs[] = "✓ Set enrollment rules for quiz '{$rule_set['quiz_key']}' (" . count( $rules ) . " rules)";
        }

        // ──────────────────────────────────────────────
        // 8. Partial Progress (simulate a user mid-course)
        // ──────────────────────────────────────────────
        if ( class_exists( '\SMC\Viable\LMS_Progress' ) ) {
            foreach ( $data['progress'] as $prog ) {
                $uid = $user_map[ $prog['user_key'] ] ?? 0;
                $cid = $course_map[ $prog['course_key'] ] ?? 0;
                if ( ! $uid || ! $cid ) continue;

                foreach ( $prog['completed_lesson_keys'] as $lk ) {
                    $lid = $lesson_map[ $lk ] ?? 0;
                    if ( $lid ) {
                        LMS_Progress::complete_lesson( $uid, $lid, $cid );
                    }
                }
                $logs[] = "✓ Set progress for user {$prog['user_key']} in {$prog['course_key']} (" . count( $prog['completed_lesson_keys'] ) . " lessons)";
            }
        }

        // ──────────────────────────────────────────────
        // 9. Quiz Submissions (populated history)
        // ──────────────────────────────────────────────
        foreach ( $data['quiz_submissions'] as $sub ) {
            $uid = $user_map[ $sub['user_key'] ] ?? 0;
            $qid = $quiz_map[ $sub['quiz_key'] ] ?? 0;
            if ( ! $uid || ! $qid ) continue;

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}smc_quiz_submissions WHERE user_id = %d AND quiz_id = %d AND score = %d",
                $uid, $qid, $sub['score']
            ) );

            if ( ! $exists ) {
                $wpdb->insert(
                    "{$wpdb->prefix}smc_quiz_submissions",
                    [
                        'user_id'    => $uid,
                        'quiz_id'    => $qid,
                        'answers'    => json_encode( $sub['answers'] ?? [] ),
                        'score'      => $sub['score'],
                        'created_at' => $sub['date'] ?? current_time( 'mysql' ),
                    ]
                );
                $logs[] = "✓ Seeded quiz submission for user {$sub['user_key']} (Score: {$sub['score']})";
            }
        }

        // ──────────────────────────────────────────────
        // Summary
        // ──────────────────────────────────────────────
        $logs[] = '';
        $logs[] = '=== SEED SUMMARY ===';
        $logs[] = 'Quizzes: ' . count( $quiz_map );
        $logs[] = 'Lessons: ' . count( $lesson_map );
        $logs[] = 'Courses: ' . count( $course_map );
        $logs[] = 'Users:   ' . count( $user_map );
        $logs[] = '====================';

        return $logs;
    }

    // ══════════════════════════════════════════════════
    // DATA DEFINITIONS
    // ══════════════════════════════════════════════════

    private static function get_data() {
        return [

            // ── Lessons ──────────────────────────────
            'lessons' => [
                // Copywriting Masterclass lessons (standalone course)
                'copy_intro'   => [
                    'title'    => 'The Power of Words',
                    'type'     => 'video',
                    'video'    => 'https://www.youtube.com/watch?v=ScMzIvxBSi4', // Placeholder YouTube
                    'duration' => 12,
                    'content'  => '<h2>Why Copywriting?</h2><p>In this lesson we explore why copywriting is the single highest-ROI skill any entrepreneur can learn. Great copy turns strangers into customers and customers into advocates.</p><h3>Key Takeaways</h3><ul><li>Copy is the bridge between your product and your market.</li><li>Even small improvements in copy can 2–3× your conversion rates.</li><li>Every entrepreneur writes copy — emails, ads, proposals.</li></ul>',
                ],
                'copy_avatar'  => [
                    'title'    => 'Understanding Your Audience',
                    'type'     => 'text',
                    'duration' => 8,
                    'content'  => '<h2>Build Your Customer Avatar</h2><p>Before you write a single word, you need to know <em>exactly</em> who you\'re writing for. This lesson walks you through building a detailed customer avatar.</p><h3>The Avatar Framework</h3><ol><li><strong>Demographics:</strong> Age, income, location, occupation</li><li><strong>Psychographics:</strong> Values, fears, desires, frustrations</li><li><strong>Behaviour:</strong> Where they hang out online, what they read, who they follow</li></ol><blockquote>The best copy doesn\'t sell — it reflects the customer\'s own thoughts back at them.</blockquote>',
                ],
                'copy_aida'    => [
                    'title'    => 'The AIDA Formula',
                    'type'     => 'text',
                    'duration' => 10,
                    'content'  => '<h2>Attention → Interest → Desire → Action</h2><p>AIDA is the oldest and most reliable copywriting framework.</p><h3>How To Use AIDA</h3><p><strong>Attention:</strong> A bold headline that stops the scroll.<br/><strong>Interest:</strong> A hook that pulls them in — a statistic, story, or question.<br/><strong>Desire:</strong> Paint the after-state. What does life look like with your solution?<br/><strong>Action:</strong> A clear, single call to action.</p>',
                ],
                'copy_headlines' => [
                    'title'    => 'Headline Mastery',
                    'type'     => 'video',
                    'video'    => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                    'duration' => 15,
                    'content'  => '<h2>80% of people only read the headline</h2><p>Your headline is your first (and maybe only) impression. This lesson covers 7 proven headline formulas.</p>',
                ],

                // Business Foundations lessons (basic plan)
                'biz_entity'   => [
                    'title'    => 'Setting Up Your Entity',
                    'type'     => 'video',
                    'video'    => 'https://vimeo.com/836423971',
                    'duration' => 20,
                    'content'  => '<h2>Choosing the Right Business Structure</h2><p>Private Limited Company, Sole Trader, Partnership — each has different tax, liability, and compliance implications.</p><h3>Comparison Table</h3><p>We walk through a decision matrix to help you choose.</p>',
                ],
                'biz_finance'  => [
                    'title'    => 'Financial Fundamentals',
                    'type'     => 'text',
                    'duration' => 15,
                    'content'  => '<h2>The 3 Financial Statements</h2><p>Every business owner must understand Profit & Loss, Balance Sheet, and Cash Flow. This lesson breaks them down with real-world examples.</p>',
                ],
                'biz_tax'      => [
                    'title'    => 'Tax Obligations & Compliance',
                    'type'     => 'text',
                    'duration' => 12,
                    'content'  => '<h2>Know What You Owe</h2><p>From PAYE to VAT to provisional tax — a practical walkthrough of your obligations as a business owner.</p>',
                ],

                // Growth Hacking lessons (standard plan)
                'growth_ads'   => [
                    'title'    => 'Scaling via Paid Ads',
                    'type'     => 'video',
                    'video'    => 'https://vimeo.com/836423971',
                    'duration' => 25,
                    'content'  => '<h2>From $10/day to $10,000/day</h2><p>A systematic approach to scaling paid advertising while maintaining ROI. Covers Facebook, Google, and LinkedIn Ads.</p>',
                ],
                'growth_viral' => [
                    'title'    => 'Viral Loops & Product-Led Growth',
                    'type'     => 'text',
                    'duration' => 18,
                    'content'  => '<h2>Building Loops, Not Funnels</h2><p>Funnels leak. Loops compound. Learn how Dropbox, Slack, and Notion grew through product-led virality.</p>',
                ],
                'growth_retention' => [
                    'title'    => 'Retention & LTV Optimisation',
                    'type'     => 'video',
                    'video'    => 'https://vimeo.com/836423971',
                    'duration' => 20,
                    'content'  => '<h2>Stop the Leaky Bucket</h2><p>Acquiring a new customer costs 5× more than retaining one. Cohort analysis, churn prediction, and re-engagement campaigns.</p>',
                ],

                // Free course lessons
                'free_mindset' => [
                    'title'    => 'The Entrepreneurial Mindset',
                    'type'     => 'video',
                    'video'    => 'https://vimeo.com/836423971',
                    'duration' => 10,
                    'content'  => '<h2>Think Like a Founder</h2><p>The most important asset in business is your mindset. This free lesson introduces the growth-oriented thinking successful entrepreneurs share.</p>',
                ],
                'free_ideas'   => [
                    'title'    => 'Validating Your Idea',
                    'type'     => 'text',
                    'duration' => 8,
                    'content'  => '<h2>Don\'t Build What Nobody Wants</h2><p>Lean validation techniques — talk to customers before you write code or sign a lease.</p>',
                ],

                // African Business Strategy (New Course)
                'abs_intro' => [
                    'title'    => 'The Informal Economy',
                    'type'     => 'video',
                    'video'    => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                    'duration' => 14,
                    'content'  => '<h2>The Hidden Engine of Growth</h2><p>The informal sector accounts for 80% of employment in many African nations. Understanding how it operates is key to unlocking mass-market strategies.</p>',
                ],
                'abs_logistics' => [
                    'title'    => 'Supply Chain Logistics',
                    'type'     => 'video',
                    'video'    => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                    'duration' => 22,
                    'content'  => '<h2>Overcoming Last-Mile Challenges</h2><p>How successful companies navigate infrastructure gaps to deliver goods reliably.</p>',
                ],
                'abs_mobile' => [
                    'title'    => 'Mobile Money Revolution',
                    'type'     => 'text',
                    'duration' => 10,
                    'content'  => '<h2>Leapfrogging Banking</h2><p>Mobile money isn\'t just payments; it\'s credit, savings, and insurance. We study the MPESA model and its replicability.</p>',
                ],
            ],

            // ── Courses ─────────────────────────────
            'courses' => [
                // Standalone — requires enrollment (purchase/invite/manual)
                'copywriting' => [
                    'title'       => 'Copywriting Masterclass',
                    'description' => 'The ultimate guide to conversion-focused writing for entrepreneurs.',
                    'access_type' => 'standalone',
                    'sections'    => [
                        [
                            'title'       => 'Module 1: Foundations',
                            'lesson_keys' => [ 'copy_intro', 'copy_avatar' ],
                        ],
                        [
                            'title'       => 'Module 2: Frameworks & Formulas',
                            'lesson_keys' => [ 'copy_aida', 'copy_headlines' ],
                        ],
                    ],
                ],

                // Plan: free — accessible to everyone
                'starter_kit' => [
                    'title'       => 'Entrepreneur Starter Kit',
                    'description' => 'Free introductory course for aspiring entrepreneurs.',
                    'access_type' => 'plan',
                    'plan_level'  => 'free',
                    'sections'    => [
                        [
                            'title'       => 'Getting Started',
                            'lesson_keys' => [ 'free_mindset', 'free_ideas' ],
                        ],
                    ],
                ],

                // Plan: basic — requires Basic plan or higher
                'biz_foundations' => [
                    'title'       => 'Business Foundations',
                    'description' => 'Essential legal, financial, and compliance knowledge.',
                    'access_type' => 'plan',
                    'plan_level'  => 'basic',
                    'sections'    => [
                        [
                            'title'       => 'Module 1: Legal & Structure',
                            'lesson_keys' => [ 'biz_entity' ],
                        ],
                        [
                            'title'       => 'Module 2: Finance & Tax',
                            'lesson_keys' => [ 'biz_finance', 'biz_tax' ],
                        ],
                    ],
                ],

                // Plan: standard — requires Standard plan
                'growth_hacking' => [
                    'title'       => 'Extreme Growth Hacking',
                    'description' => 'Advanced strategies for rapid, profitable scaling.',
                    'access_type' => 'plan',
                    'plan_level'  => 'standard',
                    'sections'    => [
                        [
                            'title'       => 'Module 1: Paid Acquisition',
                            'lesson_keys' => [ 'growth_ads' ],
                        ],
                        [
                            'title'       => 'Module 2: Product-Led & Retention',
                            'lesson_keys' => [ 'growth_viral', 'growth_retention' ],
                        ],
                    ],
                ],

                // African Business Strategy
                'african_strategy' => [
                    'title'       => 'African Business Strategy',
                    'description' => 'Navigating the unique challenges and opportunities of the African market.',
                    'access_type' => 'standalone',
                    'sections'    => [
                        [
                            'title'       => 'Module 1: Market Context',
                            'lesson_keys' => [ 'abs_intro', 'abs_mobile' ],
                        ],
                        [
                            'title'       => 'Module 2: Operations',
                            'lesson_keys' => [ 'abs_logistics' ],
                        ],
                    ],
                ],
            ],

            // ── Products ────────────────────────────
            'products' => [
                'plan_basic' => [
                    'title'        => 'Basic Plan — Monthly',
                    'description'  => 'Access to all Basic-tier courses.',
                    'price'        => 29,
                    'product_type' => 'plan',
                    'plan_level'   => 'basic',
                    'features'     => [ 'All Basic courses', 'Community access', 'Monthly webinars' ],
                ],
                'plan_standard' => [
                    'title'        => 'Standard Plan — Monthly',
                    'description'  => 'Full access to every course including advanced growth strategies.',
                    'price'        => 79,
                    'product_type' => 'plan',
                    'plan_level'   => 'standard',
                    'features'     => [ 'All courses (Basic + Standard)', '1-on-1 coaching', 'Priority support' ],
                ],
                'single_copywriting' => [
                    'title'         => 'Copywriting Masterclass — One-time',
                    'description'   => 'Lifetime access to the Copywriting Masterclass.',
                    'price'         => 149,
                    'product_type'  => 'single',
                    'linked_course' => 'copywriting',
                    'features'      => [ 'Lifetime access', '4 video + text lessons', 'Certificate of completion' ],
                ],
                'single_abs' => [
                    'title'         => 'African Business Strategy — One-time',
                    'description'   => 'Unlock the secrets of the continent\'s informal economy.',
                    'price'         => 199,
                    'product_type'  => 'single',
                    'linked_course' => 'african_strategy',
                    'features'      => [ 'Lifetime access', 'Mobile Money Case Studies', 'Logistics Frameworks' ],
                ],
            ],

            // ── Test Users ──────────────────────────
            'test_users' => [
                'student_free' => [
                    'username'     => 'student_free',
                    'email'        => 'free@test.local',
                    'password'     => 'TestPass123!',
                    'display_name' => 'Free Student',
                    'role'         => 'subscriber',
                    'plan'         => 'free',
                ],
                'student_basic' => [
                    'username'     => 'student_basic',
                    'email'        => 'basic@test.local',
                    'password'     => 'TestPass123!',
                    'display_name' => 'Basic Student',
                    'role'         => 'subscriber',
                    'plan'         => 'basic',
                ],
                'student_standard' => [
                    'username'     => 'student_standard',
                    'email'        => 'standard@test.local',
                    'password'     => 'TestPass123!',
                    'display_name' => 'Standard Student',
                    'role'         => 'subscriber',
                    'plan'         => 'standard',
                ],
                'instructor' => [
                    'username'     => 'instructor_test',
                    'email'        => 'instructor@test.local',
                    'password'     => 'TestPass123!',
                    'display_name' => 'Test Instructor',
                    'role'         => 'editor',
                    'plan'         => 'standard',
                ],
            ],

            // ── Enrollments ─────────────────────────
            // Test: invitation, manual, purchase sources
            'enrollments' => [
                // Basic student enrolled via invitation in Copywriting
                [
                    'user_key'   => 'student_basic',
                    'course_key' => 'copywriting',
                    'source'     => 'invitation',
                    'meta'       => [ 'invited_by' => 'instructor' ],
                ],
                // Standard student manually enrolled in Copywriting
                [
                    'user_key'   => 'student_standard',
                    'course_key' => 'copywriting',
                    'source'     => 'manual',
                ],
                // Free student enrolled via invitation in Starter Kit (redundant with plan access, tests both paths)
                [
                    'user_key'   => 'student_free',
                    'course_key' => 'starter_kit',
                    'source'     => 'invitation',
                ],
            ],

            // ── Quiz Enrollment Rules ────────────────
            // Test: passing Basic Assessment → unlocks Copywriting + Biz Foundations
            'quiz_enrollment_rules' => [
                [
                    'quiz_key' => 'basic',
                    'rules'    => [
                        [
                            'condition'     => [ 'operator' => 'gte', 'value' => 50 ],
                            'course_keys'   => [ 'copywriting', 'biz_foundations' ],
                            'recommended_sections' => [ 'Module 1: Foundations' ],
                        ],
                        [
                            'condition'     => [ 'operator' => 'gte', 'value' => 80 ],
                            'course_keys'   => [ 'growth_hacking' ],
                            'recommended_sections' => [],
                        ],
                    ],
                ],
                [
                    'quiz_key' => 'advanced',
                    'rules'    => [
                        [
                            'condition'     => [ 'operator' => 'gte', 'value' => 60 ],
                            'course_keys'   => [ 'growth_hacking' ],
                            'recommended_sections' => [ 'Module 1: Paid Acquisition' ],
                        ],
                    ],
                ],
            ],

            // ── Progress (partial completion) ─────────
            // Basic student has completed 2 of 4 lessons in Copywriting (50%)
            'progress' => [
                [
                    'user_key'              => 'student_basic',
                    'course_key'            => 'copywriting',
                    'completed_lesson_keys' => [ 'copy_intro', 'copy_avatar' ],
                ],
                // Free student completed full Starter Kit
                [
                    'user_key'              => 'student_free',
                    'course_key'            => 'starter_kit',
                    'completed_lesson_keys' => [ 'free_mindset', 'free_ideas' ],
                ],
            ],

            // ── Assessments (Quizzes) ────────────────
            'assessments' => [
                'free_public' => [
                    'title'      => 'Free Business Viability Assessment',
                    'plan_level' => 'free',
                    'items'      => [
                        [
                            'id'       => 'basic_6',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Nature of Business',
                            'text'     => 'What is the nature of your business?',
                            'key_text' => 'Describe what your business does.',
                            'type'     => 'text',
                        ],
                        [
                            'id'       => 'basic_7',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Type of Business',
                            'text'     => 'Which category best describes your business?',
                            'key_text' => 'Services',
                            'type'     => 'select',
                            'options'  => [ 'Services', 'Manufacturing', 'Retail', 'Agriculture', 'Technology' ],
                        ],
                        [
                            'id'       => 'basic_9',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Operational Duration',
                            'text'     => 'When did your business commence operations?',
                            'key_text' => 'Less than 6 months',
                            'type'     => 'date',
                        ],
                        [
                            'id'        => 'basic_10',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Market Readiness',
                            'text'      => 'How developed is the market for your product/service?',
                            'key_text'  => 'Example: No Ready Market',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_11',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Necessity',
                            'text'      => 'Is your product/service considered essential or a \'nice-to-have\'?',
                            'key_text'  => 'Example: Essential Product/Service',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_12',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Uniqueness',
                            'text'      => 'How unique is your offering compared to competitors?',
                            'key_text'  => 'Example: Unique Product/Service',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_13',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Product Completeness',
                            'text'      => 'Is your product fully developed and ready for sale?',
                            'key_text'  => 'Example: Established & Ready Product',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_17',
                            'stage'     => 'Operational Strategy & Capability',
                            'indicator' => 'Outsourcing Level',
                            'text'      => 'What percentage of your operations are outsourced?',
                            'key_text'  => 'Example: Close to 10%',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_18',
                            'stage'     => 'Operational Strategy & Capability',
                            'indicator' => 'Strategic Fit',
                            'text'      => 'How closely related is this new idea to your main business core?',
                            'key_text'  => 'Example: Related',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_19',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Margins & Volume',
                            'text'      => 'Do you have strong profit margins or high sales volume?',
                            'key_text'  => 'Example: Great Volume Great Margin',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_20',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Capital Expenditure',
                            'text'      => 'Is your business setup expensive (CAPEX intensive)?',
                            'key_text'  => 'Example: No CAPEX',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_21',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Revenue Model',
                            'text'      => 'Is your revenue recurring (subscription/repeat) or one-off?',
                            'key_text'  => 'Example: High Initial Fee + Recurring Rev',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_22',
                            'stage'     => 'Investment & Future Readiness',
                            'indicator' => 'Financial Independence',
                            'text'      => 'Are you dependent on external funding or self-sponsored?',
                            'key_text'  => 'Example: Independent',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_23',
                            'stage'     => 'Investment & Future Readiness',
                            'indicator' => 'Independence Timeline',
                            'text'      => 'How soon will the business be operationally independent?',
                            'key_text'  => 'Example: Less than 6 months',
                            'type'      => 'scorable',
                        ],
                    ],
                ],
                'basic' => [
                    'title'      => 'Basic Assessment',
                    'plan_level' => 'free',
                    'items'      => [
                        [
                            'id'       => 'basic_6',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Nature of Business',
                            'text'     => 'What is the nature of your business?',
                            'key_text' => 'Describe what your business does.',
                            'type'     => 'text',
                        ],
                        [
                            'id'       => 'basic_7',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Type of Business',
                            'text'     => 'Which category best describes your business?',
                            'key_text' => 'Services',
                            'type'     => 'select',
                            'options'  => [ 'Services', 'Manufacturing', 'Retail', 'Agriculture', 'Technology' ],
                        ],
                        [
                            'id'       => 'basic_9',
                            'stage'    => 'Foundation & Legal',
                            'indicator' => 'Operational Duration',
                            'text'     => 'When did your business commence operations?',
                            'key_text' => 'Less than 6 months',
                            'type'     => 'date',
                        ],
                        [
                            'id'        => 'basic_10',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Market Readiness',
                            'text'      => 'How developed is the market for your product/service?',
                            'key_text'  => 'Example: No Ready Market',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_11',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Necessity',
                            'text'      => 'Is your product/service considered essential or a \'nice-to-have\'?',
                            'key_text'  => 'Example: Essential Product/Service',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_12',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Uniqueness',
                            'text'      => 'How unique is your offering compared to competitors?',
                            'key_text'  => 'Example: Unique Product/Service',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_13',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Product Completeness',
                            'text'      => 'Is your product fully developed and ready for sale?',
                            'key_text'  => 'Example: Established & Ready Product',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_17',
                            'stage'     => 'Operational Strategy & Capability',
                            'indicator' => 'Outsourcing Level',
                            'text'      => 'What percentage of your operations are outsourced?',
                            'key_text'  => 'Example: Close to 10%',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_18',
                            'stage'     => 'Operational Strategy & Capability',
                            'indicator' => 'Strategic Fit',
                            'text'      => 'How closely related is this new idea to your main business core?',
                            'key_text'  => 'Example: Related',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_19',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Margins & Volume',
                            'text'      => 'Do you have strong profit margins or high sales volume?',
                            'key_text'  => 'Example: Great Volume Great Margin',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_20',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Capital Expenditure',
                            'text'      => 'Is your business setup expensive (CAPEX intensive)?',
                            'key_text'  => 'Example: No CAPEX',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_21',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Revenue Model',
                            'text'      => 'Is your revenue recurring (subscription/repeat) or one-off?',
                            'key_text'  => 'Example: High Initial Fee + Recurring Rev',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_22',
                            'stage'     => 'Investment & Future Readiness',
                            'indicator' => 'Financial Independence',
                            'text'      => 'Are you dependent on external funding or self-sponsored?',
                            'key_text'  => 'Example: Independent',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'basic_23',
                            'stage'     => 'Investment & Future Readiness',
                            'indicator' => 'Independence Timeline',
                            'text'      => 'How soon will the business be operationally independent?',
                            'key_text'  => 'Example: Less than 6 months',
                            'type'      => 'scorable',
                        ],
                    ],
                ],
                'advanced' => [
                    'title'      => 'Advanced Assessment',
                    'plan_level' => 'basic',
                    'items'      => [
                        [
                            'id'        => 'advanced_16',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Market Potential',
                            'text'      => 'What is the estimated size of your target demographic?',
                            'key_text'  => 'Example: > 25% of Target Demographic',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'advanced_17',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Competitive Landscape',
                            'text'      => 'Describe your competition level and market growth.',
                            'key_text'  => 'Example: Few (0-1) direct rivals; Market is growing.',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'advanced_18',
                            'stage'     => 'Market & Offering',
                            'indicator' => 'Proprietary Edge',
                            'text'      => 'Do you have registered trademarks or proprietary technology?',
                            'key_text'  => 'Example: Registered Trademark AND Proprietary Process/Tech',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'advanced_27',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Recurring Revenue',
                            'text'      => 'Is your revenue model based on recurring fees?',
                            'key_text'  => 'Example: Once Off Fee',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'advanced_30',
                            'stage'     => 'Financial Health & Economics',
                            'indicator' => 'Core Profitability',
                            'text'      => 'What is your net profit margin?',
                            'key_text'  => 'Example: >25% Net Profit Margin.',
                            'type'      => 'scorable',
                        ],
                        [
                            'id'        => 'advanced_32',
                            'stage'     => 'Investment & Future Readiness',
                            'indicator' => 'Time to Profitability',
                            'text'      => 'How long until you break even?',
                            'key_text'  => 'Example: ≤6 Months.',
                            'type'      => 'scorable',
                        ],
                    ],
                ],
            ],

            // ── Quiz Submissions ──────────────────
            'quiz_submissions' => [
                [
                    'user_key' => 'student_basic',
                    'quiz_key' => 'basic',
                    'score'    => 72,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-5 days') ),
                    'answers'  => [ 'basic_10' => 70, 'basic_11' => 80 ],
                ],
                [
                    'user_key' => 'student_standard',
                    'quiz_key' => 'basic',
                    'score'    => 85,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-10 days') ),
                    'answers'  => [ 'basic_10' => 90, 'basic_11' => 80 ],
                ],
                [
                    'user_key' => 'student_standard',
                    'quiz_key' => 'advanced',
                    'score'    => 65,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-2 days') ),
                    'answers'  => [ 'advanced_30' => 70 ],
                ],
            ],

            // ── Quiz Submissions ──────────────────
            'quiz_submissions' => [
                [
                    'user_key' => 'student_basic',
                    'quiz_key' => 'basic',
                    'score'    => 72,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-5 days') ),
                    'answers'  => [ 'basic_10' => 70, 'basic_11' => 80 ],
                ],
                [
                    'user_key' => 'student_standard',
                    'quiz_key' => 'basic',
                    'score'    => 85,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-10 days') ),
                    'answers'  => [ 'basic_10' => 90, 'basic_11' => 80 ],
                ],
                [
                    'user_key' => 'student_standard',
                    'quiz_key' => 'advanced',
                    'score'    => 65,
                    'date'     => date( 'Y-m-d H:i:s', strtotime('-2 days') ),
                    'answers'  => [ 'advanced_30' => 70 ],
                ],
            ],

        ];
    }
}
