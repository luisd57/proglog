<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\Handler\FinishSessionHandler;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class FinishSessionHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private FinishSessionHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new FinishSessionHandler(
            $this->sessionRepository,
            new SessionAssembler(
                $this->sessionRepository,
                $workoutTemplateRepository,
                $exerciseRepository,
                $this->createMock(ProfileRepositoryInterface::class),
            ),
            new MockClock(new \DateTimeImmutable('2026-08-04 11:30:00')),
        );

        $this->sessionRepository->method('findExercisesBySessionId')->willReturn(new ArrayCollection());
    }

    public function testFinishSetsFinishedAtToClockNowAndSaves(): void
    {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->sessionRepository->expects($this->once())->method('save')->with($session);

        $result = $this->handler->__invoke($session->getId()->getValue());

        $this->assertEquals(new \DateTimeImmutable('2026-08-04 11:30:00'), $session->getFinishedAt());
        $this->assertEquals(new \DateTimeImmutable('2026-08-04 11:30:00'), $result->finishedAt);
    }

    public function testFinishOverwritesFinishedAtOnAlreadyFinishedSession(): void
    {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-04 09:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-04 09:30:00'),
        );

        $this->sessionRepository->method('findById')->willReturn($session);

        $result = $this->handler->__invoke($session->getId()->getValue());

        $this->assertEquals(new \DateTimeImmutable('2026-08-04 11:30:00'), $result->finishedAt);
    }

    public function testFinishUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('save');

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(SessionId::generate()->getValue());
    }

    public function testFinishWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
