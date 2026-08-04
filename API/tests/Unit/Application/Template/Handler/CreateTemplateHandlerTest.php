<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\DTO\Input\CreateTemplateInputDTO;
use App\Application\Template\DTO\Input\TemplateExerciseLineInputDTO;
use App\Application\Template\Handler\CreateTemplateHandler;
use App\Application\Template\Service\TemplateAssembler;
use App\Application\Template\Service\TemplateExerciseLineFactory;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateTemplateHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private CreateTemplateHandler $handler;

    private ExerciseId $benchId;
    private ExerciseId $ohpId;
    private ?WorkoutTemplate $savedTemplate = null;
    private ?ArrayCollection $addedExercises = null;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->handler = new CreateTemplateHandler(
            $this->workoutTemplateRepository,
            new TemplateExerciseLineFactory($this->exerciseRepository),
            new TemplateAssembler($this->workoutTemplateRepository, $this->exerciseRepository),
        );

        $this->benchId = ExerciseId::generate();
        $this->ohpId = ExerciseId::generate();

        $catalog = [
            $this->benchId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $this->benchId,
                name: 'Bench Press',
                primaryMuscles: ['chest'],
                secondaryMuscles: ['shoulders', 'triceps'],
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
            ->method('save')
            ->willReturnCallback(function (WorkoutTemplate $workoutTemplate): void {
                $this->savedTemplate = $workoutTemplate;
            });

        $this->workoutTemplateRepository
            ->method('addExercises')
            ->willReturnCallback(function (ArrayCollection $templateExercises): void {
                $this->addedExercises = $templateExercises;
            });

        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturnCallback(fn (): ArrayCollection => $this->addedExercises ?? new ArrayCollection());
    }

    public function testCreateBuildsTemplateWithOrderedExerciseLines(): void
    {
        $this->workoutTemplateRepository->method('findHighestSortOrder')->willReturn(null);

        $result = $this->handler->__invoke(new CreateTemplateInputDTO(
            name: 'Push Day',
            exercises: [
                new TemplateExerciseLineInputDTO(
                    exerciseId: $this->benchId->getValue(),
                    targetSets: 3,
                    targetReps: 8,
                    restSeconds: 180,
                ),
                new TemplateExerciseLineInputDTO(exerciseId: $this->ohpId->getValue()),
            ],
        ));

        $this->assertSame('Push Day', $result->name);
        $this->assertSame(0, $result->sortOrder);
        $this->assertSame(
            ['Bench Press', 'Overhead Press'],
            array_map(fn ($templateExerciseDto) => $templateExerciseDto->exercise->name, $result->exercises),
        );
        $this->assertSame(3, $result->exercises[0]->targetSets);
        $this->assertSame(180, $result->exercises[0]->restSeconds);
        $this->assertNull($result->exercises[1]->targetSets);

        $this->assertNotNull($this->savedTemplate);
        $this->assertNotNull($this->addedExercises);
        $this->assertSame(
            [0, 1],
            $this->addedExercises
                ->map(fn (TemplateExercise $templateExercise) => $templateExercise->getSortOrder())
                ->toArray(),
        );
        $this->assertTrue(
            $this->addedExercises->first()->getTemplateId()->equals($this->savedTemplate->getId())
        );
    }

    public function testCreateAssignsNextSortOrderAfterHighestExisting(): void
    {
        $this->workoutTemplateRepository->method('findHighestSortOrder')->willReturn(4);

        $result = $this->handler->__invoke(new CreateTemplateInputDTO(name: 'Leg Day', exercises: []));

        $this->assertSame(5, $result->sortOrder);
    }

    public function testCreateTrimsName(): void
    {
        $this->workoutTemplateRepository->method('findHighestSortOrder')->willReturn(null);

        $result = $this->handler->__invoke(new CreateTemplateInputDTO(name: '  Push Day  ', exercises: []));

        $this->assertSame('Push Day', $result->name);
    }

    public function testCreateWithBlankNameThrowsInvalidArgumentException(): void
    {
        $this->workoutTemplateRepository->method('findHighestSortOrder')->willReturn(null);
        $this->workoutTemplateRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateTemplateInputDTO(name: ' ', exercises: []));
    }

    public function testCreateWithUnknownExerciseThrowsExerciseNotFoundException(): void
    {
        $this->workoutTemplateRepository->expects($this->never())->method('save');
        $this->workoutTemplateRepository->expects($this->never())->method('addExercises');

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(new CreateTemplateInputDTO(
            name: 'Push Day',
            exercises: [
                new TemplateExerciseLineInputDTO(exerciseId: ExerciseId::generate()->getValue()),
            ],
        ));
    }
}
