<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Service\OverviewCalculator;
use App\Domain\Stats\ValueObject\CumulativeVolumePoint;
use App\Domain\Stats\ValueObject\LoggedSet;
use App\Domain\Stats\ValueObject\SessionActivity;
use PHPUnit\Framework\TestCase;

final class OverviewCalculatorTest extends TestCase
{
    /**
     * @param array<int, array{0: float, 1: int}> $sets
     */
    private static function sessionActivity(string $startedAt, ?string $finishedAt, array $sets): SessionActivity
    {
        return new SessionActivity(
            startedAt: new \DateTimeImmutable($startedAt),
            finishedAt: $finishedAt !== null ? new \DateTimeImmutable($finishedAt) : null,
            sets: array_map(
                fn (array $set) => new LoggedSet(weightKg: $set[0], reps: $set[1]),
                $sets,
            ),
        );
    }

    /**
     * @param array<int, CumulativeVolumePoint> $points
     *
     * @return array<string, float>
     */
    private static function pointsToMap(array $points): array
    {
        $map = [];

        foreach ($points as $cumulativeVolumePoint) {
            $map[$cumulativeVolumePoint->date] = $cumulativeVolumePoint->value;
        }

        return $map;
    }

    public function testTotalsSumsVolumeRepsSetsAndHeaviestOverTheGivenSets(): void
    {
        // the warmup set is filtered out before the calculator sees it
        $totals = OverviewCalculator::totals([
            self::sessionActivity('2026-08-04 10:00:00', '2026-08-04 11:00:00', [[80.0, 8], [100.0, 5]]),
        ]);

        $this->assertSame(1, $totals->workouts);
        $this->assertSame(80.0 * 8 + 100.0 * 5, $totals->volumeKg);
        $this->assertSame(13, $totals->reps);
        $this->assertSame(2, $totals->sets);
        $this->assertSame(100.0, $totals->heaviestKg);
    }

    public function testTotalsWithoutSessionsReturnsZeros(): void
    {
        $totals = OverviewCalculator::totals([]);

        $this->assertSame(0, $totals->workouts);
        $this->assertSame(0.0, $totals->volumeKg);
        $this->assertSame(0, $totals->reps);
        $this->assertSame(0, $totals->sets);
        $this->assertSame(0.0, $totals->heaviestKg);
        $this->assertSame(0, $totals->timeSeconds);
    }

    public function testTotalsCountsSessionsWithoutSetsAsWorkouts(): void
    {
        $totals = OverviewCalculator::totals([
            self::sessionActivity('2026-08-04 10:00:00', '2026-08-04 11:00:00', []),
        ]);

        $this->assertSame(1, $totals->workouts);
        $this->assertSame(0.0, $totals->volumeKg);
        $this->assertSame(0.0, $totals->heaviestKg);
    }

    public function testTotalsSumsSessionDurationsIntoTimeSeconds(): void
    {
        $totals = OverviewCalculator::totals([
            self::sessionActivity('2026-08-04 10:00:00', '2026-08-04 10:30:00', [[100.0, 5]]),
            self::sessionActivity('2026-08-03 10:00:00', '2026-08-03 11:00:00', [[100.0, 5]]),
        ]);

        $this->assertSame(30 * 60 + 60 * 60, $totals->timeSeconds);
    }

    public function testTotalsIgnoresTheDurationOfAnUnfinishedSession(): void
    {
        $totals = OverviewCalculator::totals([
            self::sessionActivity('2026-08-04 10:00:00', null, [[100.0, 5]]),
        ]);

        $this->assertSame(0, $totals->timeSeconds);
        $this->assertSame(1, $totals->workouts);
    }

    public function testTotalsClampsANegativeDurationToZero(): void
    {
        $totals = OverviewCalculator::totals([
            self::sessionActivity('2026-08-04 11:00:00', '2026-08-04 10:00:00', []),
        ]);

        $this->assertSame(0, $totals->timeSeconds);
    }

