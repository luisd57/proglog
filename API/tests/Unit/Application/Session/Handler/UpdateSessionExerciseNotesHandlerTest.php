<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\UpdateSessionExerciseNotesInputDTO;
use App\Application\Session\Handler\UpdateSessionExerciseNotesHandler;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateSessionExerciseNotesHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private UpdateSessionExerciseNotesHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new UpdateSessionExerciseNotesHandler($this->sessionRepository);
    }

    public function testUpdateExerciseNotesStoresNotesAndSaves(): void
    {
        $sessionId = SessionId::generate();
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $sessionId);

        $this->sessionRepository->method('findExerciseById')->willReturn($sessionExercise);
        $this->sessionRepository
            ->expects($this->once())
            ->method('saveExercise')
            ->with($sessionExercise);

        $this->handler->__invoke(new UpdateSessionExerciseNotesInputDTO(
            sessionId: $sessionId->getValue(),
            sessionExerciseId: $sessionExercise->getId()->getValue(),
            notes: 'slow eccentric',
        ));

        $this->assertSame('slow eccentric', $sessionExercise->getNotes());
    }

    public function testUpdateExerciseNotesForExerciseOfAnotherSessionThrowsNotFound(): void
    {
        $foreignExercise = DomainTestHelper::createSessionExercise(sessionId: SessionId::generate());

        $this->sessionRepository->method('findExerciseById')->willReturn($foreignExercise);
        $this->sessionRepository->expects($this->never())->method('saveExercise');

        $this->expectException(SessionExerciseNotFoundException::class);

        $this->handler->__invoke(new UpdateSessionExerciseNotesInputDTO(
            sessionId: SessionId::generate()->getValue(),
            sessionExerciseId: $foreignExercise->getId()->getValue(),
            notes: 'x',
        ));
    }

    public function testUpdateExerciseNotesForUnknownExerciseThrowsNotFound(): void
    {
        $this->sessionRepository->method('findExerciseById')->willReturn(null);

        $this->expectException(SessionExerciseNotFoundException::class);

        $this->handler->__invoke(new UpdateSessionExerciseNotesInputDTO(
            sessionId: SessionId::generate()->getValue(),
            sessionExerciseId: '0198c5b6-0000-7000-8000-000000000000',
            notes: 'x',
        ));
    }
}
