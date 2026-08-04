<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Stats\Handler;

use App\Application\Stats\Handler\GetExerciseSeriesHandler;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetExerciseSeriesHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private GetExerciseSeriesHandler $handler;
    private ExerciseId $benchId;

    /** @var array<int, Session> */
    private array $sessions = [];

    /** @var array<int, SessionExercise> */
    private array $sessionExercises = [];

    /** @var array<string, ArrayCollection> */
    private array $setsBySessionExerciseId = [];

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new GetExerciseSeriesHandler($this->sessionRepository);
        $this->benchId = ExerciseId::generate();

        $this->sessionRepository
            ->method('findFinishedSessionsByExerciseId')
            ->willReturnCallback(fn (): ArrayCollection => new ArrayCollection($this->sessions));
        $this->sessionRepository
            ->method('findFinishedExercisesByExerciseId')
            ->willReturnCallback(fn (): ArrayCollection => new ArrayCollection($this->sessionExercises));
        $this->sessionRepository
            ->method('findSetsBySessionExerciseId')
            ->willReturnCallback(
                fn (SessionExerciseId $sessionExerciseId): ArrayCollection => $this->setsBySessionExerciseId[$sessionExerciseId->getValue()]
                    ?? new ArrayCollection()
            );
    }

    /**
     * @param array<int, array{0: float, 1: int, 2: bool}> $sets
     */
    private function givenFinishedSession(string $startedAt, array $sets): Session
    {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable($startedAt),
            finishedAt: new \DateTimeImmutable($startedAt),
        );
        $sessionExercise = DomainTestHelper::createSessionExercise(
            sessionId: $session->getId(),
            exerciseId: $this->benchId,
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

        $this->sessions[] = $session;
        $this->sessionExercises[] = $sessionExercise;
        $this->setsBySessionExerciseId[$sessionExercise->getId()->getValue()] = new ArrayCollection($setLogs);

        return $session;
    }

    public function testSeriesReturnsOnePointPerFinishedSessionExcludingWarmupSets(): void
    {
        $first = $this->givenFinishedSession('2026-08-01 10:00:00', [
            [60.0, 10, true],
            [80.0, 8, false],
            [80.0, 6, false],
        ]);
        $second = $this->givenFinishedSession('2026-08-04 10:00:00', [[82.5, 8, false]]);

        $result = $this->handler->__invoke($this->benchId->getValue());

        $this->assertCount(2, $result->points);

        $this->assertSame($first->getId()->getValue(), $result->points[0]->sessionId);
        // the warmup is excluded from volume, top set and e1rm
        $this->assertSame(80.0 * 8 + 80.0 * 6, $result->points[0]->volume);
        $this->assertSame(80.0, $result->points[0]->topSetWeightKg);
        $this->assertSame(8, $result->points[0]->topSetReps);
        $this->assertEqualsWithDelta(101.33, $result->points[0]->e1rm, 0.01);

        $this->assertSame($second->getId()->getValue(), $result->points[1]->sessionId);
        $this->assertSame(82.5 * 8, $result->points[1]->volume);
        $this->assertEqualsWithDelta(104.5, $result->points[1]->e1rm, 0.01);

        $this->assertLessThan($result->points[1]->date, $result->points[0]->date);
    }

    public function testSeriesSkipsSessionsWithOnlyWarmupsAndReturnsPrsChronologically(): void
    {
        $this->givenFinishedSession('2026-08-01 10:00:00', [[80.0, 8, false]]);  // baseline = PR
        $this->givenFinishedSession('2026-08-02 10:00:00', [[60.0, 10, true]]);  // warmup only
        $this->givenFinishedSession('2026-08-03 10:00:00', [[70.0, 5, false]]);  // no PR
        $this->givenFinishedSession('2026-08-04 10:00:00', [[85.0, 8, false]]);  // weight + e1rm PR

        $result = $this->handler->__invoke($this->benchId->getValue());

        $this->assertCount(3, $result->points);
        $this->assertCount(2, $result->prs);
        $this->assertSame(80.0, $result->prs[0]->weightKg);
        $this->assertSame(8, $result->prs[0]->reps);
        $this->assertSame(85.0, $result->prs[1]->weightKg);
        $this->assertSame(8, $result->prs[1]->reps);
    }

    public function testSeriesWithoutHistoryReturnsAnEmptySeries(): void
    {
        $result = $this->handler->__invoke($this->benchId->getValue());

        $this->assertSame([], $result->points);
        $this->assertSame([], $result->prs);
        $this->assertSame(['points' => [], 'prs' => []], $result->toArray());
    }

    public function testSeriesSkipsEntriesWhoseSessionIsMissing(): void
    {
        $this->givenFinishedSession('2026-08-01 10:00:00', [[80.0, 8, false]]);
        // an orphaned entry: no matching session was returned
        $this->sessionExercises[] = DomainTestHelper::createSessionExercise(exerciseId: $this->benchId);

        $result = $this->handler->__invoke($this->benchId->getValue());

        $this->assertCount(1, $result->points);
    }

    public function testSeriesSerialisesSnakeCasePayloadWithAtomDates(): void
    {
        $session = $this->givenFinishedSession('2026-08-04 10:00:00', [[100.0, 5, false]]);

        $payload = $this->handler->__invoke($this->benchId->getValue())->toArray();

        $this->assertSame(
            $session->getId()->getValue(),
            $payload['points'][0]['session_id'],
        );
        $this->assertSame(
            $session->getStartedAt()->format(\DateTimeInterface::ATOM),
            $payload['points'][0]['date'],
        );
        $this->assertSame(['weight_kg' => 100.0, 'reps' => 5], $payload['points'][0]['top_set']);
        $this->assertSame(500.0, $payload['points'][0]['volume']);
        $this->assertEqualsWithDelta(116.67, $payload['points'][0]['e1rm'], 0.01);
        $this->assertSame(100.0, $payload['prs'][0]['weight_kg']);
        $this->assertSame(5, $payload['prs'][0]['reps']);
    }

    public function testSeriesWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
