<?php
/**
 * Quiz question schema normalization and migration helpers.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Quiz_Question_Schema {

	public const VERSION = 2;

	/**
	 * Normalize an array of mixed/legacy questions into schema v2.
	 *
	 * @param mixed $questions Raw quiz questions meta payload.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_questions( $questions ): array {
		if ( ! is_array( $questions ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $questions as $index => $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}
			$normalized[] = self::normalize_question( $question, (int) $index );
		}

		return $normalized;
	}

	/**
	 * Normalize a single question.
	 *
	 * @param array<string, mixed> $question Raw question.
	 * @param int                  $index    Fallback index for id generation.
	 * @return array<string, mixed>
	 */
	public static function normalize_question( array $question, int $index = 0 ): array {
		$type = isset( $question['type'] ) ? sanitize_key( (string) $question['type'] ) : 'single_choice';
		$id   = self::normalize_question_id( $question['id'] ?? null, $index );

		$base = [
			'version'     => self::VERSION,
			'id'          => $id,
			'type'        => $type,
			'stage'       => (string) ( $question['stage'] ?? 'Other' ),
			'indicator'   => (string) ( $question['indicator'] ?? '' ),
			'text'        => (string) ( $question['text'] ?? '' ),
			'key_text'    => (string) ( $question['key_text'] ?? '' ),
			'guidance'    => (string) ( $question['guidance'] ?? '' ),
			'required'    => ! isset( $question['required'] ) || (bool) $question['required'],
			'grading'     => is_array( $question['grading'] ?? null ) ? $question['grading'] : [],
			'bias_checks' => is_array( $question['bias_checks'] ?? null ) ? $question['bias_checks'] : [],
		];

		// Legacy migrations.
		if ( 'scorable' === $type ) {
			$base['type'] = 'single_choice';
			$base['choices'] = self::normalize_choices(
				$question['options'] ?? [
					[ 'label' => 'Great', 'score' => 15 ],
					[ 'label' => 'Good', 'score' => 10 ],
					[ 'label' => 'Borderline', 'score' => 5 ],
					[ 'label' => 'Flag', 'score' => -5 ],
				],
				'id'
			);
			$base['grading'] = self::normalize_grading(
				$base['grading'],
				'single_choice',
				$base['choices']
			);
			return $base;
		}

		if ( 'select' === $type ) {
			$base['type'] = 'single_choice';
			$base['choices'] = self::normalize_choices( $question['options'] ?? [], 'id' );
			$base['grading'] = self::normalize_grading(
				$base['grading'],
				'single_choice',
				$base['choices']
			);
			return $base;
		}

		if ( 'text' === $type ) {
			$base['type'] = 'short_text';
			$base['grading'] = self::normalize_grading( $base['grading'], 'short_text', [] );
			return $base;
		}

		if ( 'date' === $type ) {
			$base['type'] = 'date_month';
			$base['grading'] = self::normalize_grading( $base['grading'], 'date_month', [] );
			return $base;
		}

		// Native schema v2 types.
		if ( 'single_choice' === $base['type'] ) {
			$base['choices'] = self::normalize_choices( $question['choices'] ?? ( $question['options'] ?? [] ), 'id' );
			$base['grading'] = self::normalize_grading( $base['grading'], 'single_choice', $base['choices'] );
			return $base;
		}

		if ( 'multi_select' === $base['type'] ) {
			$base['choices'] = self::normalize_choices( $question['choices'] ?? [], 'id' );
			$base['grading'] = self::normalize_grading( $base['grading'], 'multi_select', $base['choices'] );
			return $base;
		}

		if ( 'numeric' === $base['type'] ) {
			$base['numeric'] = [
				'correct_value' => isset( $question['numeric']['correct_value'] ) ? (float) $question['numeric']['correct_value'] : 0.0,
				'tolerance'     => isset( $question['numeric']['tolerance'] ) ? max( 0.0, (float) $question['numeric']['tolerance'] ) : 0.0,
				'min'           => isset( $question['numeric']['min'] ) ? (float) $question['numeric']['min'] : null,
				'max'           => isset( $question['numeric']['max'] ) ? (float) $question['numeric']['max'] : null,
				'unit'          => (string) ( $question['numeric']['unit'] ?? '' ),
			];
			$base['grading'] = self::normalize_grading( $base['grading'], 'numeric', [] );
			return $base;
		}

		if ( 'ranking' === $base['type'] ) {
			$base['ranking'] = self::normalize_ranking( $question['ranking'] ?? [] );
			$base['grading'] = self::normalize_grading( $base['grading'], 'ranking', [] );
			return $base;
		}

		if ( 'matching' === $base['type'] ) {
			$base['matching'] = self::normalize_matching( $question['matching'] ?? [] );
			$base['grading'] = self::normalize_grading( $base['grading'], 'matching', [] );
			return $base;
		}

		if ( 'matrix_true_false' === $base['type'] ) {
			$base['matrix'] = self::normalize_matrix( $question['matrix'] ?? [] );
			$base['grading'] = self::normalize_grading( $base['grading'], 'matrix_true_false', [] );
			return $base;
		}

		if ( 'short_text' === $base['type'] ) {
			$base['grading'] = self::normalize_grading( $base['grading'], 'short_text', [] );
			return $base;
		}

		if ( 'date_month' === $base['type'] ) {
			$base['grading'] = self::normalize_grading( $base['grading'], 'date_month', [] );
			return $base;
		}

		$base['type'] = 'short_text';
		$base['grading'] = self::normalize_grading( $base['grading'], 'short_text', [] );
		return $base;
	}

	/**
	 * Create a deterministic id for question/options when missing.
	 *
	 * @param mixed $id Raw id value.
	 * @param int   $index Fallback index.
	 * @return string
	 */
	private static function normalize_question_id( $id, int $index ): string {
		if ( is_numeric( $id ) ) {
			return 'q_' . (string) (int) $id;
		}

		if ( is_string( $id ) && '' !== trim( $id ) ) {
			$clean = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $id );
			return 'q_' . trim( (string) $clean, '_' );
		}

		return 'q_' . (string) ( $index + 1 ) . '_' . wp_generate_password( 6, false, false );
	}

	/**
	 * Normalize choices array from legacy/new payloads.
	 *
	 * @param mixed  $choices Raw options/choices payload.
	 * @param string $id_prefix Prefix for generated ids.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_choices( $choices, string $id_prefix ): array {
		if ( ! is_array( $choices ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $choices as $index => $choice ) {
			if ( is_string( $choice ) ) {
				$normalized[] = [
					'id'     => $id_prefix . '_' . ( $index + 1 ),
					'label'  => $choice,
					'points' => 0,
				];
				continue;
			}

			if ( ! is_array( $choice ) ) {
				continue;
			}

			$label = (string) ( $choice['label'] ?? $choice['text'] ?? '' );
			$points = 0;
			if ( isset( $choice['points'] ) && is_numeric( $choice['points'] ) ) {
				$points = (float) $choice['points'];
			} elseif ( isset( $choice['score'] ) && is_numeric( $choice['score'] ) ) {
				$points = (float) $choice['score'];
			}

			$choice_id = (string) ( $choice['id'] ?? $id_prefix . '_' . ( $index + 1 ) );
			if ( '' === trim( $choice_id ) ) {
				$choice_id = $id_prefix . '_' . ( $index + 1 );
			}

			$normalized[] = [
				'id'     => sanitize_key( $choice_id ),
				'label'  => $label,
				'points' => $points,
			];
		}

		return $normalized;
	}

	/**
	 * Normalize ranking config.
	 *
	 * @param mixed $ranking Raw ranking payload.
	 * @return array<string, mixed>
	 */
	private static function normalize_ranking( $ranking ): array {
		$items = [];
		$correct_order = [];
		$mode = 'position';

		if ( is_array( $ranking ) ) {
			$mode = isset( $ranking['mode'] ) ? sanitize_key( (string) $ranking['mode'] ) : 'position';
			$raw_items = is_array( $ranking['items'] ?? null ) ? $ranking['items'] : [];
			foreach ( $raw_items as $index => $item ) {
				$label = is_array( $item ) ? (string) ( $item['label'] ?? '' ) : (string) $item;
				$item_id = is_array( $item ) && isset( $item['id'] ) ? (string) $item['id'] : 'rank_' . ( $index + 1 );
				$items[] = [
					'id'    => sanitize_key( $item_id ),
					'label' => $label,
				];
			}
			$correct_order = is_array( $ranking['correct_order'] ?? null ) ? array_values( array_map( 'strval', $ranking['correct_order'] ) ) : [];
		}

		if ( empty( $correct_order ) ) {
			$correct_order = array_values(
				array_map(
					static function ( array $item ): string {
						return (string) $item['id'];
					},
					$items
				)
			);
		}

		return [
			'mode'          => in_array( $mode, [ 'exact', 'position' ], true ) ? $mode : 'position',
			'items'         => $items,
			'correct_order' => $correct_order,
		];
	}

	/**
	 * Normalize matching config.
	 *
	 * @param mixed $matching Raw matching payload.
	 * @return array<string, mixed>
	 */
	private static function normalize_matching( $matching ): array {
		$pairs = [];
		if ( is_array( $matching ) && is_array( $matching['pairs'] ?? null ) ) {
			foreach ( $matching['pairs'] as $index => $pair ) {
				if ( ! is_array( $pair ) ) {
					continue;
				}
				$pairs[] = [
					'id'    => sanitize_key( (string) ( $pair['id'] ?? ( 'pair_' . ( $index + 1 ) ) ) ),
					'left'  => (string) ( $pair['left'] ?? '' ),
					'right' => (string) ( $pair['right'] ?? '' ),
				];
			}
		}

		return [ 'pairs' => $pairs ];
	}

	/**
	 * Normalize matrix true/false config.
	 *
	 * @param mixed $matrix Raw matrix payload.
	 * @return array<string, mixed>
	 */
	private static function normalize_matrix( $matrix ): array {
		$statements = [];
		if ( is_array( $matrix ) && is_array( $matrix['statements'] ?? null ) ) {
			foreach ( $matrix['statements'] as $index => $statement ) {
				if ( ! is_array( $statement ) ) {
					continue;
				}
				$statements[] = [
					'id'              => sanitize_key( (string) ( $statement['id'] ?? ( 'stmt_' . ( $index + 1 ) ) ) ),
					'text'            => (string) ( $statement['text'] ?? '' ),
					'correct'         => (bool) ( $statement['correct'] ?? false ),
					'points_correct'  => isset( $statement['points_correct'] ) ? (float) $statement['points_correct'] : 1.0,
					'points_incorrect'=> isset( $statement['points_incorrect'] ) ? (float) $statement['points_incorrect'] : 0.0,
				];
			}
		}

		return [ 'statements' => $statements ];
	}

	/**
	 * Normalize grading object with sane defaults per question type.
	 *
	 * @param array<string, mixed>           $grading Existing grading config.
	 * @param string                         $type    Question type.
	 * @param array<int, array<string,mixed>> $choices Optional choices.
	 * @return array<string, mixed>
	 */
	private static function normalize_grading( array $grading, string $type, array $choices ): array {
		$max_points = isset( $grading['max_points'] ) && is_numeric( $grading['max_points'] ) ? (float) $grading['max_points'] : 0.0;
		$min_points = isset( $grading['min_points'] ) && is_numeric( $grading['min_points'] ) ? (float) $grading['min_points'] : 0.0;
		$cap_points = isset( $grading['cap_points'] ) && is_numeric( $grading['cap_points'] ) ? (float) $grading['cap_points'] : null;
		$mode       = isset( $grading['mode'] ) ? sanitize_key( (string) $grading['mode'] ) : 'auto';

		if ( 'single_choice' === $type ) {
			$choice_points = array_map(
				static function ( array $choice ): float {
					return isset( $choice['points'] ) ? (float) $choice['points'] : 0.0;
				},
				$choices
			);
			$max_points = max( $max_points, empty( $choice_points ) ? 0.0 : max( array_filter( $choice_points, static fn( float $n ): bool => $n > 0 ) ?: [ 0.0 ] ) );
			$min_points = min( $min_points, empty( $choice_points ) ? 0.0 : min( array_filter( $choice_points, static fn( float $n ): bool => $n < 0 ) ?: [ 0.0 ] ) );
		}
		if ( 'multi_select' === $type ) {
			$choice_points = array_map(
				static function ( array $choice ): float {
					return isset( $choice['points'] ) ? (float) $choice['points'] : 0.0;
				},
				$choices
			);
			$max_points = max( $max_points, empty( $choice_points ) ? 0.0 : array_sum( array_filter( $choice_points, static fn( float $n ): bool => $n > 0 ) ) );
			$min_points = min( $min_points, empty( $choice_points ) ? 0.0 : min( array_filter( $choice_points, static fn( float $n ): bool => $n < 0 ) ?: [ 0.0 ] ) );
			if ( null !== $cap_points ) {
				$cap_points = max( 0.0, $cap_points );
			}
		} else {
			$cap_points = null;
		}

		if ( in_array( $type, [ 'numeric', 'ranking', 'matching', 'matrix_true_false' ], true ) ) {
			if ( 0.0 === $max_points ) {
				$max_points = 1.0;
			}
		}

		if ( in_array( $type, [ 'short_text', 'date_month' ], true ) ) {
			$mode = 'none';
			$max_points = 0.0;
			$min_points = 0.0;
		}

		if ( ! in_array( $mode, [ 'auto', 'manual', 'none' ], true ) ) {
			$mode = 'auto';
		}

		return [
			'mode'       => $mode,
			'max_points' => $max_points,
			'min_points' => $min_points,
			'cap_points' => $cap_points,
		];
	}
}
