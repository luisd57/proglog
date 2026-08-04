<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

use App\Domain\Stats\ValueObject\ExerciseSeriesResult;
use App\Domain\Stats\ValueObject\PrEvent;
use App\Domain\Stats\ValueObject\SeriesPoint;
use App\Domain\Stats\ValueObject\SessionSets;

/**
 * Builds the progress series of one exercise: one point per finished session
 * with working sets (top set = highest e1RM, first set wins ties), plus PR
 * events whenever a session's best set weight or top e1RM beats everything
 * before it (the first qualifying session always emits one). Faithful port of
 * the exerciseSeries() loop in stats.service.ts.
 */
final class ExerciseSeriesCalculator
{
    private function __construct()
    {
    }

    /**
     * @param array<int, SessionSets> $sessionSetsHistory ordered by session
     *                                                    started_at ASC
     */
    public static function calculate(array $sessionSetsHistory): ExerciseSeriesResult
    {
        $points = [];
        $prs = [];
        $bestWeight = -INF;
        $bestE1rm = -INF;

        foreach ($sessionSetsHistory as $sessionSets) {
            if ($sessionSets->sets === []) {
                continue;
            }

            $top = $sessionSets->sets[0];
            $topE1rm = E1rmCalculator::epley1Rm($top->weightKg, $top->reps);
            $volume = 0.0;
            $sessionBestWeight = -INF;

            foreach ($sessionSets->sets as $loggedSet) {
                $volume += $loggedSet->weightKg * $loggedSet->reps;
                $e1rm = E1rmCalculator::epley1Rm($loggedSet->weightKg, $loggedSet->reps);

                if ($e1rm > $topE1rm) {
                    $top = $loggedSet;
                    $topE1rm = $e1rm;
                }

                $sessionBestWeight = max($sessionBestWeight, $loggedSet->weightKg);
            }

            $points[] = new SeriesPoint(
                sessionId: $sessionSets->sessionId,
                date: $sessionSets->startedAt,
                topSetWeightKg: $top->weightKg,
                topSetReps: $top->reps,
                volume: $volume,
                e1rm: $topE1rm,
            );

            // PR event: best set of the session beats everything before it
            if ($sessionBestWeight > $bestWeight || $topE1rm > $bestE1rm) {
                $prs[] = new PrEvent(
                    date: $sessionSets->startedAt,
                    weightKg: $top->weightKg,
                    reps: $top->reps,
                    e1rm: $topE1rm,
                );
            }

            $bestWeight = max($bestWeight, $sessionBestWeight);
            $bestE1rm = max($bestE1rm, $topE1rm);
        }

        return new ExerciseSeriesResult($points, $prs);
    }
}
