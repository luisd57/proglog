<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\CreateExerciseInputDTO;
use App\Application\Exercise\Handler\CreateExerciseHandler;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Exception\DuplicateExerciseNameException;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateExerciseHandlerTest extends TestCase
{
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private CreateExerciseHandler $handler;

    protected function setUp(): void
    {
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new CreateExerciseHandler($this->exerciseRepository);
    }

    public function testCreateCustomExerciseSavesAndReturnsDto(): void
    {
        $this->exerciseRepository->method('findByName')->willReturn(null);

        $savedExercise = null;
        $this->exerciseRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Exercise $exercise) use (&$savedExercise): void {
                $savedExercise = $exercise;
            });

        $result = $this->handler->__invoke(new CreateExerciseInputDTO(
            name: 'Machine Rear Delt Fly',
            primaryMuscles: ['shoulders'],
            secondaryMuscles: ['traps'],
            equipment: 'machine',
        ));

        $this->assertTrue($result->isCustom);
        $this->assertSame('Machine Rear Delt Fly', $result->name);
        $this->assertSame(['shoulders'], $result->primaryMuscles);
        $this->assertSame(['traps'], $result->secondaryMuscles);
        $this->assertNotNull($savedExercise);
        $this->assertTrue($savedExercise->isCustom());
    }

    public function testCreateWithDuplicateNameThrowsException(): void
    {
        $this->exerciseRepository
            ->method('findByName')
            ->with('Barbell Bench Press')
            ->willReturn(DomainTestHelper::createBuiltInExercise());

        $this->expectException(DuplicateExerciseNameException::class);

        $this->handler->__invoke(new CreateExerciseInputDTO(
            name: 'Barbell Bench Press',
            primaryMuscles: ['chest'],
        ));
    }

    public function testCreateWithEmptyNameThrowsInvalidArgumentException(): void
    {
        $this->exerciseRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateExerciseInputDTO(
            name: '  ',
            primaryMuscles: ['chest'],
        ));
    }

    public function testCreateWithEmptyPrimaryMusclesThrowsInvalidArgumentException(): void
    {
        $this->exerciseRepository->method('findByName')->willReturn(null);
        $this->exerciseRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateExerciseInputDTO(
            name: 'X',
            primaryMuscles: [],
        ));
    }
}
