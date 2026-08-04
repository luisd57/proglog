<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\DTO\Input\TemplateExerciseLineInputDTO;
use App\Application\Template\DTO\Input\UpdateTemplateInputDTO;
use App\Application\Template\Handler\UpdateTemplateHandler;
use App\Application\Template\Service\TemplateAssembler;
use App\Application\Template\Service\TemplateExerciseLineFactory;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateTemplateHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private UpdateTemplateHandler $handler;

    private WorkoutTemplate $workoutTemplate;
    private ExerciseId $benchId;
    private ExerciseId $ohpId;
    private ?ArrayCollection $addedExercises = null;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new UpdateTemplateHandler(
            $this->workoutTemplateRepository,
            new TemplateExerciseLineFactory($this->exerciseRepository),
            new TemplateAssembler($this->workoutTemplateRepository, $this->exerciseRepository),
        );

        $this->workoutTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Push Day', sortOrder: 7);
        $this->benchId = ExerciseId::generate();
        $this->ohpId = ExerciseId::generate();

        $catalog = [
            $this->benchId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $this->benchId,
                name: 'Bench Press',
            ),
            $this->ohpId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $this->ohpId,
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

        $this->workoutTemplateRepository
            ->method('addExercises')
            ->willReturnCallback(function (ArrayCollection $templateExercises): void {
                $this->addedExercises = $templateExercises;
            });

        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturnCallback(fn (): ArrayCollection => $this->addedExercises ?? new ArrayCollection());
    }

    public function testUpdateReplacesNameAndExerciseListPreservingNewOrder(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn($this->workoutTemplate);
        $this->workoutTemplateRepository
            ->expects($this->once())
            ->method('deleteExercisesByTemplateId')
            ->with($this->workoutTemplate->getId());

        $result = $this->handler->__invoke(new UpdateTemplateInputDTO(
            id: $this->workoutTemplate->getId()->getValue(),
            name: 'Push Day A',
            exercises: [
                new TemplateExerciseLineInputDTO(exerciseId: $this->ohpId->getValue(), targetSets: 4),
                new TemplateExerciseLineInputDTO(exerciseId: $this->benchId->getValue()),
            ],
        ));

        $this->assertSame('Push Day A', $result->name);
        $this->assertSame(
            [$this->ohpId->getValue(), $this->benchId->getValue()],
            array_map(fn ($templateExerciseDto) => $templateExerciseDto->exercise->id, $result->exercises),
        );
        $this->assertSame(4, $result->exercises[0]->targetSets);
    }

    public function testUpdateKeepsTemplateSortOrderUnchanged(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn($this->workoutTemplate);

        $result = $this->handler->__invoke(new UpdateTemplateInputDTO(
            id: $this->workoutTemplate->getId()->getValue(),
            name: 'Renamed',
            exercises: [],
        ));

        $this->assertSame(7, $result->sortOrder);
    }

    public function testUpdateUnknownTemplateThrowsTemplateNotFoundException(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn(null);

        $this->expectException(TemplateNotFoundException::class);

        $this->handler->__invoke(new UpdateTemplateInputDTO(
            id: '0198c5b6-0000-7000-8000-000000000000',
            name: 'X',
            exercises: [],
        ));
    }

    public function testUpdateWithUnknownExerciseThrowsBeforeDeletingLines(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn($this->workoutTemplate);
        $this->workoutTemplateRepository->expects($this->never())->method('deleteExercisesByTemplateId');
        $this->workoutTemplateRepository->expects($this->never())->method('save');

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(new UpdateTemplateInputDTO(
            id: $this->workoutTemplate->getId()->getValue(),
            name: 'Push Day A',
            exercises: [
                new TemplateExerciseLineInputDTO(exerciseId: ExerciseId::generate()->getValue()),
            ],
        ));
    }
}
