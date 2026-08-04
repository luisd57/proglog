<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\Handler\ListSessionsHandler;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListSessionsHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private ListSessionsHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new ListSessionsHandler(
            $this->sessionRepository,
            $this->workoutTemplateRepository,
        );
    }

    public function testListMapsSummariesWithTemplateNamesAndCounts(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Push Day');
        $newerSession = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );
        $olderSession = DomainTestHelper::createSession(
            workoutTemplateId: $workoutTemplate->getId(),
            startedAt: new \DateTimeImmutable('2026-08-03 10:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-03 11:00:00'),
        );

        // repository returns newest first
        $this->sessionRepository
            ->method('findAll')
            ->willReturn(new ArrayCollection([$newerSession, $olderSession]));
        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);

        $exerciseCounts = [
            $newerSession->getId()->getValue() => 2,
            $olderSession->getId()->getValue() => 3,
        ];
        $setCounts = [
            $newerSession->getId()->getValue() => 0,
            $olderSession->getId()->getValue() => 9,
        ];
        $this->sessionRepository
            ->method('countExercisesBySessionId')
            ->willReturnCallback(
                fn (SessionId $sessionId): int => $exerciseCounts[$sessionId->getValue()]
            );
        $this->sessionRepository
            ->method('countSetsBySessionId')
            ->willReturnCallback(
                fn (SessionId $sessionId): int => $setCounts[$sessionId->getValue()]
            );

        $result = $this->handler->__invoke();

        $this->assertCount(2, $result);

        $this->assertSame($newerSession->getId()->getValue(), $result->get(0)->id);
        $this->assertNull($result->get(0)->templateName);
        $this->assertNull($result->get(0)->finishedAt);
        $this->assertSame(2, $result->get(0)->exerciseCount);
        $this->assertSame(0, $result->get(0)->setCount);

        $this->assertSame($olderSession->getId()->getValue(), $result->get(1)->id);
        $this->assertSame('Push Day', $result->get(1)->templateName);
        $this->assertNotNull($result->get(1)->finishedAt);
        $this->assertSame(3, $result->get(1)->exerciseCount);
        $this->assertSame(9, $result->get(1)->setCount);
    }

    public function testListWithDeletedTemplateReferenceReturnsNullTemplateName(): void
    {
        $session = DomainTestHelper::createSession(
            workoutTemplateId: DomainTestHelper::createWorkoutTemplate()->getId(),
        );

        $this->sessionRepository->method('findAll')->willReturn(new ArrayCollection([$session]));
        $this->workoutTemplateRepository->method('findById')->willReturn(null);
        $this->sessionRepository->method('countExercisesBySessionId')->willReturn(0);
        $this->sessionRepository->method('countSetsBySessionId')->willReturn(0);

        $result = $this->handler->__invoke();

        $this->assertNull($result->get(0)->templateName);
    }

    public function testListWithNoSessionsReturnsEmptyCollection(): void
    {
        $this->sessionRepository->method('findAll')->willReturn(new ArrayCollection());

        $this->assertCount(0, $this->handler->__invoke());
    }
}
