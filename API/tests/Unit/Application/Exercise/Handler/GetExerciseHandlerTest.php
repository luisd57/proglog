<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exercise\Handler;

use App\Application\Exercise\Handler\GetExerciseHandler;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetExerciseHandlerTest extends TestCase
{
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private GetExerciseHandler $handler;

    protected function setUp(): void
    {
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new GetExerciseHandler($this->exerciseRepository);
    }

    public function testGetExistingExerciseReturnsDtoWithParsedMuscles(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createBuiltInExercise(id: $id);

        $this->exerciseRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($exercise);

        $result = $this->handler->__invoke($id->getValue());

        $this->assertSame($id->getValue(), $result->id);
        $this->assertSame('Barbell Bench Press', $result->name);
        $this->assertSame(['chest'], $result->primaryMuscles);
    }

    public function testGetNonExistentExerciseThrowsException(): void
    {
        $this->exerciseRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(ExerciseId::generate()->getValue());
    }

    public function testGetWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
