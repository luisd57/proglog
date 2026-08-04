<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\Handler\GetTemplateHandler;
use App\Application\Template\Service\TemplateAssembler;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetTemplateHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private GetTemplateHandler $handler;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new GetTemplateHandler(
            $this->workoutTemplateRepository,
            new TemplateAssembler($this->workoutTemplateRepository, $this->exerciseRepository),
        );
    }

    public function testGetReturnsTemplateWithComposedExercises(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Push Day', sortOrder: 2);
        $benchId = ExerciseId::generate();
        $templateExercise = DomainTestHelper::createTemplateExercise(
            workoutTemplateId: $workoutTemplate->getId(),
            exerciseId: $benchId,
            sortOrder: 0,
            targetSets: 3,
            restSeconds: 180,
        );

        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);
        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturn(new ArrayCollection([$templateExercise]));
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise(id: $benchId, name: 'Bench Press'));

        $result = $this->handler->__invoke($workoutTemplate->getId()->getValue());

        $this->assertSame('Push Day', $result->name);
        $this->assertSame(2, $result->sortOrder);
        $this->assertCount(1, $result->exercises);
        $this->assertSame('Bench Press', $result->exercises[0]->exercise->name);
        $this->assertSame(3, $result->exercises[0]->targetSets);
        $this->assertSame(180, $result->exercises[0]->restSeconds);
    }

    public function testGetUnknownTemplateThrowsTemplateNotFoundException(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn(null);

        $this->expectException(TemplateNotFoundException::class);

        $this->handler->__invoke('0198c5b6-0000-7000-8000-000000000000');
    }

    public function testGetWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
