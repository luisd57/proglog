<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

use App\Domain\Stats\Parameter\StandardRow;
use App\Domain\Stats\Result\LevelResult;

/**
 * Classifies an e1RM against a bodyweight-interpolated standards table.
 * Faithful port of levelFor()/interpolateRow() from strength-standards.ts.
 */
final class StrengthLevelCalculator
{
    public const string LEVEL_UNTRAINED = 'untrained';

    public const array LEVELS = [
        'beginner',
        'novice',
        'intermediate',
        'advanced',
        'elite',
    ];

    private function __construct()
    {
    }

    /**
     * @param array<int, StandardRow> $standardRows
     */
    public static function levelFor(array $standardRows, float $bodyweightKg, float $e1rm): LevelResult
    {
        $thresholds = self::interpolateRow($standardRows, $bodyweightKg);

        if ($e1rm >= $thresholds[4]) {
            return new LevelResult(
                level: 'elite',
                nextLevel: null,
                progress: 1.0,
                thresholds: $thresholds,
            );
        }

        // walk levels from elite down to find the highest reached
        $levelIndex = -1; // -1 = untrained
        for ($thresholdIndex = count($thresholds) - 1; $thresholdIndex >= 0; $thresholdIndex--) {
            if ($e1rm >= $thresholds[$thresholdIndex]) {
                $levelIndex = $thresholdIndex;
                break;
            }
        }

        $lower = $levelIndex === -1 ? 0.0 : $thresholds[$levelIndex];
        $upper = $thresholds[$levelIndex + 1];
        $progress = max(0.0, min(1.0, ($e1rm - $lower) / ($upper - $lower)));

        return new LevelResult(
            level: $levelIndex === -1 ? self::LEVEL_UNTRAINED : self::LEVELS[$levelIndex],
            nextLevel: self::LEVELS[$levelIndex + 1],
            progress: $progress,
            thresholds: $thresholds,
        );
    }

    /**
     * Interpolates the thresholds for a bodyweight, clamped to the min/max
     * table row; interpolated values are rounded to 1 decimal.
     *
     * @param array<int, StandardRow> $standardRows
     *
     * @return array<int, float>
     */
    private static function interpolateRow(array $standardRows, float $bodyweightKg): array
    {
        $sorted = array_values($standardRows);
        usort(
            $sorted,
            fn (StandardRow $left, StandardRow $right) => $left->bodyweightKg <=> $right->bodyweightKg,
        );

        if ($bodyweightKg <= $sorted[0]->bodyweightKg) {
            return $sorted[0]->thresholds;
        }

        $last = $sorted[count($sorted) - 1];

        if ($bodyweightKg >= $last->bodyweightKg) {
            return $last->thresholds;
        }

        for ($rowIndex = 0; $rowIndex < count($sorted) - 1; $rowIndex++) {
            $lowerRow = $sorted[$rowIndex];
            $upperRow = $sorted[$rowIndex + 1];

            if ($bodyweightKg >= $lowerRow->bodyweightKg && $bodyweightKg <= $upperRow->bodyweightKg) {
                $fraction = ($bodyweightKg - $lowerRow->bodyweightKg)
                    / ($upperRow->bodyweightKg - $lowerRow->bodyweightKg);

                return array_map(
                    fn (float $lowerThreshold, float $upperThreshold): float => round(
                        ($lowerThreshold + $fraction * ($upperThreshold - $lowerThreshold)) * 10
                    ) / 10,
                    $lowerRow->thresholds,
                    $upperRow->thresholds,
                );
            }
        }

        return $last->thresholds;
    }
}