    public function testCumulativeVolumeBucketsVolumeByCalendarDayAndRunsTheSumForward(): void
    {
        $points = OverviewCalculator::cumulativeVolume(
            [
                self::sessionActivity('2026-08-02 09:00:00', '2026-08-02 10:00:00', [[100.0, 5]]),
                self::sessionActivity('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[60.0, 5]]),
            ],
            new \DateTimeImmutable('2026-08-01 12:00:00'),
            new \DateTimeImmutable('2026-08-04 12:00:00'),
        );

        $this->assertSame(
            [
                '2026-08-01' => 0.0,
                '2026-08-02' => 500.0,
                '2026-08-03' => 500.0,
                '2026-08-04' => 800.0,
            ],
            self::pointsToMap($points),
        );
    }

    public function testCumulativeVolumeSumsSeveralSessionsOfTheSameDay(): void
    {
        $points = OverviewCalculator::cumulativeVolume(
            [
                self::sessionActivity('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[100.0, 5]]),
                self::sessionActivity('2026-08-04 18:00:00', '2026-08-04 19:00:00', [[100.0, 5]]),
            ],
            new \DateTimeImmutable('2026-08-04 06:00:00'),
            new \DateTimeImmutable('2026-08-04 20:00:00'),
        );

        $this->assertSame(['2026-08-04' => 1000.0], self::pointsToMap($points));
    }

    public function testCumulativeVolumeIsNonDecreasingAndEndsAtThePeriodVolume(): void
    {
        $sessionActivities = [
            self::sessionActivity('2026-08-02 09:00:00', '2026-08-02 10:00:00', [[100.0, 5]]),
            self::sessionActivity('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[60.0, 5]]),
        ];

        $points = OverviewCalculator::cumulativeVolume(
            $sessionActivities,
            new \DateTimeImmutable('2026-07-28 12:00:00'),
            new \DateTimeImmutable('2026-08-04 12:00:00'),
        );

        $values = array_map(fn (CumulativeVolumePoint $point) => $point->value, $points);

        for ($index = 1; $index < count($values); $index++) {
            $this->assertGreaterThanOrEqual($values[$index - 1], $values[$index]);
        }

        $this->assertSame(
            OverviewCalculator::totals($sessionActivities)->volumeKg,
            $values[count($values) - 1],
        );
    }

    public function testCumulativeVolumeForAllTimeStartsAtTheFirstSessionDay(): void
    {
        $points = OverviewCalculator::cumulativeVolume(
            [
                self::sessionActivity('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[60.0, 5]]),
                self::sessionActivity('2026-08-02 09:00:00', '2026-08-02 10:00:00', [[100.0, 5]]),
            ],
            null,
            new \DateTimeImmutable('2026-08-04 12:00:00'),
        );

        $this->assertSame(
            [
                '2026-08-02' => 500.0,
                '2026-08-03' => 500.0,
                '2026-08-04' => 800.0,
            ],
            self::pointsToMap($points),
        );
    }

    public function testCumulativeVolumeForAllTimeWithoutSessionsReturnsTodayOnly(): void
    {
        $points = OverviewCalculator::cumulativeVolume(
            [],
            null,
            new \DateTimeImmutable('2026-08-04 12:00:00'),
        );

        $this->assertSame(['2026-08-04' => 0.0], self::pointsToMap($points));
    }

    public function testCumulativeVolumeIgnoresSessionsOutsideTheRenderedWindow(): void
    {
        // a session started before the window start is bucketed on a day that
        // is never visited, so it never enters the running sum
        $points = OverviewCalculator::cumulativeVolume(
            [self::sessionActivity('2026-07-01 09:00:00', '2026-07-01 10:00:00', [[100.0, 5]])],
            new \DateTimeImmutable('2026-08-03 12:00:00'),
            new \DateTimeImmutable('2026-08-04 12:00:00'),
        );

        $this->assertSame(
            [
                '2026-08-03' => 0.0,
                '2026-08-04' => 0.0,
            ],
            self::pointsToMap($points),
        );
    }
}
