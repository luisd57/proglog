<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\ReplaceSetsInputDTO;
use App\Application\Session\DTO\Input\SetLineInputDTO;
use App\Application\Session\Handler\ReplaceSetsHandler;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReplaceSetsHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ReplaceSetsHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new ReplaceSetsHandler($this->sessionRepository);
    }

    public function testReplaceSetsNumbersSetsFromArrayIndexWithDefaults(): void
    {
        $sessionId = SessionId::generate();
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $sessionId);

        $this->sessionRepository->method('findExerciseById')->willReturn($sessionExercise);

        $replacedSets = null;
        $this->sessionRepository
            ->expects($this->once())
            ->method('replaceSets')
            ->willReturnCallback(function ($sessionExerciseId, ArrayCollection $setLogs) use (&$replacedSets): void {
                $replacedSets = $setLogs;
            });

        $this->handler->__invoke(new ReplaceSetsInputDTO(
            sessionId: $sessionId->getValue(),
            sessionExerciseId: $sessionExercise->getId()->getValue(),
            sets: [
                new SetLineInputDTO(weightKg: 60.0, reps: 10, isWarmup: true),
                new SetLineInputDTO(weightKg: 80.0, reps: 8, notes: 'felt heavy'),
                new SetLineInputDTO(weightKg: 82.5, reps: 6),
            ],
        ));

        $this->assertNotNull($replacedSets);
        $this->assertSame(
            [1, 2, 3],
            $replacedSets->map(fn (SetLog $setLog) => $setLog->getSetNumber())->toArray(),
        );
        $this->assertSame(
            [60.0, 80.0, 82.5],
            $replacedSets->map(fn (SetLog $setLog) => $setLog->getWeightKg())->toArray(),
        );
        $this->assertTrue($replacedSets->get(0)->isWarmup());
        $this->assertFalse($replacedSets->get(1)->isWarmup());
        $this->assertSame('felt heavy', $replacedSets->get(1)->getNotes());
        $this->assertNull($replacedSets->get(2)->getNotes());
        $this->assertTrue(
            $replacedSets->get(0)->getSessionExerciseId()->equals($sessionExercise->getId())
        );
    }

    public function testReplaceSetsWithEmptyListClearsSets(): void
    {
        $sessionId = SessionId::generate();
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $sessionId);

        $this->sessionRepository->method('findExerciseById')->willReturn($sessionExercise);

        $replacedSets = null;
        $this->sessionRepository
            ->expects($this->once())
            ->method('replaceSets')
            ->willReturnCallback(function ($sessionExerciseId, ArrayCollection $setLogs) use (&$replacedSets): void {
                $replacedSets = $setLogs;
            });

        $this->handler->__invoke(new ReplaceSetsInputDTO(
            sessionId: $sessionId->getValue(),
            sessionExerciseId: $sessionExercise->getId()->getValue(),
            sets: [],
        ));

        $this->assertNotNull($replacedSets);
        $this->assertTrue($replacedSets->isEmpty());
    }

    public function testReplaceSetsForExerciseOfAnotherSessionThrowsNotFound(): void
    {
        // exercise belongs to a different session than the one in the path
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: SessionId::generate());

        $this->sessionRepository->method('findExerciseById')->willReturn($sessionExercise);
        $this->sessionRepository->expects($this->never())->method('replaceSets');

        $this->expectException(SessionExerciseNotFoundException::class);

        $this->handler->__invoke(new ReplaceSetsInputDTO(
            sessionId: SessionId::generate()->getValue(),
            sessionExerciseId: $sessionExercise->getId()->getValue(),
            sets: [],
        ));
    }

    public function testReplaceSetsForUnknownSessionExerciseThrowsNotFound(): void
    {
        $this->sessionRepository->method('findExerciseById')->willReturn(null);

        $this->expectException(SessionExerciseNotFoundException::class);

        $this->handler->__invoke(new ReplaceSetsInputDTO(
            sessionId: SessionId::generate()->getValue(),
            sessionExerciseId: '0198c5b6-0000-7000-8000-000000000000',
            sets: [],
        ));
    }
}
