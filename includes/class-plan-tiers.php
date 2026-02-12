<?php
/**
 * Plan tier registry and comparison helpers.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plan_Tiers {
    private const OPTION_KEY = 'smc_plan_tiers_order';
    private const DEFAULT_LEVELS = [ 'free', 'basic', 'standard' ];

    /**
     * Legacy aliases to canonical plan slugs.
     *
     * @var array<string,string>
     */
    private const ALIASES = [
        'public'   => 'free',
        'premium'  => 'standard',
        'pro'      => 'standard',
        'advanced' => 'standard',
    ];

    /**
     * Known canonical slugs accepted by this build.
     *
     * @var array<int,string>
     */
    private const KNOWN_LEVELS = [ 'free', 'basic', 'standard' ];

    /**
     * Default plan order from lowest to highest.
     *
     * @return array<int,string>
     */
    public static function get_levels(): array {
        $stored = get_option( self::OPTION_KEY, self::DEFAULT_LEVELS );
        $levels = [];

        if ( is_string( $stored ) ) {
            $stored = explode( ',', $stored );
        }

        if ( is_array( $stored ) ) {
            foreach ( $stored as $level ) {
                $normalized = self::normalize_loose( (string) $level );
                if ( '' !== $normalized ) {
                    $levels[] = $normalized;
                }
            }
        }

        if ( empty( $levels ) ) {
            $levels = self::DEFAULT_LEVELS;
        }

        if ( ! in_array( 'free', $levels, true ) ) {
            array_unshift( $levels, 'free' );
        }

        $levels = array_values( array_unique( $levels ) );

        $filtered = apply_filters( 'smc_plan_tiers_order', $levels );
        if ( is_array( $filtered ) && ! empty( $filtered ) ) {
            $levels = [];
            foreach ( $filtered as $level ) {
                $normalized = self::normalize_loose( (string) $level );
                if ( '' !== $normalized ) {
                    $levels[] = $normalized;
                }
            }
            if ( ! in_array( 'free', $levels, true ) ) {
                array_unshift( $levels, 'free' );
            }
            $levels = array_values( array_unique( $levels ) );
        }

        return $levels;
    }

    /**
     * Persist plan order in wp_options.
     *
     * @param array<int,string> $levels
     */
    public static function set_levels( array $levels ): void {
        $normalized = [];
        foreach ( $levels as $level ) {
            $value = self::normalize_loose( (string) $level );
            if ( '' !== $value ) {
                $normalized[] = $value;
            }
        }
        if ( empty( $normalized ) ) {
            $normalized = self::DEFAULT_LEVELS;
        }
        if ( ! in_array( 'free', $normalized, true ) ) {
            array_unshift( $normalized, 'free' );
        }
        $normalized = array_values( array_unique( $normalized ) );
        update_option( self::OPTION_KEY, $normalized, false );
    }

    public static function ensure_default_levels(): void {
        if ( false === get_option( self::OPTION_KEY, false ) ) {
            self::set_levels( self::DEFAULT_LEVELS );
        }
    }

    public static function get_paid_levels(): array {
        return array_values(
            array_filter(
                self::get_levels(),
                static function ( string $level ): bool {
                    return 'free' !== $level;
                }
            )
        );
    }

    public static function normalize( string $plan ): string {
        $plan = self::normalize_loose( $plan );
        return in_array( $plan, self::get_levels(), true ) ? $plan : '';
    }

    public static function normalize_or_default( string $plan, string $default = 'free' ): string {
        $normalized = self::normalize( $plan );
        if ( '' !== $normalized ) {
            return $normalized;
        }

        $fallback = self::normalize( $default );
        return '' !== $fallback ? $fallback : 'free';
    }

    public static function rank( string $plan ): int {
        $normalized = self::normalize_or_default( $plan );
        $levels = self::get_levels();
        $index = array_search( $normalized, $levels, true );
        return false === $index ? 0 : (int) $index;
    }

    public static function compare( string $user_plan, string $required_plan ): bool {
        return self::rank( $user_plan ) >= self::rank( $required_plan );
    }

    private static function normalize_loose( string $plan ): string {
        $plan = sanitize_key( $plan );
        if ( isset( self::ALIASES[ $plan ] ) ) {
            $plan = self::ALIASES[ $plan ];
        }

        return in_array( $plan, self::KNOWN_LEVELS, true ) ? $plan : '';
    }

    /**
     * @return array<string,string> slug => label
     */
    public static function get_level_labels(): array {
        $default_labels = [
            'free'     => 'Free Plan',
            'basic'    => 'Basic',
            'standard' => 'Standard',
        ];

        $labels = [];
        foreach ( self::get_levels() as $level ) {
            $labels[ $level ] = $default_labels[ $level ] ?? ucfirst( $level );
        }

        $filtered = apply_filters( 'smc_plan_tier_labels', $labels );
        return is_array( $filtered ) ? $filtered : $labels;
    }

    /**
     * @return array<int,array{label:string,value:string}>
     */
    public static function get_level_options(): array {
        $options = [];
        foreach ( self::get_level_labels() as $slug => $label ) {
            $options[] = [ 'label' => $label, 'value' => $slug ];
        }
        return $options;
    }
}
