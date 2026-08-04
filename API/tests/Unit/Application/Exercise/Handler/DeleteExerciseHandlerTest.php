<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exercise\Handler;

use App\Application\Exercise\Handler\DeleteExerciseHandler;
use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteExerciseHandlerTest extends TestCase
{
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private DeleteExerciseHandler $handler;

    protected function setUp(): void
    {
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new DeleteExerciseHandler($this->exerciseRepository);
    }

    public function testDeleteCustomExerciseCallsRepository(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(id: $id);

        $this->exerciseRepository->method('findById')->willReturn($exercise);
        $this->exerciseRepository
            ->expects($this->once())
            ->method('delete')
            ->with($exercise);

        $this->handler->__invoke($id->getValue());
    }

    public function testDeleteNonExistentExerciseThrowsException(): void
    {
        $this->exerciseRepository->method('findById')->willReturn(null);
        $this->exerciseRepository->expects($this->never())->method('delete');

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(ExerciseId::generate()->getValue());
    }

    public function testDeleteBuiltInExerciseThrowsException(): void
    {
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise());
        $this->exerciseRepository->expects($this->never())->method('delete');

        $this->expectException(BuiltInExerciseImmutableException::class);

        $this->handler->__invoke(ExerciseId::generate()->getValue());
    }
}
