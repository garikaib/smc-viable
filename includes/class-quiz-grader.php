<?php
/**
 * Canonical quiz grading engine.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Quiz_Grader {

	/**
	 * Grade a quiz attempt from quiz questions and raw answers.
	 *
	 * @param mixed $questions Raw questions payload.
	 * @param mixed $answers Raw answers payload.
	 * @return array<string, mixed>
	 */
	public static function grade( $questions, $answers ): array {
		$normalized_questions = Quiz_Question_Schema::normalize_questions( $questions );
		$answer_map = is_array( $answers ) ? $answers : [];

		$total = 0.0;
		$max = 0.0;
		$by_stage = [];
		$question_scores = [];

		foreach ( $normalized_questions as $question ) {
			$question_id = (string) ( $question['id'] ?? '' );
			$stage = (string) ( $question['stage'] ?? 'Other' );
			$response = $answer_map[ $question_id ] ?? null;

			$graded = self::grade_question( $question, $response );
			$score = (float) $graded['score'];
			$question_max = (float) $graded['max'];

			$total += $score;
			$max += $question_max;

			if ( ! isset( $by_stage[ $stage ] ) ) {
				$by_stage[ $stage ] = [
					'total' => 0.0,
					'max'   => 0.0,
					'flags' => 0,
					'items' => [],
				];
			}

			$by_stage[ $stage ]['total'] += $score;
			$by_stage[ $stage ]['max'] += $question_max;
			if ( $score < 0 ) {
				$by_stage[ $stage ]['flags'] += 1;
			}
			$by_stage[ $stage ]['items'][] = [
				'id'    => $question_id,
				'label' => (string) ( $question['indicator'] ?? $question['text'] ?? $question_id ),
				'score' => $score,
				'max'   => $question_max,
			];

			$question_scores[ $question_id ] = [
				'type'      => (string) ( $question['type'] ?? 'short_text' ),
				'score'     => $score,
				'max'       => $question_max,
				'stage'     => $stage,
				'indicator' => (string) ( $question['indicator'] ?? '' ),
			];
		}

		return [
			'answers'         => $answer_map,
			'total_score'     => (int) round( $total ),
			'max_score'       => (int) round( max( 0.0, $max ) ),
			'scores_by_stage' => $by_stage,
			'question_scores' => $question_scores,
		];
	}

	/**
	 * Grade an individual question response.
	 *
	 * @param array<string, mixed> $question Normalized question.
	 * @param mixed                $response Raw response.
	 * @return array{score: float, max: float}
	 */
	private static function grade_question( array $question, $response ): array {
		$type = (string) ( $question['type'] ?? 'short_text' );
		$grading = is_array( $question['grading'] ?? null ) ? $question['grading'] : [];
		$mode = (string) ( $grading['mode'] ?? 'auto' );

		if ( 'none' === $mode ) {
			return [ 'score' => 0.0, 'max' => 0.0 ];
		}

		if ( 'single_choice' === $type ) {
			return self::grade_single_choice( $question, $response );
		}

		if ( 'multi_select' === $type ) {
			return self::grade_multi_select( $question, $response );
		}

		if ( 'numeric' === $type ) {
			return self::grade_numeric( $question, $response );
		}

		if ( 'ranking' === $type ) {
			return self::grade_ranking( $question, $response );
		}

		if ( 'matching' === $type ) {
			return self::grade_matching( $question, $response );
		}

		if ( 'matrix_true_false' === $type ) {
			return self::grade_matrix( $question, $response );
		}

		return [ 'score' => 0.0, 'max' => 0.0 ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_single_choice( array $question, $response ): array {
		$choices = is_array( $question['choices'] ?? null ) ? $question['choices'] : [];
		$max = self::max_positive_choice_points( $choices );

		if ( ! is_string( $response ) && ! is_numeric( $response ) ) {
			return [ 'score' => 0.0, 'max' => $max ];
		}
		$needle = (string) $response;

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$id = (string) ( $choice['id'] ?? '' );
			$label = (string) ( $choice['label'] ?? '' );
			if ( $needle === $id || $needle === $label ) {
				return [ 'score' => (float) ( $choice['points'] ?? 0.0 ), 'max' => $max ];
			}
		}

		return [ 'score' => 0.0, 'max' => $max ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_multi_select( array $question, $response ): array {
		$choices = is_array( $question['choices'] ?? null ) ? $question['choices'] : [];
		$max = self::sum_positive_choice_points( $choices );

		if ( ! is_array( $response ) ) {
			return [ 'score' => 0.0, 'max' => $max ];
		}

		$selected = array_map( 'strval', $response );
		$score = 0.0;

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$id = (string) ( $choice['id'] ?? '' );
			$label = (string) ( $choice['label'] ?? '' );
			$points = (float) ( $choice['points'] ?? 0.0 );
			$is_selected = in_array( $id, $selected, true ) || in_array( $label, $selected, true );
			if ( $is_selected ) {
				$score += $points;
			}
		}

		$min = isset( $question['grading']['min_points'] ) ? (float) $question['grading']['min_points'] : 0.0;
		if ( $score < $min ) {
			$score = $min;
		}

		return [ 'score' => $score, 'max' => $max ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_numeric( array $question, $response ): array {
		$max = isset( $question['grading']['max_points'] ) ? (float) $question['grading']['max_points'] : 1.0;
		$config = is_array( $question['numeric'] ?? null ) ? $question['numeric'] : [];
		$expected = isset( $config['correct_value'] ) ? (float) $config['correct_value'] : 0.0;
		$tolerance = isset( $config['tolerance'] ) ? abs( (float) $config['tolerance'] ) : 0.0;

		if ( ! is_numeric( $response ) ) {
			return [ 'score' => 0.0, 'max' => $max ];
		}

		$value = (float) $response;
		if ( abs( $value - $expected ) <= $tolerance ) {
			return [ 'score' => $max, 'max' => $max ];
		}

		return [ 'score' => 0.0, 'max' => $max ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_ranking( array $question, $response ): array {
		$config = is_array( $question['ranking'] ?? null ) ? $question['ranking'] : [];
		$items = is_array( $config['items'] ?? null ) ? $config['items'] : [];
		$correct_order = is_array( $config['correct_order'] ?? null ) ? array_values( array_map( 'strval', $config['correct_order'] ) ) : [];
		$mode = (string) ( $config['mode'] ?? 'position' );
		$max = isset( $question['grading']['max_points'] ) ? (float) $question['grading']['max_points'] : 1.0;

		$response_order = self::normalize_order_response( $response, $items );
		if ( empty( $response_order ) || empty( $correct_order ) ) {
			return [ 'score' => 0.0, 'max' => $max ];
		}

		if ( 'exact' === $mode ) {
			return [ 'score' => $response_order === $correct_order ? $max : 0.0, 'max' => $max ];
		}

		$correct_positions = 0;
		$count = min( count( $correct_order ), count( $response_order ) );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $response_order[ $i ] === $correct_order[ $i ] ) {
				$correct_positions++;
			}
		}

		$score = $count > 0 ? ( $max * ( $correct_positions / $count ) ) : 0.0;
		return [ 'score' => $score, 'max' => $max ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_matching( array $question, $response ): array {
		$config = is_array( $question['matching'] ?? null ) ? $question['matching'] : [];
		$pairs = is_array( $config['pairs'] ?? null ) ? $config['pairs'] : [];
		$max = isset( $question['grading']['max_points'] ) ? (float) $question['grading']['max_points'] : (float) count( $pairs );

		if ( empty( $pairs ) || ! is_array( $response ) ) {
			return [ 'score' => 0.0, 'max' => $max ];
		}

		$correct = 0;
		foreach ( $pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}
			$left_id = (string) ( $pair['id'] ?? '' );
			$expected_right = (string) ( $pair['right'] ?? '' );
			$selected_right = isset( $response[ $left_id ] ) ? (string) $response[ $left_id ] : '';
			if ( '' !== $selected_right && $selected_right === $expected_right ) {
				$correct++;
			}
		}

		$score = count( $pairs ) > 0 ? ( $max * ( $correct / count( $pairs ) ) ) : 0.0;
		return [ 'score' => $score, 'max' => $max ];
	}

	/**
	 * @param array<string, mixed> $question
	 * @param mixed                $response
	 * @return array{score: float, max: float}
	 */
	private static function grade_matrix( array $question, $response ): array {
		$config = is_array( $question['matrix'] ?? null ) ? $question['matrix'] : [];
		$statements = is_array( $config['statements'] ?? null ) ? $config['statements'] : [];
		if ( empty( $statements ) || ! is_array( $response ) ) {
			return [ 'score' => 0.0, 'max' => 0.0 ];
		}

		$max = 0.0;
		$score = 0.0;

		foreach ( $statements as $statement ) {
			if ( ! is_array( $statement ) ) {
				continue;
			}
			$id = (string) ( $statement['id'] ?? '' );
			$correct = (bool) ( $statement['correct'] ?? false );
			$points_correct = isset( $statement['points_correct'] ) ? (float) $statement['points_correct'] : 1.0;
			$points_incorrect = isset( $statement['points_incorrect'] ) ? (float) $statement['points_incorrect'] : 0.0;

			$max += max( 0.0, $points_correct );
			$answer = self::normalize_bool( $response[ $id ] ?? null );
			if ( null === $answer ) {
				continue;
			}
			$score += ( $answer === $correct ) ? $points_correct : $points_incorrect;
		}

		return [ 'score' => $score, 'max' => $max ];
	}

	/**
	 * @param mixed $value
	 * @return bool|null
	 */
	private static function normalize_bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, [ 'true', '1', 'yes' ], true ) ) {
				return true;
			}
			if ( in_array( $lower, [ 'false', '0', 'no' ], true ) ) {
				return false;
			}
		}
		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}
		return null;
	}

	/**
	 * @param mixed                           $response
	 * @param array<int, array<string,mixed>> $items
	 * @return array<int, string>
	 */
	private static function normalize_order_response( $response, array $items ): array {
		if ( is_array( $response ) && array_values( $response ) === $response ) {
			return array_values( array_map( 'strval', $response ) );
		}

		if ( ! is_array( $response ) ) {
			return [];
		}

		$map = [];
		foreach ( $response as $item_id => $position ) {
			if ( ! is_numeric( $position ) ) {
				continue;
			}
			$map[] = [
				'item_id'  => (string) $item_id,
				'position' => (int) $position,
			];
		}

		usort(
			$map,
			static function ( array $a, array $b ): int {
				return $a['position'] <=> $b['position'];
			}
		);

		return array_values(
			array_map(
				static function ( array $row ): string {
					return $row['item_id'];
				},
				$map
			)
		);
	}

	/**
	 * @param array<int, array<string,mixed>> $choices
	 * @return float
	 */
	private static function max_positive_choice_points( array $choices ): float {
		$max = 0.0;
		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$points = isset( $choice['points'] ) ? (float) $choice['points'] : 0.0;
			if ( $points > $max ) {
				$max = $points;
			}
		}
		return $max;
	}

	/**
	 * @param array<int, array<string,mixed>> $choices
	 * @return float
	 */
	private static function sum_positive_choice_points( array $choices ): float {
		$sum = 0.0;
		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}
			$points = isset( $choice['points'] ) ? (float) $choice['points'] : 0.0;
			if ( $points > 0 ) {
				$sum += $points;
			}
		}
		return $sum;
	}
}

