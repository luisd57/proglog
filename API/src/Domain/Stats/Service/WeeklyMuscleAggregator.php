<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

use App\Domain\Stats\ValueObject\SessionMuscles;
use App\Domain\Stats\ValueObject\WeeklyMuscles;

/**
 * Unions the muscles trained across qualifying session exercises; muscles in
 * primary are removed from secondary; session_count = distinct sessions.
 * Insertion order is preserved, as in the old service's Set-based port.
 */
final class WeeklyMuscleAggregator
{
    private function __construct()
    {
    }

    /**
     * @param array<int, SessionMuscles> $sessionMuscleEntries
     */
    public static function aggregate(array $sessionMuscleEntries): WeeklyMuscles
    {
        $primary = [];
        $secondary = [];
        $sessionIds = [];

        foreach ($sessionMuscleEntries as $sessionMuscles) {
            $sessionIds[$sessionMuscles->sessionId] = true;

            foreach ($sessionMuscles->primaryMuscles as $muscle) {
                $primary[$muscle] = true;
            }

            foreach ($sessionMuscles->secondaryMuscles as $muscle) {
                $secondary[$muscle] = true;
            }
        }

        foreach (array_keys($primary) as $muscle) {
            unset($secondary[$muscle]);
        }

        return new WeeklyMuscles(
            primary: array_keys($primary),
            secondary: array_keys($secondary),
            sessionCount: count($sessionIds),
        );
    }
}
