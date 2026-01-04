<?php
namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Seeder class to import default data.
 */
class Seeder {

    /**
     * Register WP-CLI commands.
     */
    public static function register_commands() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'smc seed', [ __CLASS__, 'seed_content' ] );
        }
    }

    /**
     * Seed content.
     * 
     * @return array Logs of the operation.
     */
    public static function seed_content() {
        $data = self::get_data();
        $logs = [];

        foreach ( $data['assessments'] as $key => $quiz_data ) {
            $existing = get_page_by_title( $quiz_data['title'], OBJECT, 'smc_quiz' );
            
            if ( $existing ) {
                $msg = "Quiz '{$quiz_data['title']}' already exists. Skipping.";
                $logs[] = $msg;
                if ( defined( 'WP_CLI' ) && WP_CLI ) \WP_CLI::log( $msg );
                continue;
            }

            $post_id = wp_insert_post( [
                'post_title'  => $quiz_data['title'],
                'post_type'   => 'smc_quiz',
                'post_status' => 'publish',
            ] );

            if ( ! is_wp_error( $post_id ) ) {
                // Ensure questions array is properly structured for the frontend
                $questions = array_map( function( $item ) {
                    // Ensure options is an array if missing
                    if ( ! isset( $item['options'] ) ) {
                        $item['options'] = [];
                    }
                    return $item;
                }, $quiz_data['items'] );

                update_post_meta( $post_id, '_smc_quiz_questions', $questions );
                
                $msg = "Created quiz: {$quiz_data['title']} (ID: $post_id)";
                $logs[] = $msg;
                if ( defined( 'WP_CLI' ) && WP_CLI ) \WP_CLI::success( $msg );
            } else {
                $msg = "Failed to create quiz: {$quiz_data['title']}";
                $logs[] = "ERROR: $msg";
                if ( defined( 'WP_CLI' ) && WP_CLI ) \WP_CLI::error( $msg );
            }
        }
        
        return $logs;
    }

    /**
     * Get default data (converted from assessment-config.js).
     */
    private static function get_data() {
        return [
            'assessments' => [
                'basic' => [
                    'title' => 'Basic Assessment',
                    'items' => [
                        [
                            'id' => 'basic_6',
                            'stage' => 'Foundation & Legal',
                            'indicator' => 'Nature of Business',
                            'text' => 'What is the nature of your business?', // Renaming 'question' to 'text' to match our schema
                            'key_text' => 'Describe what your business does.',
                            'type' => 'text'
                        ],
                        [
                            'id' => 'basic_7',
                            'stage' => 'Foundation & Legal',
                            'indicator' => 'Type of Business',
                            'text' => 'Which category best describes your business?',
                            'key_text' => 'Services',
                            'type' => 'select', // Mapping 'select' to 'select' (dropdown unscored)
                            'options' => ['Services', 'Manufacturing', 'Retail', 'Agriculture', 'Technology']
                        ],
                        [
                            'id' => 'basic_9',
                            'stage' => 'Foundation & Legal',
                            'indicator' => 'Operational Duration',
                            'text' => 'When did your business commence operations?',
                            'key_text' => 'Less than 6 months',
                            'type' => 'date'
                        ],
                        [
                            'id' => 'basic_10',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Market Readiness',
                            'text' => 'How developed is the market for your product/service?',
                            'key_text' => 'Example: No Ready Market',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_11',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Necessity',
                            'text' => 'Is your product/service considered essential or a \'nice-to-have\'?',
                            'key_text' => 'Example: Essential Product/Service',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_12',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Uniqueness',
                            'text' => 'How unique is your offering compared to competitors?',
                            'key_text' => 'Example: Unique Product/Service',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_13',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Product Completeness',
                            'text' => 'Is your product fully developed and ready for sale, or does it require more research?',
                            'key_text' => 'Example: Established & Ready Product',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_17',
                            'stage' => 'Operational Strategy & Capability',
                            'indicator' => 'Outsourcing Level',
                            'text' => 'What percentage of your operations are outsourced?',
                            'key_text' => 'Example: Close to 10%',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_18',
                            'stage' => 'Operational Strategy & Capability',
                            'indicator' => 'Strategic Fit',
                            'text' => 'How closely related is this new idea to your main business core?',
                            'key_text' => 'Example: Related',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_19',
                            'stage' => 'Financial Health & Economics',
                            'indicator' => 'Margins & Volume',
                            'text' => 'Do you have strong profit margins or high sales volume?',
                            'key_text' => 'Example: Great Volume Great Margin',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_20',
                            'stage' => 'Financial Health & Economics',
                            'indicator' => 'Capital Expenditure',
                            'text' => 'Is your business setup expensive (CAPEX intensive)?',
                            'key_text' => 'Example: No CAPEX',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_21',
                            'stage' => 'Financial Health & Economics',
                            'indicator' => 'Revenue Model',
                            'text' => 'Is your revenue recurring (subscription/repeat) or one-off?',
                            'key_text' => 'Example: High Initial Fee + Recurring Rev',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_22',
                            'stage' => 'Investment & Future Readiness',
                            'indicator' => 'Financial Independence',
                            'text' => 'Are you dependent on external funding or self-sponsored?',
                            'key_text' => 'Example: Independent',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'basic_23',
                            'stage' => 'Investment & Future Readiness',
                            'indicator' => 'Independence Timeline',
                            'text' => 'How soon will the business be operationally independent?',
                            'key_text' => 'Example: Less than 6 months',
                            'type' => 'scorable'
                        ]
                    ]
                ],
                'advanced' => [
                    'title' => 'Advanced Assessment',
                    'items' => [
                        [
                            'id' => 'advanced_16',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Market Potential',
                            'text' => 'What is the estimated size of your target demographic?',
                            'key_text' => 'Example: > 25% of Target Demographic',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'advanced_17',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Competitive Landscape',
                            'text' => 'Describe your competition level and market growth.',
                            'key_text' => 'Example: Few (0-1) direct rivals; Market is growing.',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'advanced_18',
                            'stage' => 'Market & Offering',
                            'indicator' => 'Proprietary Edge',
                            'text' => 'Do you have registered trademarks or proprietary technology?',
                            'key_text' => 'Example: Registered Trademark AND Proprietary Process/Tech',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'advanced_27',
                            'stage' => 'Financial Health & Economics',
                            'indicator' => 'Recurring Revenue',
                            'text' => 'Is your revenue model based on recurring fees?',
                            'key_text' => 'Example: Once Off Fee',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'advanced_30',
                            'stage' => 'Financial Health & Economics',
                            'indicator' => 'Core Profitability',
                            'text' => 'What is your net profit margin?',
                            'key_text' => 'Example: >25% Net Profit Margin.',
                            'type' => 'scorable'
                        ],
                        [
                            'id' => 'advanced_32',
                            'stage' => 'Investment & Future Readiness',
                            'indicator' => 'Time to Profitability',
                            'text' => 'How long until you break even?',
                            'key_text' => 'Example: ≤6 Months.',
                            'type' => 'scorable'
                        ]
                    ]
                ]
            ]
        ];
    }
}
