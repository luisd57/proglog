<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\UpdateSessionNotesInputDTO;
use App\Application\Session\Handler\UpdateSessionNotesHandler;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateSessionNotesHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private UpdateSessionNotesHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new UpdateSessionNotesHandler($this->sessionRepository);
    }

    public function testUpdateNotesStoresNotesAndSaves(): void
    {
        $session = DomainTestHelper::createSession();

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->sessionRepository->expects($this->once())->method('save')->with($session);

        $this->handler->__invoke(new UpdateSessionNotesInputDTO(
            sessionId: $session->getId()->getValue(),
            notes: 'great pump',
        ));

        $this->assertSame('great pump', $session->getNotes());
    }

    public function testUpdateNotesAcceptsEmptyString(): void
    {
        $session = DomainTestHelper::createSession();
        $session->changeNotes('old');

        $this->sessionRepository->method('findById')->willReturn($session);

        $this->handler->__invoke(new UpdateSessionNotesInputDTO(
            sessionId: $session->getId()->getValue(),
            notes: '',
        ));

        $this->assertSame('', $session->getNotes());
    }

    public function testUpdateNotesForUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('save');

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(new UpdateSessionNotesInputDTO(
            sessionId: SessionId::generate()->getValue(),
            notes: 'x',
        ));
    }
}
