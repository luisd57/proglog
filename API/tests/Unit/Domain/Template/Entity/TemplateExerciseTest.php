<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Template\Entity;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Id\TemplateExerciseId;
use App\Domain\Template\Id\WorkoutTemplateId;
use PHPUnit\Framework\TestCase;

final class TemplateExerciseTest extends TestCase
{
    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = TemplateExerciseId::generate();
        $workoutTemplateId = WorkoutTemplateId::generate();
        $exerciseId = ExerciseId::generate();

        $templateExercise = TemplateExercise::create(
            id: $id,
            workoutTemplateId: $workoutTemplateId,
            exerciseId: $exerciseId,
            sortOrder: 2,
            targetSets: 3,
            targetReps: 8,
            restSeconds: 120,
        );

        $this->assertTrue($id->equals($templateExercise->getId()));
        $this->assertTrue($workoutTemplateId->equals($templateExercise->getTemplateId()));
        $this->assertTrue($exerciseId->equals($templateExercise->getExerciseId()));
        $this->assertSame(2, $templateExercise->getSortOrder());
        $this->assertSame(3, $templateExercise->getTargetSets());
        $this->assertSame(8, $templateExercise->getTargetReps());
        $this->assertSame(120, $templateExercise->getRestSeconds());
    }

    public function testCreateDefaultsTargetsToNull(): void
    {
        $templateExercise = TemplateExercise::create(
            id: TemplateExerciseId::generate(),
            workoutTemplateId: WorkoutTemplateId::generate(),
            exerciseId: ExerciseId::generate(),
            sortOrder: 0,
        );

        $this->assertNull($templateExercise->getTargetSets());
        $this->assertNull($templateExercise->getTargetReps());
        $this->assertNull($templateExercise->getRestSeconds());
    }

    public function testCreateWithNegativeSortOrderThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TemplateExercise::create(
            id: TemplateExerciseId::generate(),
            workoutTemplateId: WorkoutTemplateId::generate(),
            exerciseId: ExerciseId::generate(),
            sortOrder: -1,
        );
    }
}
