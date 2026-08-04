<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\Handler\GetTemplateMusclesHandler;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetTemplateMusclesHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private GetTemplateMusclesHandler $handler;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new GetTemplateMusclesHandler(
            $this->workoutTemplateRepository,
            $this->exerciseRepository,
        );
    }

    public function testMusclesAggregatesPrimaryAndRemovesThemFromSecondary(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();
        $benchId = ExerciseId::generate();
        $ohpId = ExerciseId::generate();

        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);
        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createTemplateExercise(
                    workoutTemplateId: $workoutTemplate->getId(),
                    exerciseId: $benchId,
                    sortOrder: 0,
                ),
                DomainTestHelper::createTemplateExercise(
                    workoutTemplateId: $workoutTemplate->getId(),
                    exerciseId: $ohpId,
                    sortOrder: 1,
                ),
            ]));

        $catalog = [
            $benchId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $benchId,
                name: 'Bench Press',
                primaryMuscles: ['chest'],
                secondaryMuscles: ['shoulders', 'triceps'],
            ),
            $ohpId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $ohpId,
                name: 'Overhead Press',
                primaryMuscles: ['shoulders'],
                secondaryMuscles: ['triceps'],
            ),
        ];
        $this->exerciseRepository
            ->method('findById')
            ->willReturnCallback(
                fn (ExerciseId $exerciseId): ?Exercise => $catalog[$exerciseId->getValue()] ?? null
            );

        $result = $this->handler->__invoke($workoutTemplate->getId()->getValue());

        $sortedPrimary = $result->primary;
        sort($sortedPrimary);
        $this->assertSame(['chest', 'shoulders'], $sortedPrimary);
        // shoulders is primary somewhere, so it must not appear as secondary
        $this->assertSame(['triceps'], $result->secondary);
    }

    public function testMusclesForTemplateWithoutExercisesReturnsEmptyLists(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();

        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);
        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturn(new ArrayCollection());

        $result = $this->handler->__invoke($workoutTemplate->getId()->getValue());

        $this->assertSame([], $result->primary);
        $this->assertSame([], $result->secondary);
    }

    public function testMusclesUnknownTemplateThrowsTemplateNotFoundException(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn(null);

        $this->expectException(TemplateNotFoundException::class);

        $this->handler->__invoke('0198c5b6-0000-7000-8000-000000000000');
    }
}
