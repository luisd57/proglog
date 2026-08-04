<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Service\StrengthLevelCalculator;
use App\Domain\Stats\Service\StrengthStandards;
use App\Domain\Stats\ValueObject\LiftStandard;
use App\Domain\Stats\ValueObject\StandardRow;
use PHPUnit\Framework\TestCase;

final class StrengthLevelCalculatorTest extends TestCase
{
    /**
     * Simplified table for the ported cases: bodyweight rows 60 and 100.
     *
     * @return array<int, StandardRow>
     */
    private static function simplifiedTable(): array
    {
        return [
            new StandardRow(60.0, [40.0, 60.0, 80.0, 100.0, 120.0]),
            new StandardRow(100.0, [80.0, 100.0, 120.0, 140.0, 160.0]),
        ];
    }

    private static function standardFor(string $lift): LiftStandard
    {
        foreach (StrengthStandards::all() as $liftStandard) {
            if ($liftStandard->lift === $lift) {
                return $liftStandard;
            }
        }

        throw new \RuntimeException("No strength standard for lift {$lift}.");
    }

    public function testBelowBeginnerIsUntrainedWithProgressTowardBeginner(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 20.0);

        $this->assertSame('untrained', $result->level);
        $this->assertSame('beginner', $result->nextLevel);
        $this->assertEqualsWithDelta(0.5, $result->progress, 0.00001);
    }

    public function testExactThresholdIsClassifiedAndProgressInterpolatesToTheNextLevel(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 70.0);

        $this->assertSame('novice', $result->level);
        $this->assertSame('intermediate', $result->nextLevel);
        // 70 sits halfway between the novice (60) and intermediate (80) thresholds
        $this->assertEqualsWithDelta(0.5, $result->progress, 0.00001);
    }

    public function testExactlyOnAThresholdReportsThatLevelWithZeroProgress(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 40.0);

        $this->assertSame('beginner', $result->level);
        $this->assertSame('novice', $result->nextLevel);
        $this->assertEqualsWithDelta(0.0, $result->progress, 0.00001);
    }

    public function testAboveEliteCapsAtElite(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 150.0);

        $this->assertSame('elite', $result->level);
        $this->assertNull($result->nextLevel);
        $this->assertSame(1.0, $result->progress);
    }

    public function testExactlyOnTheEliteThresholdIsElite(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 120.0);

        $this->assertSame('elite', $result->level);
        $this->assertNull($result->nextLevel);
        $this->assertSame(1.0, $result->progress);
    }

    public function testThresholdsAreInterpolatedBetweenBodyweightRows(): void
    {
        // bodyweight 80 sits midway -> thresholds midway
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 80.0, 100.0);

        $this->assertSame([60.0, 80.0, 100.0, 120.0, 140.0], $result->thresholds);
        $this->assertSame('intermediate', $result->level);
        $this->assertEqualsWithDelta(0.0, $result->progress, 0.00001);
    }

    public function testBodyweightBelowTheTableClampsToTheLightestRow(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 40.0, 50.0);

        $this->assertSame([40.0, 60.0, 80.0, 100.0, 120.0], $result->thresholds);
    }

    public function testBodyweightAboveTheTableClampsToTheHeaviestRow(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 150.0, 50.0);

        $this->assertSame([80.0, 100.0, 120.0, 140.0, 160.0], $result->thresholds);
    }

    public function testUnsortedRowsAreSortedBeforeInterpolation(): void
    {
        $unsorted = [
            new StandardRow(100.0, [80.0, 100.0, 120.0, 140.0, 160.0]),
            new StandardRow(60.0, [40.0, 60.0, 80.0, 100.0, 120.0]),
        ];

        $result = StrengthLevelCalculator::levelFor($unsorted, 80.0, 100.0);

        $this->assertSame([60.0, 80.0, 100.0, 120.0, 140.0], $result->thresholds);
    }

    public function testInterpolatedThresholdsAreRoundedToOneDecimal(): void
    {
        // Barbell Row, female, bodyweight 75 -> midway between the 70 and 80 rows
        $result = StrengthLevelCalculator::levelFor(self::standardFor('row')->rowsForSex('female'), 75.0, 43.0);

        $this->assertSame([21.0, 31.0, 43.0, 57.5, 72.5], $result->thresholds);
        $this->assertSame('intermediate', $result->level);
        $this->assertEqualsWithDelta(0.0, $result->progress, 0.00001);
    }

    public function testMaleBenchAtEightyKilosClassifiesAnIntermediateLifter(): void
    {
        // 100kg x 5 -> e1rm 116.67; male bench @80kg: [49, 68, 91, 118, 147]
        $result = StrengthLevelCalculator::levelFor(
            self::standardFor('bench')->rowsForSex('male'),
            80.0,
            116.666666,
        );

        $this->assertSame([49.0, 68.0, 91.0, 118.0, 147.0], $result->thresholds);
        $this->assertSame('intermediate', $result->level);
        $this->assertSame('advanced', $result->nextLevel);
        $this->assertEqualsWithDelta(0.9506, $result->progress, 0.0001);
    }

    public function testFemaleSquatAtSixtyKilosClassifiesAnAdvancedLifter(): void
    {
        // female squat @60kg: [31, 47, 66, 89, 113]
        $result = StrengthLevelCalculator::levelFor(
            self::standardFor('squat')->rowsForSex('female'),
            60.0,
            100.0,
        );

        $this->assertSame([31.0, 47.0, 66.0, 89.0, 113.0], $result->thresholds);
        $this->assertSame('advanced', $result->level);
        $this->assertSame('elite', $result->nextLevel);
        $this->assertEqualsWithDelta(11 / 24, $result->progress, 0.00001);
    }

    public function testMaleDeadliftAtTheEliteThresholdIsElite(): void
    {
        // male deadlift @100kg: [97, 124, 156, 190, 227]
        $result = StrengthLevelCalculator::levelFor(
            self::standardFor('deadlift')->rowsForSex('male'),
            100.0,
            227.0,
        );

        $this->assertSame('elite', $result->level);
        $this->assertNull($result->nextLevel);
        $this->assertSame(1.0, $result->progress);
    }

    public function testMaleOverheadPressBelowBeginnerIsUntrained(): void
    {
        // male ohp @60kg: [20, 31, 44, 60, 77]
        $result = StrengthLevelCalculator::levelFor(
            self::standardFor('ohp')->rowsForSex('male'),
            60.0,
            10.0,
        );

        $this->assertSame([20.0, 31.0, 44.0, 60.0, 77.0], $result->thresholds);
        $this->assertSame('untrained', $result->level);
        $this->assertSame('beginner', $result->nextLevel);
        $this->assertEqualsWithDelta(0.5, $result->progress, 0.00001);
    }

    public function testFemaleRowsAreUsedForFemaleAndMaleRowsForMale(): void
    {
        $rowStandard = self::standardFor('row');

        $male = StrengthLevelCalculator::levelFor($rowStandard->rowsForSex('male'), 80.0, 50.0);
        $female = StrengthLevelCalculator::levelFor($rowStandard->rowsForSex('female'), 80.0, 50.0);

        $this->assertSame([39.0, 56.0, 76.0, 99.0, 124.0], $male->thresholds);
        $this->assertSame([22.0, 32.0, 44.0, 59.0, 74.0], $female->thresholds);
        $this->assertSame('beginner', $male->level);
        $this->assertSame('intermediate', $female->level);
    }

    public function testZeroE1rmIsUntrainedWithoutProgress(): void
    {
        $result = StrengthLevelCalculator::levelFor(self::simplifiedTable(), 60.0, 0.0);

        $this->assertSame('untrained', $result->level);
        $this->assertSame('beginner', $result->nextLevel);
        $this->assertSame(0.0, $result->progress);
    }
}
