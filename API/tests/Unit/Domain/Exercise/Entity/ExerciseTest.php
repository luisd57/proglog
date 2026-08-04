<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exercise\Entity;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use PHPUnit\Framework\TestCase;

final class ExerciseTest extends TestCase
{
    // --- createCustom() factory ---

    public function testCreateCustomSetsAllPropertiesCorrectly(): void
    {
        $id = ExerciseId::generate();

        $exercise = Exercise::createCustom(
            id: $id,
            name: 'Machine Rear Delt Fly',
            primaryMuscles: ['shoulders'],
            secondaryMuscles: ['traps'],
            equipment: 'machine',
            category: 'strength',
            instructions: 'Sit facing the pad.',
        );

        $this->assertTrue($id->equals($exercise->getId()));
        $this->assertSame('Machine Rear Delt Fly', $exercise->getName());
        $this->assertSame(['shoulders'], $exercise->getPrimaryMuscles());
        $this->assertSame(['traps'], $exercise->getSecondaryMuscles());
        $this->assertSame('machine', $exercise->getEquipment());
        $this->assertSame('strength', $exercise->getCategory());
        $this->assertSame('Sit facing the pad.', $exercise->getInstructions());
        $this->assertTrue($exercise->isCustom());
    }

    public function testCreateCustomDefaultsOptionalFieldsToNull(): void
    {
        $exercise = Exercise::createCustom(
            id: ExerciseId::generate(),
            name: 'Cable Thing',
            primaryMuscles: ['lats'],
        );

        $this->assertSame([], $exercise->getSecondaryMuscles());
        $this->assertNull($exercise->getEquipment());
        $this->assertNull($exercise->getCategory());
        $this->assertNull($exercise->getInstructions());
    }

    public function testCreateCustomTrimsName(): void
    {
        $exercise = Exercise::createCustom(
            id: ExerciseId::generate(),
            name: '  Cable Thing  ',
            primaryMuscles: ['lats'],
        );

        $this->assertSame('Cable Thing', $exercise->getName());
    }

    public function testCreateCustomWithEmptyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name is required.');

        Exercise::createCustom(
            id: ExerciseId::generate(),
            name: '   ',
            primaryMuscles: ['chest'],
        );
    }

    public function testCreateCustomWithEmptyPrimaryMusclesThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one primary muscle is required.');

        Exercise::createCustom(
            id: ExerciseId::generate(),
            name: 'X',
            primaryMuscles: [],
        );
    }

    public function testCreateCustomWithBlankMuscleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Exercise::createCustom(
            id: ExerciseId::generate(),
            name: 'X',
            primaryMuscles: ['chest', '  '],
        );
    }

    // --- createBuiltIn() factory ---

    public function testCreateBuiltInIsNotCustom(): void
    {
        $exercise = Exercise::createBuiltIn(
            id: ExerciseId::generate(),
            name: 'Barbell Squat',
            primaryMuscles: ['quadriceps'],
            secondaryMuscles: ['glutes', 'lower back'],
            equipment: 'barbell',
        );

        $this->assertFalse($exercise->isCustom());
    }

    // --- mutators ---

    public function testRenameChangesNameAndTrims(): void
    {
        $exercise = $this->createExercise();

        $exercise->rename('  Cable Row Variation ');

        $this->assertSame('Cable Row Variation', $exercise->getName());
    }

    public function testRenameWithEmptyNameThrowsException(): void
    {
        $exercise = $this->createExercise();

        $this->expectException(\InvalidArgumentException::class);

        $exercise->rename('  ');
    }

    public function testReplacePrimaryMusclesWithEmptyListThrowsException(): void
    {
        $exercise = $this->createExercise();

        $this->expectException(\InvalidArgumentException::class);

        $exercise->replacePrimaryMuscles([]);
    }

    public function testReplaceSecondaryMusclesAcceptsEmptyList(): void
    {
        $exercise = $this->createExercise();

        $exercise->replaceSecondaryMuscles([]);

        $this->assertSame([], $exercise->getSecondaryMuscles());
    }

    public function testChangeEquipmentAcceptsNull(): void
    {
        $exercise = $this->createExercise();

        $exercise->changeEquipment(null);

        $this->assertNull($exercise->getEquipment());
    }

    // --- targetsMuscle() ---

    public function testTargetsMuscleMatchesPrimaryAndSecondary(): void
    {
        $exercise = Exercise::createCustom(
            id: ExerciseId::generate(),
            name: 'Bench',
            primaryMuscles: ['chest'],
            secondaryMuscles: ['triceps'],
        );

        $this->assertTrue($exercise->targetsMuscle('chest'));
        $this->assertTrue($exercise->targetsMuscle('triceps'));
        $this->assertFalse($exercise->targetsMuscle('biceps'));
    }

    private function createExercise(): Exercise
    {
        return Exercise::createCustom(
            id: ExerciseId::generate(),
            name: 'Cable Thing',
            primaryMuscles: ['lats'],
            secondaryMuscles: ['biceps'],
            equipment: 'cable',
        );
    }
}
