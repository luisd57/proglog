<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

use App\Domain\Stats\ValueObject\CumulativeVolumePoint;
use App\Domain\Stats\ValueObject\OverviewTotals;
use App\Domain\Stats\ValueObject\SessionActivity;

/**
 * Aggregates finished-session activity into overview totals and the
 * cumulative volume series. Faithful port of totalsOf()/cumulativeVolume()
 * from stats.service.ts; day bucketing uses the datetimes' own timezone
 * (server-local: the container TZ is set in php.ini and DB values hydrate in
 * the default timezone).
 */
final class OverviewCalculator
{
    private function __construct()
    {
    }

    /**
     * @param array<int, SessionActivity> $sessionActivities
     */
    public static function totals(array $sessionActivities): OverviewTotals
    {
        $volumeKg = 0.0;
        $reps = 0;
        $sets = 0;
        $heaviestKg = 0.0;
        $timeSeconds = 0;

        foreach ($sessionActivities as $sessionActivity) {
            if ($sessionActivity->finishedAt !== null) {
                $timeSeconds += max(
                    0,
                    $sessionActivity->finishedAt->getTimestamp() - $sessionActivity->startedAt->getTimestamp(),
                );
            }

            foreach ($sessionActivity->sets as $loggedSet) {
                $volumeKg += $loggedSet->weightKg * $loggedSet->reps;
                $reps += $loggedSet->reps;
                $sets += 1;

                if ($loggedSet->weightKg > $heaviestKg) {
                    $heaviestKg = $loggedSet->weightKg;
                }
            }
        }

        return new OverviewTotals(
            workouts: count($sessionActivities),
            volumeKg: $volumeKg,
            reps: $reps,
            sets: $sets,
            heaviestKg: $heaviestKg,
            timeSeconds: $timeSeconds,
        );
    }

    /**
     * Volume bucketed by server-local calendar day, one running-sum point per
     * day from the window start through today inclusive. $currentStart null =
     * all-time: starts at the first session's day (today only if none).
     *
     * @param array<int, SessionActivity> $sessionActivities
     *
     * @return array<int, CumulativeVolumePoint>
     */
    public static function cumulativeVolume(
        array $sessionActivities,
        ?\DateTimeImmutable $currentStart,
        \DateTimeImmutable $now,
    ): array {
        $volumePerDay = [];

        foreach ($sessionActivities as $sessionActivity) {
            $volume = 0.0;

            foreach ($sessionActivity->sets as $loggedSet) {
                $volume += $loggedSet->weightKg * $loggedSet->reps;
            }

            $dayKey = $sessionActivity->startedAt->format('Y-m-d');
            $volumePerDay[$dayKey] = ($volumePerDay[$dayKey] ?? 0.0) + $volume;
        }

        $earliest = $currentStart ?? self::earliestStartedAt($sessionActivities) ?? $now;

        $cursor = new \DateTimeImmutable($earliest->format('Y-m-d'));
        $last = new \DateTimeImmutable($now->format('Y-m-d'));

        $points = [];
        $running = 0.0;

        while ($cursor <= $last) {
            $dayKey = $cursor->format('Y-m-d');
            $running += $volumePerDay[$dayKey] ?? 0.0;
            $points[] = new CumulativeVolumePoint($dayKey, $running);
            $cursor = $cursor->modify('+1 day');
        }

        return $points;
    }

    /**
     * @param array<int, SessionActivity> $sessionActivities
     */
    private static function earliestStartedAt(array $sessionActivities): ?\DateTimeImmutable
    {
        $earliest = null;

        foreach ($sessionActivities as $sessionActivity) {
            if ($earliest === null || $sessionActivity->startedAt < $earliest) {
                $earliest = $sessionActivity->startedAt;
            }
        }

        return $earliest;
    }
}
