<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\UpdateExerciseInputDTO;
use App\Application\Exercise\Handler\UpdateExerciseHandler;
use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\DuplicateExerciseNameException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateExerciseHandlerTest extends TestCase
{
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private UpdateExerciseHandler $handler;

    protected function setUp(): void
    {
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new UpdateExerciseHandler($this->exerciseRepository);
    }

    public function testUpdateAppliesOnlyProvidedFields(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(
            id: $id,
            name: 'Cable Thing',
            primaryMuscles: ['lats'],
            secondaryMuscles: [],
        );

        $this->exerciseRepository->method('findById')->willReturn($exercise);
        $this->exerciseRepository->method('findByName')->willReturn(null);
        $this->exerciseRepository->expects($this->once())->method('save');

        $result = $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: $id->getValue(),
            name: 'Cable Row Variation',
            secondaryMuscles: ['biceps'],
        ));

        $this->assertSame('Cable Row Variation', $result->name);
        $this->assertSame(['biceps'], $result->secondaryMuscles);
        // untouched fields keep their values
        $this->assertSame(['lats'], $result->primaryMuscles);
        $this->assertSame('machine', $result->equipment);
    }

    public function testUpdateWithProvidedNullClearsNullableField(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(id: $id, equipment: 'machine');

        $this->exerciseRepository->method('findById')->willReturn($exercise);
        $this->exerciseRepository->method('save');

        $result = $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: $id->getValue(),
            equipmentProvided: true,
            equipment: null,
        ));

        $this->assertNull($result->equipment);
    }

    public function testUpdateNonExistentExerciseThrowsException(): void
    {
        $this->exerciseRepository->method('findById')->willReturn(null);

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: ExerciseId::generate()->getValue(),
            name: 'Anything',
        ));
    }

    public function testUpdateBuiltInExerciseThrowsException(): void
    {
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise());
        $this->exerciseRepository->expects($this->never())->method('save');

        $this->expectException(BuiltInExerciseImmutableException::class);

        $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: ExerciseId::generate()->getValue(),
            name: 'Hacked',
        ));
    }

    public function testUpdateToNameOfAnotherExerciseThrowsException(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(id: $id, name: 'Cable Thing');

        $this->exerciseRepository->method('findById')->willReturn($exercise);
        $this->exerciseRepository
            ->method('findByName')
            ->with('Barbell Bench Press')
            ->willReturn(DomainTestHelper::createBuiltInExercise());

        $this->expectException(DuplicateExerciseNameException::class);

        $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: $id->getValue(),
            name: 'Barbell Bench Press',
        ));
    }

    public function testUpdateKeepingOwnNameDoesNotThrowDuplicate(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(id: $id, name: 'Cable Thing');

        $this->exerciseRepository->method('findById')->willReturn($exercise);
        $this->exerciseRepository->method('findByName')->with('Cable Thing')->willReturn($exercise);
        $this->exerciseRepository->expects($this->once())->method('save');

        $result = $this->handler->__invoke(new UpdateExerciseInputDTO(
            id: $id->getValue(),
            name: 'Cable Thing',
        ));

        $this->assertSame('Cable Thing', $result->name);
    }
}
