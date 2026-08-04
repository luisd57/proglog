<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Service\StrengthStandards;
use App\Domain\Stats\ValueObject\LiftStandard;
use App\Domain\Stats\ValueObject\StandardRow;
use PHPUnit\Framework\TestCase;

final class StrengthStandardsTest extends TestCase
{
    public function testAllCoversTheFiveMainLiftsForBothSexesWithAscendingThresholds(): void
    {
        $standards = StrengthStandards::all();
        $lifts = array_map(fn (LiftStandard $liftStandard) => $liftStandard->lift, $standards);

        $this->assertSame(['squat', 'bench', 'deadlift', 'ohp', 'row'], $lifts);

        foreach ($standards as $liftStandard) {
            foreach (['male', 'female'] as $sex) {
                $rows = $liftStandard->rowsForSex($sex);

                $this->assertGreaterThanOrEqual(5, count($rows), "{$liftStandard->lift}/{$sex} rows");

                /** @var StandardRow $standardRow */
                foreach ($rows as $standardRow) {
                    $sorted = $standardRow->thresholds;
                    sort($sorted);

                    $this->assertCount(5, $standardRow->thresholds);
                    $this->assertSame(
                        $sorted,
                        $standardRow->thresholds,
                        "{$liftStandard->lift}/{$sex} @{$standardRow->bodyweightKg}kg thresholds ascending",
                    );
                }
            }
        }
    }

    public function testAllListsTheSeededExerciseNamesEachStandardAppliesTo(): void
    {
        $namesByLift = [];

        foreach (StrengthStandards::all() as $liftStandard) {
            $namesByLift[$liftStandard->lift] = $liftStandard->exerciseNames;
        }

        $this->assertSame(['Barbell Squat', 'Barbell Full Squat'], $namesByLift['squat']);
        $this->assertSame(['Barbell Bench Press - Medium Grip'], $namesByLift['bench']);
        $this->assertSame(['Barbell Deadlift'], $namesByLift['deadlift']);
        $this->assertSame(['Standing Military Press', 'Barbell Shoulder Press'], $namesByLift['ohp']);
        $this->assertSame(['Bent Over Barbell Row'], $namesByLift['row']);
    }

    public function testRowsForSexFallsBackToTheMaleTableForAnythingButFemale(): void
    {
        $squat = StrengthStandards::all()[0];

        $this->assertSame($squat->male, $squat->rowsForSex('male'));
        $this->assertSame($squat->female, $squat->rowsForSex('female'));
        $this->assertSame($squat->male, $squat->rowsForSex('unknown'));
    }

    public function testStandardRowRejectsAThresholdCountOtherThanFive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StandardRow(80.0, [1.0, 2.0, 3.0, 4.0]);
    }
}
