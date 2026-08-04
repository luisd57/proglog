<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\StartSessionInputDTO;
use App\Application\Session\Handler\StartSessionHandler;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class StartSessionHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private MockClock $clock;
    private StartSessionHandler $handler;

    private WorkoutTemplate $workoutTemplate;
    private ExerciseId $benchId;
    private ExerciseId $ohpId;
    private ?Session $savedSession = null;
    private ?ArrayCollection $addedExercises = null;
    private ?SessionExercise $previousExercise = null;

    /** @var array<string, ArrayCollection> */
    private array $setsBySessionExerciseId = [];

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $profileRepository = $this->createMock(ProfileRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-08-04 10:00:00'));
        $this->handler = new StartSessionHandler(
            $this->sessionRepository,
            $this->workoutTemplateRepository,
            new SessionAssembler(
                $this->sessionRepository,
                $this->workoutTemplateRepository,
                $this->exerciseRepository,
                $profileRepository,
            ),
            $this->clock,
        );

        $this->workoutTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Push Day');
        $this->benchId = ExerciseId::generate();
        $this->ohpId = ExerciseId::generate();

        $catalog = [
            $this->benchId->getValue() => DomainTestHelper::createBuiltInExercise(
                id: $this->benchId,
                name: 'Bench Press',
                primaryMuscles: ['chest'],
                secondaryMuscles: ['triceps'],
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

        $this->sessionRepository
            ->method('save')
            ->willReturnCallback(function (Session $session): void {
                $this->savedSession = $session;
            });

        $this->sessionRepository
            ->method('addExercises')
            ->willReturnCallback(function (ArrayCollection $sessionExercises): void {
                $this->addedExercises = $sessionExercises;
            });

        $this->sessionRepository
            ->method('findExercisesBySessionId')
            ->willReturnCallback(fn (): ArrayCollection => $this->addedExercises ?? new ArrayCollection());

        $this->sessionRepository
            ->method('findSetsBySessionExerciseId')
            ->willReturnCallback(
                fn (SessionExerciseId $sessionExerciseId): ArrayCollection => $this->setsBySessionExerciseId[$sessionExerciseId->getValue()]
                    ?? new ArrayCollection()
            );

        $this->sessionRepository
            ->method('findLatestFinishedExercise')
            ->willReturnCallback(
                fn (ExerciseId $exerciseId): ?SessionExercise => $this->previousExercise !== null
                    && $this->previousExercise->getExerciseId()->equals($exerciseId)
                    ? $this->previousExercise
                    : null
            );
    }

    private function givenTemplateWithBenchAndOhp(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn($this->workoutTemplate);
        $this->workoutTemplateRepository
            ->method('findExercisesByTemplateId')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createTemplateExercise(
                    workoutTemplateId: $this->workoutTemplate->getId(),
                    exerciseId: $this->benchId,
                    sortOrder: 0,
                    targetSets: 3,
                    targetReps: 8,
                    restSeconds: 150,
                ),
                DomainTestHelper::createTemplateExercise(
                    workoutTemplateId: $this->workoutTemplate->getId(),
                    exerciseId: $this->ohpId,
                    sortOrder: 1,
                ),
            ]));
    }

    public function testStartFromTemplateCopiesExercisesInOrderWithTargets(): void
    {
        $this->givenTemplateWithBenchAndOhp();

        $result = $this->handler->__invoke(new StartSessionInputDTO(
            templateId: $this->workoutTemplate->getId()->getValue(),
        ));

        $this->assertSame($this->workoutTemplate->getId()->getValue(), $result->templateId);
        $this->assertSame('Push Day', $result->templateName);
        $this->assertNull($result->finishedAt);
        $this->assertSame(
            ['Bench Press', 'Overhead Press'],
            array_map(fn ($sessionExerciseDto) => $sessionExerciseDto->exercise->name, $result->exercises),
        );
        $this->assertSame(3, $result->exercises[0]->targetSets);
        $this->assertSame(150, $result->exercises[0]->restSeconds);
        // no template rest, no profile row -> ultimate default
        $this->assertSame(120, $result->exercises[1]->restSeconds);
        $this->assertSame([], $result->exercises[0]->previousSets);
        $this->assertSame([], $result->exercises[0]->sets);
        $this->assertSame([0, 1], array_map(
            fn ($sessionExerciseDto) => $sessionExerciseDto->sortOrder,
            $result->exercises,
        ));
    }

    public function testStartUsesClockForStartedAt(): void
    {
        $this->givenTemplateWithBenchAndOhp();

        $result = $this->handler->__invoke(new StartSessionInputDTO(
            templateId: $this->workoutTemplate->getId()->getValue(),
        ));

        $this->assertEquals(new \DateTimeImmutable('2026-08-04 10:00:00'), $result->startedAt);
        $this->assertNotNull($this->savedSession);
        $this->assertEquals(new \DateTimeImmutable('2026-08-04 10:00:00'), $this->savedSession->getStartedAt());
    }

    public function testStartReturnsPreviousSetsFromLatestFinishedSession(): void
    {
        $this->givenTemplateWithBenchAndOhp();

        $this->previousExercise = DomainTestHelper::createSessionExercise(exerciseId: $this->benchId);
        $this->setsBySessionExerciseId[$this->previousExercise->getId()->getValue()] = new ArrayCollection([
            DomainTestHelper::createSetLog(
                sessionExerciseId: $this->previousExercise->getId(),
                setNumber: 1,
                weightKg: 60.0,
                reps: 10,
                isWarmup: true,
            ),
            DomainTestHelper::createSetLog(
                sessionExerciseId: $this->previousExercise->getId(),
                setNumber: 2,
                weightKg: 80.0,
                reps: 8,
            ),
            DomainTestHelper::createSetLog(
                sessionExerciseId: $this->previousExercise->getId(),
                setNumber: 3,
                weightKg: 80.0,
                reps: 7,
            ),
        ]);

        $result = $this->handler->__invoke(new StartSessionInputDTO(
            templateId: $this->workoutTemplate->getId()->getValue(),
        ));

        $benchPreviousSets = $result->exercises[0]->previousSets;
        $this->assertSame(
            [[60.0, 10, true], [80.0, 8, false], [80.0, 7, false]],
            array_map(
                fn ($setDto) => [$setDto->weightKg, $setDto->reps, $setDto->isWarmup],
                $benchPreviousSets,
            ),
        );
        // OHP was never performed in a finished session
        $this->assertSame([], $result->exercises[1]->previousSets);
    }

    public function testStartBlankSessionHasNoTemplateAndNoExercises(): void
    {
        $result = $this->handler->__invoke(new StartSessionInputDTO(templateId: null));

        $this->assertNull($result->templateId);
        $this->assertNull($result->templateName);
        $this->assertSame([], $result->exercises);
        $this->assertNull($this->addedExercises);
    }

    public function testStartWithUnknownTemplateThrowsTemplateNotFoundException(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('save');

        $this->expectException(TemplateNotFoundException::class);

        $this->handler->__invoke(new StartSessionInputDTO(
            templateId: '0198c5b6-0000-7000-8000-000000000000',
        ));
    }

    public function testStartWithMalformedTemplateIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new StartSessionInputDTO(templateId: 'nope'));
    }
}
