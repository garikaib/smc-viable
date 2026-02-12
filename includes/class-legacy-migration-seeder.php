<?php
/**
 * Legacy data migration seeder.
 *
 * Upgrades older SMC test data into the current access/shop schema.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Legacy_Migration_Seeder {
    /**
     * Execute full migration.
     *
     * @return array<int,string>
     */
    public static function run(): array {
        $logs = [];
        Plan_Tiers::set_levels( [ 'free', 'basic', 'standard' ] );

        $product_changes = self::migrate_products_and_links();
        $course_changes = self::migrate_courses_access_and_links();
        $quiz_changes = self::migrate_quiz_shop_settings();
        $order_changes = self::migrate_orders();
        $user_changes = self::migrate_user_plans_and_enrollments();

        $logs[] = 'Legacy migration completed.';
        $logs[] = sprintf(
            'Products: %d updated | Courses: %d updated | Quizzes: %d updated | Orders: %d updated | Users: %d updated',
            $product_changes,
            $course_changes,
            $quiz_changes,
            $order_changes,
            $user_changes
        );

        return $logs;
    }

    /**
     * WP-CLI wrapper.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Assoc args.
     */
    public static function run_cli( array $args = [], array $assoc_args = [] ): void {
        $logs = self::run();

        if ( defined( 'WP_CLI' ) && \WP_CLI ) {
            foreach ( $logs as $line ) {
                \WP_CLI::log( $line );
            }
            \WP_CLI::success( 'Legacy migration finished.' );
        }
    }

    private static function migrate_products_and_links(): int {
        $changed = 0;
        $product_ids = get_posts( [
            'post_type'      => 'smc_product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $product_ids as $product_id ) {
            $product_id = (int) $product_id;
            $raw_type = sanitize_key( (string) get_post_meta( $product_id, '_product_type', true ) );
            $raw_plan = self::normalize_plan_slug( (string) get_post_meta( $product_id, '_plan_level', true ) );

            $linked_course_id = self::get_product_linked_course_id( $product_id );
            $normalized_type = self::normalize_product_type( $raw_type, $linked_course_id > 0, '' !== $raw_plan );
            if ( self::update_meta_if_changed( $product_id, '_product_type', $normalized_type ) ) {
                $changed++;
            }

            if ( 'plan' === $normalized_type ) {
                $next_plan = '' !== $raw_plan ? $raw_plan : 'free';
                if ( self::update_meta_if_changed( $product_id, '_plan_level', $next_plan ) ) {
                    $changed++;
                }
            } else {
                if ( delete_post_meta( $product_id, '_plan_level' ) ) {
                    $changed++;
                }
            }

            if ( $linked_course_id > 0 ) {
                if ( self::update_meta_if_changed( $product_id, '_linked_training_id', $linked_course_id ) ) {
                    $changed++;
                }
                if ( self::update_meta_if_changed( $product_id, '_linked_course_id', $linked_course_id ) ) {
                    $changed++;
                }
                if ( self::update_meta_if_changed( $linked_course_id, '_linked_product_id', $product_id ) ) {
                    $changed++;
                }
            }
        }

        return $changed;
    }

    private static function migrate_courses_access_and_links(): int {
        $changed = 0;
        $course_ids = get_posts( [
            'post_type'      => 'smc_training',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $course_ids as $course_id ) {
            $course_id = (int) $course_id;
            $linked_product_id = (int) get_post_meta( $course_id, '_linked_product_id', true );
            if ( $linked_product_id <= 0 ) {
                $linked_product_id = self::find_product_by_linked_course_id( $course_id );
                if ( $linked_product_id > 0 && self::update_meta_if_changed( $course_id, '_linked_product_id', $linked_product_id ) ) {
                    $changed++;
                }
            }

            if ( $linked_product_id > 0 ) {
                if ( self::update_meta_if_changed( $linked_product_id, '_linked_training_id', $course_id ) ) {
                    $changed++;
                }
                if ( self::update_meta_if_changed( $linked_product_id, '_linked_course_id', $course_id ) ) {
                    $changed++;
                }
            }

            $legacy_plan = self::normalize_plan_slug( (string) get_post_meta( $course_id, '_plan_level', true ) );

            $modes = self::collect_course_modes( $course_id );
            $plans = self::collect_course_plan_access( $course_id );

            if ( '' !== $legacy_plan && ! in_array( $legacy_plan, $plans, true ) ) {
                $plans[] = $legacy_plan;
            }

            if ( ! empty( $plans ) && ! in_array( 'plan', $modes, true ) ) {
                $modes[] = 'plan';
            }

            if ( $linked_product_id > 0 && ! in_array( 'standalone', $modes, true ) ) {
                $modes[] = 'standalone';
            }

            if ( empty( $modes ) ) {
                $modes = [ 'standalone' ];
            }

            $modes = self::sanitize_modes( $modes );
            $plans = self::sanitize_plans( $plans );

            if ( in_array( 'plan', $modes, true ) && empty( $plans ) ) {
                $plans = [ 'free' ];
            }
            if ( ! in_array( 'plan', $modes, true ) ) {
                $plans = [];
            }

            if ( self::update_meta_if_changed( $course_id, '_smc_access_modes', $modes ) ) {
                $changed++;
            }
            wp_set_post_terms( $course_id, $modes, 'smc_access_mode', false );

            if ( self::update_meta_if_changed( $course_id, '_smc_allowed_plans', $plans ) ) {
                $changed++;
            }
            wp_set_post_terms( $course_id, $plans, 'smc_plan_access', false );

            $legacy_access_type = ( 1 === count( $modes ) && in_array( 'standalone', $modes, true ) ) ? 'standalone' : 'plan';
            if ( self::update_meta_if_changed( $course_id, '_access_type', $legacy_access_type ) ) {
                $changed++;
            }

            if ( ! empty( $plans ) ) {
                $legacy_min_plan = self::min_plan_by_rank( $plans );
                if ( self::update_meta_if_changed( $course_id, '_plan_level', $legacy_min_plan ) ) {
                    $changed++;
                }
            } else {
                if ( delete_post_meta( $course_id, '_plan_level' ) ) {
                    $changed++;
                }
            }
        }

        return $changed;
    }

    private static function migrate_orders(): int {
        $changed = 0;
        $order_ids = get_posts( [
            'post_type'      => 'smc_order',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $order_ids as $order_id ) {
            $order_id = (int) $order_id;
            $items = get_post_meta( $order_id, '_order_items', true );
            if ( ! is_array( $items ) ) {
                continue;
            }

            if ( isset( $items['product_id'] ) || isset( $items['id'] ) ) {
                $items = [ $items ];
            }

            $normalized = [];
            foreach ( $items as $item ) {
                if ( is_numeric( $item ) ) {
                    $product_id = (int) $item;
                } elseif ( is_array( $item ) ) {
                    $product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : (int) ( $item['id'] ?? 0 );
                } else {
                    $product_id = 0;
                }

                if ( $product_id <= 0 ) {
                    continue;
                }

                $product = get_post( $product_id );
                $price = (float) get_post_meta( $product_id, '_price', true );
                $normalized[] = [
                    'product_id' => $product_id,
                    'name'       => $product ? $product->post_title : '',
                    'price'      => $price,
                ];
            }

            if ( self::update_meta_if_changed( $order_id, '_order_items', $normalized ) ) {
                $changed++;
            }

            $total = 0.0;
            foreach ( $normalized as $entry ) {
                $total += (float) ( $entry['price'] ?? 0 );
            }
            if ( self::update_meta_if_changed( $order_id, '_order_total', (float) $total ) ) {
                $changed++;
            }
        }

        return $changed;
    }

    private static function migrate_quiz_shop_settings(): int {
        $changed = 0;
        $quiz_ids = get_posts( [
            'post_type'      => 'smc_quiz',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $quiz_ids as $quiz_id ) {
            $quiz_id = (int) $quiz_id;
            if ( self::update_meta_if_changed( $quiz_id, '_smc_quiz_plan_level', Plan_Tiers::normalize_or_default( (string) get_post_meta( $quiz_id, '_smc_quiz_plan_level', true ), 'free' ) ) ) {
                $changed++;
            }

            $shop = get_post_meta( $quiz_id, '_smc_quiz_shop', true );
            if ( is_string( $shop ) ) {
                $decoded = json_decode( $shop, true );
                if ( is_array( $decoded ) ) {
                    $shop = $decoded;
                }
            }
            if ( ! is_array( $shop ) ) {
                continue;
            }

            $mode = sanitize_key( (string) ( $shop['access_mode'] ?? 'standalone' ) );
            if ( ! in_array( $mode, [ 'standalone', 'plan', 'both' ], true ) ) {
                $mode = 'standalone';
            }

            $normalized_shop = [
                'enabled'       => ! empty( $shop['enabled'] ),
                'access_mode'   => $mode,
                'assigned_plan' => Plan_Tiers::normalize_or_default( (string) ( $shop['assigned_plan'] ?? 'free' ), 'free' ),
                'price'         => isset( $shop['price'] ) ? (float) $shop['price'] : 0.0,
                'features'      => is_array( $shop['features'] ?? null )
                    ? array_values( array_filter( array_map( 'sanitize_text_field', $shop['features'] ) ) )
                    : [],
            ];

            if ( self::update_meta_if_changed( $quiz_id, '_smc_quiz_shop', $normalized_shop ) ) {
                $changed++;
            }
        }

        return $changed;
    }

    private static function migrate_user_plans_and_enrollments(): int {
        $changed = 0;
        $user_ids = get_users( [
            'fields' => 'ids',
            'number' => -1,
        ] );

        foreach ( $user_ids as $user_id ) {
            $user_id = (int) $user_id;
            $current = (string) get_user_meta( $user_id, '_smc_user_plan', true );
            $normalized = self::normalize_plan_slug( $current );
            if ( '' === $normalized ) {
                $normalized = 'free';
            }

            if ( (string) get_user_meta( $user_id, '_smc_user_plan', true ) !== $normalized ) {
                update_user_meta( $user_id, '_smc_user_plan', $normalized );
                $changed++;
            }

            Enrollment_Manager::resolve_user_plan( $user_id, true );
            Enrollment_Manager::reconcile_user_purchase_enrollments( $user_id );
        }

        return $changed;
    }

    private static function collect_course_modes( int $course_id ): array {
        $modes = [];
        $terms = wp_get_post_terms( $course_id, 'smc_access_mode', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
            $modes = array_merge( $modes, $terms );
        }

        $meta_modes = get_post_meta( $course_id, '_smc_access_modes', true );
        if ( is_array( $meta_modes ) ) {
            $modes = array_merge( $modes, $meta_modes );
        } elseif ( is_string( $meta_modes ) && '' !== $meta_modes ) {
            $modes[] = $meta_modes;
        }

        $legacy_mode = sanitize_key( (string) get_post_meta( $course_id, '_access_type', true ) );
        if ( in_array( $legacy_mode, [ 'standalone', 'plan' ], true ) ) {
            $modes[] = $legacy_mode;
        }

        return $modes;
    }

    private static function collect_course_plan_access( int $course_id ): array {
        $plans = [];
        $terms = wp_get_post_terms( $course_id, 'smc_plan_access', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
            $plans = array_merge( $plans, $terms );
        }

        $meta_plans = get_post_meta( $course_id, '_smc_allowed_plans', true );
        if ( is_array( $meta_plans ) ) {
            $plans = array_merge( $plans, $meta_plans );
        } elseif ( is_string( $meta_plans ) && '' !== $meta_plans ) {
            $plans[] = $meta_plans;
        }

        return $plans;
    }

    private static function get_product_linked_course_id( int $product_id ): int {
        $linked = (int) get_post_meta( $product_id, '_linked_training_id', true );
        if ( $linked <= 0 ) {
            $linked = (int) get_post_meta( $product_id, '_linked_course_id', true );
        }
        if ( $linked <= 0 ) {
            $linked = self::find_course_by_linked_product_id( $product_id );
        }
        return $linked;
    }

    private static function find_course_by_linked_product_id( int $product_id ): int {
        $ids = get_posts( [
            'post_type'      => 'smc_training',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_linked_product_id',
                    'value' => $product_id,
                ],
            ],
            'fields'         => 'ids',
        ] );

        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    private static function find_product_by_linked_course_id( int $course_id ): int {
        $ids = get_posts( [
            'post_type'      => 'smc_product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_linked_training_id',
                    'value' => $course_id,
                ],
                [
                    'key'   => '_linked_course_id',
                    'value' => $course_id,
                ],
            ],
            'fields'         => 'ids',
        ] );

        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    private static function normalize_product_type( string $type, bool $has_course_link, bool $has_plan_level ): string {
        $type = sanitize_key( $type );

        if ( in_array( $type, [ 'plan', 'membership', 'subscription', 'tier' ], true ) || $has_plan_level ) {
            return 'plan';
        }

        if ( $has_course_link || in_array( $type, [ 'single', 'course', 'module', 'standalone', 'training' ], true ) ) {
            return 'single';
        }

        return 'service';
    }

    private static function normalize_plan_slug( string $plan ): string {
        return Plan_Tiers::normalize( $plan );
    }

    private static function sanitize_modes( array $modes ): array {
        $out = [];
        foreach ( $modes as $mode ) {
            $mode = sanitize_key( (string) $mode );
            if ( in_array( $mode, [ 'standalone', 'plan' ], true ) ) {
                $out[] = $mode;
            }
        }

        return array_values( array_unique( $out ) );
    }

    private static function sanitize_plans( array $plans ): array {
        $out = [];
        foreach ( $plans as $plan ) {
            $normalized = self::normalize_plan_slug( (string) $plan );
            if ( '' !== $normalized ) {
                $out[] = $normalized;
            }
        }

        $out = array_values( array_unique( $out ) );
        usort( $out, function ( string $a, string $b ): int {
            return self::plan_rank( $a ) <=> self::plan_rank( $b );
        } );

        return $out;
    }

    private static function min_plan_by_rank( array $plans ): string {
        $plans = self::sanitize_plans( $plans );
        return ! empty( $plans ) ? $plans[0] : 'free';
    }

    private static function plan_rank( string $plan ): int {
        return Plan_Tiers::rank( $plan );
    }

    /**
     * Update post meta only when changed.
     *
     * @param int $post_id Post ID.
     * @param string $key Meta key.
     * @param mixed $value Meta value.
     */
    private static function update_meta_if_changed( int $post_id, string $key, $value ): bool {
        $existing = get_post_meta( $post_id, $key, true );

        if ( maybe_serialize( $existing ) === maybe_serialize( $value ) ) {
            return false;
        }

        update_post_meta( $post_id, $key, $value );
        return true;
    }
}
