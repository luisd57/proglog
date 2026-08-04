<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\Handler\DeleteSessionHandler;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteSessionHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private DeleteSessionHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new DeleteSessionHandler($this->sessionRepository);
    }

    public function testDeleteDelegatesToRepository(): void
    {
        $session = DomainTestHelper::createSession();

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->sessionRepository->expects($this->once())->method('delete')->with($session);

        $this->handler->__invoke($session->getId()->getValue());
    }

    public function testDeleteUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('delete');

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(SessionId::generate()->getValue());
    }

    public function testDeleteWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
