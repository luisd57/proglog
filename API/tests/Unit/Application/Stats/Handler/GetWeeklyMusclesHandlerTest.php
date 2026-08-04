<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Stats\Handler;

use App\Application\Stats\Handler\GetWeeklyMusclesHandler;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetWeeklyMusclesHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private MockClock $clock;
    private GetWeeklyMusclesHandler $handler;

    private Exercise $bench;
    private Exercise $row;

    /** @var array<int, Session> */
    private array $sessions = [];

    /** @var array<string, ArrayCollection> */
    private array $exercisesBySessionId = [];

    /** @var array<string, ArrayCollection> */
    private array $setsBySessionExerciseId = [];

    /** @var array<string, Exercise> */
    private array $catalog = [];

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-08-04 12:00:00'));
        $this->handler = new GetWeeklyMusclesHandler(
            $this->sessionRepository,
            $this->exerciseRepository,
            $this->clock,
        );

        $this->bench = DomainTestHelper::createBuiltInExercise(
            name: 'Bench Press',
            primaryMuscles: ['chest'],
            secondaryMuscles: ['triceps'],
        );
        $this->row = DomainTestHelper::createBuiltInExercise(
            name: 'Bent Over Barbell Row',
            primaryMuscles: ['middle back'],
            secondaryMuscles: ['biceps'],
        );
        $this->catalog = [
            $this->bench->getId()->getValue() => $this->bench,
            $this->row->getId()->getValue() => $this->row,
        ];

        $this->sessionRepository
            ->method('findFinishedSessionsBetween')
            ->willReturnCallback(
                fn (?\DateTimeImmutable $from): ArrayCollection => new ArrayCollection(array_values(array_filter(
                    $this->sessions,
                    fn (Session $session) => $from === null || $session->getStartedAt() >= $from,
                )))
            );
        $this->sessionRepository
            ->method('findExercisesBySessionId')
            ->willReturnCallback(
                fn (SessionId $sessionId): ArrayCollection => $this->exercisesBySessionId[$sessionId->getValue()]
                    ?? new ArrayCollection()
            );
        $this->sessionRepository
            ->method('findSetsBySessionExerciseId')
            ->willReturnCallback(
                fn (SessionExerciseId $sessionExerciseId): ArrayCollection => $this->setsBySessionExerciseId[$sessionExerciseId->getValue()]
                    ?? new ArrayCollection()
            );
        $this->exerciseRepository
            ->method('findById')
            ->willReturnCallback(
                fn (ExerciseId $exerciseId): ?Exercise => $this->catalog[$exerciseId->getValue()] ?? null
            );
    }

    /**
     * @param array<int, array{0: ExerciseId, 1: array<int, array{0: float, 1: int, 2: bool}>}> $exercises
     */
    private function givenFinishedSession(string $startedAt, array $exercises): Session
    {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable($startedAt),
            finishedAt: new \DateTimeImmutable($startedAt),
        );

        $sessionExercises = [];

        foreach ($exercises as $sortOrder => [$exerciseId, $sets]) {
            $sessionExercise = DomainTestHelper::createSessionExercise(
                sessionId: $session->getId(),
                exerciseId: $exerciseId,
                sortOrder: $sortOrder,
            );
            $setLogs = [];

            foreach ($sets as $index => $set) {
                $setLogs[] = DomainTestHelper::createSetLog(
                    sessionExerciseId: $sessionExercise->getId(),
                    setNumber: $index + 1,
                    weightKg: $set[0],
                    reps: $set[1],
                    isWarmup: $set[2],
                );
            }

            $sessionExercises[] = $sessionExercise;
            $this->setsBySessionExerciseId[$sessionExercise->getId()->getValue()] = new ArrayCollection($setLogs);
        }

        $this->sessions[] = $session;
        $this->exercisesBySessionId[$session->getId()->getValue()] = new ArrayCollection($sessionExercises);

        return $session;
    }

    public function testWeeklyMusclesAggregatesFinishedSessionsOfTheLastSevenDays(): void
    {
        $this->givenFinishedSession('2026-08-03 09:00:00', [[$this->bench->getId(), [[80.0, 8, false]]]]);
        // 10 days ago: outside the rolling window
        $this->givenFinishedSession('2026-07-25 09:00:00', [[$this->row->getId(), [[60.0, 10, false]]]]);

        $result = $this->handler->__invoke();

        $this->assertSame(['chest'], $result->primary);
        $this->assertSame(['triceps'], $result->secondary);
        $this->assertSame(1, $result->sessionCount);
    }

    public function testWeeklyMusclesQueriesTheSevenDayWindowFromTheClock(): void
    {
        $sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $sessionRepository
            ->expects($this->once())
            ->method('findFinishedSessionsBetween')
            ->with(new \DateTimeImmutable('2026-07-28 12:00:00'), null)
            ->willReturn(new ArrayCollection());
        $handler = new GetWeeklyMusclesHandler($sessionRepository, $this->exerciseRepository, $this->clock);

        $handler->__invoke();
    }

    public function testWeeklyMusclesDoesNotCountExercisesWithoutWorkingSets(): void
    {
        $this->givenFinishedSession('2026-08-03 09:00:00', [[$this->bench->getId(), [[60.0, 10, true]]]]);

        $result = $this->handler->__invoke();

        $this->assertSame([], $result->primary);
        $this->assertSame([], $result->secondary);
        $this->assertSame(0, $result->sessionCount);
    }

    public function testWeeklyMusclesDoesNotCountExercisesWithoutAnySets(): void
    {
        $this->givenFinishedSession('2026-08-03 09:00:00', [[$this->bench->getId(), []]]);

        $this->assertSame(0, $this->handler->__invoke()->sessionCount);
    }

    public function testWeeklyMusclesRemovesPrimaryMusclesFromSecondaryAcrossSessions(): void
    {
        $triceps = DomainTestHelper::createBuiltInExercise(
            name: 'Triceps Pushdown',
            primaryMuscles: ['triceps'],
            secondaryMuscles: [],
        );
        $this->catalog[$triceps->getId()->getValue()] = $triceps;

        $this->givenFinishedSession('2026-08-03 09:00:00', [[$this->bench->getId(), [[80.0, 8, false]]]]);
        $this->givenFinishedSession('2026-08-04 09:00:00', [[$triceps->getId(), [[30.0, 12, false]]]]);

        $result = $this->handler->__invoke();

        $this->assertSame(['chest', 'triceps'], $result->primary);
        $this->assertSame([], $result->secondary);
        $this->assertSame(2, $result->sessionCount);
    }

    public function testWeeklyMusclesSkipsOrphanedExerciseReferences(): void
    {
        $this->givenFinishedSession('2026-08-03 09:00:00', [
            [ExerciseId::generate(), [[80.0, 8, false]]],
            [$this->bench->getId(), [[80.0, 8, false]]],
        ]);

        $result = $this->handler->__invoke();

        $this->assertSame(['chest'], $result->primary);
        $this->assertSame(1, $result->sessionCount);
    }

    public function testWeeklyMusclesSerialisesSnakeCaseSessionCount(): void
    {
        $this->givenFinishedSession('2026-08-03 09:00:00', [[$this->bench->getId(), [[80.0, 8, false]]]]);

        $this->assertSame(
            ['primary' => ['chest'], 'secondary' => ['triceps'], 'session_count' => 1],
            $this->handler->__invoke()->toArray(),
        );
    }

    public function testWeeklyMusclesWithoutSessionsReturnsEmptyLists(): void
    {
        $result = $this->handler->__invoke();

        $this->assertSame([], $result->primary);
        $this->assertSame([], $result->secondary);
        $this->assertSame(0, $result->sessionCount);
    }
}
