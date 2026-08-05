<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Parameter\LoggedSet;
use App\Domain\Stats\Parameter\SessionSets;
use App\Domain\Stats\Service\ExerciseSeriesCalculator;
use PHPUnit\Framework\TestCase;

final class ExerciseSeriesCalculatorTest extends TestCase
{
    /**
     * @param array<int, array{0: float, 1: int}> $sets
     */
    private static function sessionSets(string $sessionId, string $startedAt, array $sets): SessionSets
    {
        return new SessionSets(
            sessionId: $sessionId,
            startedAt: new \DateTimeImmutable($startedAt),
            sets: array_map(
                fn (array $set) => new LoggedSet(weightKg: $set[0], reps: $set[1]),
                $sets,
            ),
        );
    }

    public function testCalculateReturnsOnePointPerSessionWithTopSetVolumeAndE1rm(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            // the warmup set is filtered out before the calculator sees it
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[80.0, 8], [80.0, 6]]),
            self::sessionSets('session-b', '2026-08-04 10:00:00', [[82.5, 8]]),
        ]);

        $this->assertCount(2, $result->points);

        [$first, $second] = $result->points;

        $this->assertSame('session-a', $first->sessionId);
        $this->assertSame(80.0 * 8 + 80.0 * 6, $first->volume);
        $this->assertSame(80.0, $first->topSetWeightKg);
        $this->assertSame(8, $first->topSetReps);
        $this->assertEqualsWithDelta(101.33, $first->e1rm, 0.01);

        $this->assertSame('session-b', $second->sessionId);
        $this->assertSame(82.5 * 8, $second->volume);
        $this->assertEqualsWithDelta(104.5, $second->e1rm, 0.01);

        $this->assertLessThan($second->date, $first->date);
    }

    public function testCalculateSkipsSessionsWithoutWorkingSetsAndReturnsPrsChronologically(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[80.0, 8]]),   // baseline = PR
            self::sessionSets('session-b', '2026-08-02 10:00:00', []),            // warmups only
            self::sessionSets('session-c', '2026-08-03 10:00:00', [[70.0, 5]]),   // no PR
            self::sessionSets('session-d', '2026-08-04 10:00:00', [[85.0, 8]]),   // weight + e1rm PR
        ]);

        $this->assertCount(3, $result->points);
        $this->assertSame(
            ['session-a', 'session-c', 'session-d'],
            array_map(fn ($seriesPoint) => $seriesPoint->sessionId, $result->points),
        );

        $this->assertCount(2, $result->prs);
        $this->assertSame(80.0, $result->prs[0]->weightKg);
        $this->assertSame(8, $result->prs[0]->reps);
        $this->assertEquals(new \DateTimeImmutable('2026-08-01 10:00:00'), $result->prs[0]->date);
        $this->assertSame(85.0, $result->prs[1]->weightKg);
        $this->assertSame(8, $result->prs[1]->reps);
        $this->assertEquals(new \DateTimeImmutable('2026-08-04 10:00:00'), $result->prs[1]->date);
    }

    public function testCalculateEmitsAPrWhenOnlyTheSessionBestWeightImproves(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[50.0, 30]]), // e1rm 100, weight 50
            self::sessionSets('session-b', '2026-08-04 10:00:00', [[90.0, 1]]),  // e1rm 93, weight 90
        ]);

        $this->assertCount(2, $result->prs);
        $this->assertSame(90.0, $result->prs[1]->weightKg);
        $this->assertEqualsWithDelta(93.0, $result->prs[1]->e1rm, 0.01);
    }

    public function testCalculateEmitsAPrWhenOnlyTheTopE1rmImproves(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[100.0, 1]]), // e1rm 103.33
            self::sessionSets('session-b', '2026-08-04 10:00:00', [[80.0, 10]]), // e1rm 106.67, lighter
        ]);

        $this->assertCount(2, $result->prs);
        $this->assertSame(80.0, $result->prs[1]->weightKg);
        $this->assertEqualsWithDelta(106.67, $result->prs[1]->e1rm, 0.01);
    }

    public function testCalculateDoesNotEmitAPrWhenNeitherWeightNorE1rmImproves(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[100.0, 5]]),
            self::sessionSets('session-b', '2026-08-04 10:00:00', [[90.0, 5]]),
        ]);

        $this->assertCount(2, $result->points);
        $this->assertCount(1, $result->prs);
    }

    public function testCalculateWithEqualBestsDoesNotEmitAPr(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-01 10:00:00', [[100.0, 5]]),
            self::sessionSets('session-b', '2026-08-04 10:00:00', [[100.0, 5]]),
        ]);

        $this->assertCount(1, $result->prs);
    }

    public function testCalculateKeepsTheFirstSetWhenTwoSetsTieOnE1rm(): void
    {
        // 45 x 30 and 60 x 15 both estimate exactly 90
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-04 10:00:00', [[45.0, 30], [60.0, 15]]),
        ]);

        $this->assertSame(45.0, $result->points[0]->topSetWeightKg);
        $this->assertSame(30, $result->points[0]->topSetReps);
        $this->assertSame(90.0, $result->points[0]->e1rm);
    }

    public function testCalculateUsesTheHighestE1rmSetAsTopSetNotTheHeaviest(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-04 10:00:00', [[80.0, 8], [85.0, 3]]),
        ]);

        // 80x8 -> 101.33 beats 85x3 -> 93.5
        $this->assertSame(80.0, $result->points[0]->topSetWeightKg);
        $this->assertSame(8, $result->points[0]->topSetReps);
        $this->assertSame(80.0 * 8 + 85.0 * 3, $result->points[0]->volume);
    }

    public function testCalculateWithoutHistoryReturnsAnEmptySeries(): void
    {
        $result = ExerciseSeriesCalculator::calculate([]);

        $this->assertSame([], $result->points);
        $this->assertSame([], $result->prs);
    }

    public function testCalculateWithOnlyWarmupSessionsReturnsAnEmptySeries(): void
    {
        $result = ExerciseSeriesCalculator::calculate([
            self::sessionSets('session-a', '2026-08-04 10:00:00', []),
        ]);

        $this->assertSame([], $result->points);
        $this->assertSame([], $result->prs);
    }
}
