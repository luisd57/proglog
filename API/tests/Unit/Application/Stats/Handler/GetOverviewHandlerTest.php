<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Stats\Handler;

use App\Application\Stats\Handler\GetOverviewHandler;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\ValueObject\CumulativeVolumePoint;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GetOverviewHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private MockClock $clock;
    private GetOverviewHandler $handler;

    /** @var array<int, Session> */
    private array $sessions = [];

    /** @var array<string, ArrayCollection> */
    private array $exercisesBySessionId = [];

    /** @var array<string, ArrayCollection> */
    private array $setsBySessionExerciseId = [];

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-08-04 12:00:00'));
        $this->handler = new GetOverviewHandler($this->sessionRepository, $this->clock);

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
    }

    /**
     * @param array<int, array{0: float, 1: int, 2: bool}> $sets
     */
    private function givenFinishedSession(string $startedAt, string $finishedAt, array $sets): Session
    {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable($startedAt),
            finishedAt: new \DateTimeImmutable($finishedAt),
        );
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $session->getId());

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
        $this->exercisesBySessionId[$session->getId()->getValue()] = new ArrayCollection([$sessionExercise]);
        $this->setsBySessionExerciseId[$sessionExercise->getId()->getValue()] = new ArrayCollection($setLogs);

        return $session;
    }

    public function testOverviewTotalsWorkingSetsAndExcludesWarmups(): void
    {
        $this->givenFinishedSession('2026-08-04 09:00:00', '2026-08-04 10:00:00', [
            [60.0, 10, true],
            [80.0, 8, false],
            [100.0, 5, false],
        ]);

        $result = $this->handler->__invoke('7d');

        $this->assertSame('7d', $result->period);
        $this->assertSame(1, $result->current->workouts);
        $this->assertSame(80.0 * 8 + 100.0 * 5, $result->current->volumeKg);
        $this->assertSame(13, $result->current->reps);
        $this->assertSame(2, $result->current->sets);
        $this->assertSame(100.0, $result->current->heaviestKg);
    }

    public function testOverviewSeparatesThePrecedingEqualLengthWindowIntoPrevious(): void
    {
        $this->givenFinishedSession('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[100.0, 5, false]]);
        $this->givenFinishedSession('2026-07-25 09:00:00', '2026-07-25 10:00:00', [[50.0, 10, false]]);
        // older than the previous window: never fetched
        $this->givenFinishedSession('2026-07-15 09:00:00', '2026-07-15 10:00:00', [[40.0, 10, false]]);

        $result = $this->handler->__invoke('7d');

        $this->assertSame(1, $result->current->workouts);
        $this->assertSame(500.0, $result->current->volumeKg);
        $this->assertNotNull($result->previous);
        $this->assertSame(1, $result->previous->workouts);
        $this->assertSame(500.0, $result->previous->volumeKg);
    }

    public function testOverviewForAllTimeReturnsNullPreviousAndIncludesEverySession(): void
    {
        $this->givenFinishedSession('2026-08-04 09:00:00', '2026-08-04 10:00:00', [[100.0, 5, false]]);
        $this->givenFinishedSession('2026-04-26 09:00:00', '2026-04-26 10:00:00', [[40.0, 10, false]]);

        $result = $this->handler->__invoke('all');

        $this->assertSame('all', $result->period);
        $this->assertNull($result->previous);
        $this->assertSame(2, $result->current->workouts);
        $this->assertSame(100.0 * 5 + 40.0 * 10, $result->current->volumeKg);
    }

    public function testOverviewForAllTimeFetchesWithoutALowerBound(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->sessionRepository
            ->expects($this->once())
            ->method('findFinishedSessionsBetween')
            ->with(null, null)
            ->willReturn(new ArrayCollection());
        $handler = new GetOverviewHandler($this->sessionRepository, $this->clock);

        $handler->__invoke('all');
    }

    public function testOverviewFetchesTwoWindowsWorthOfSessions(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->sessionRepository
            ->expects($this->once())
            ->method('findFinishedSessionsBetween')
            ->with(new \DateTimeImmutable('2026-07-21 12:00:00'), null)
            ->willReturn(new ArrayCollection());
        $handler = new GetOverviewHandler($this->sessionRepository, $this->clock);

        $handler->__invoke('7d');
    }

    public function testOverviewWithAnUnknownPeriodFallsBackToSevenDays(): void
    {
        $result = $this->handler->__invoke('nonsense');

        $this->assertSame('7d', $result->period);
        $this->assertNotNull($result->previous);
    }

    public function testOverviewAcceptsEveryDocumentedPeriod(): void
    {
        foreach (['7d', '30d', '90d', '365d'] as $period) {
            $this->assertSame($period, $this->handler->__invoke($period)->period);
        }
    }

    public function testOverviewSumsSessionDurationsIntoTimeSeconds(): void
    {
        $this->givenFinishedSession('2026-08-04 09:00:00', '2026-08-04 09:30:00', [[100.0, 5, false]]);

        $this->assertSame(30 * 60, $this->handler->__invoke('7d')->current->timeSeconds);
    }

    public function testOverviewCumulativeVolumeCoversTheWindowAndEndsAtTheCurrentVolume(): void
    {
        $this->givenFinishedSession('2026-08-02 09:00:00', '2026-08-02 10:00:00', [[100.0, 5, false]]);

        $result = $this->handler->__invoke('7d');

        $dates = array_map(
            fn (CumulativeVolumePoint $point) => $point->date,
            $result->cumulativeVolume,
        );
        $values = array_map(
            fn (CumulativeVolumePoint $point) => $point->value,
            $result->cumulativeVolume,
        );

        $this->assertSame('2026-07-28', $dates[0]);
        $this->assertSame('2026-08-04', $dates[count($dates) - 1]);
        $this->assertCount(8, $result->cumulativeVolume);
        $this->assertSame($result->current->volumeKg, $values[count($values) - 1]);
    }

    public function testOverviewSerialisesSnakeCaseTotals(): void
    {
        $this->givenFinishedSession('2026-08-04 09:00:00', '2026-08-04 09:30:00', [[100.0, 5, false]]);

        $payload = $this->handler->__invoke('7d')->toArray();

        $this->assertSame('7d', $payload['period']);
        $this->assertSame(
            ['workouts', 'volume_kg', 'reps', 'sets', 'heaviest_kg', 'time_seconds'],
            array_keys($payload['current']),
        );
        $this->assertSame(500.0, $payload['current']['volume_kg']);
        $this->assertSame(1800, $payload['current']['time_seconds']);
        $this->assertSame(['date', 'value'], array_keys($payload['cumulative_volume'][0]));
    }

    public function testOverviewWithoutSessionsReturnsZeroTotals(): void
    {
        $result = $this->handler->__invoke('7d');

        $this->assertSame(0, $result->current->workouts);
        $this->assertSame(0.0, $result->current->volumeKg);
        $this->assertSame(0.0, $result->current->heaviestKg);
        $this->assertNotNull($result->previous);
        $this->assertSame(0, $result->previous->workouts);
    }
}
