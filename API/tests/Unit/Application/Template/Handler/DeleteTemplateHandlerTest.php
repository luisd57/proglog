<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Template\Handler;

use App\Application\Template\Handler\DeleteTemplateHandler;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteTemplateHandlerTest extends TestCase
{
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private DeleteTemplateHandler $handler;

    protected function setUp(): void
    {
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new DeleteTemplateHandler(
            $this->workoutTemplateRepository,
            $this->sessionRepository,
        );
    }

    public function testDeleteDetachesReferencingSessionsThenDeletesTemplate(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();
        $session = DomainTestHelper::createSession(workoutTemplateId: $workoutTemplate->getId());

        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);
        $this->sessionRepository
            ->method('findByTemplateId')
            ->with($workoutTemplate->getId())
            ->willReturn(new ArrayCollection([$session]));

        $savedSessions = [];
        $this->sessionRepository
            ->method('save')
            ->willReturnCallback(function (Session $savedSession) use (&$savedSessions): void {
                $savedSessions[] = $savedSession;
            });

        $this->workoutTemplateRepository
            ->expects($this->once())
            ->method('delete')
            ->with($workoutTemplate);

        $this->handler->__invoke($workoutTemplate->getId()->getValue());

        $this->assertCount(1, $savedSessions);
        $this->assertNull($savedSessions[0]->getTemplateId());
    }

    public function testDeleteWithoutReferencingSessionsOnlyDeletesTemplate(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();

        $this->workoutTemplateRepository->method('findById')->willReturn($workoutTemplate);
        $this->sessionRepository->method('findByTemplateId')->willReturn(new ArrayCollection());
        $this->sessionRepository->expects($this->never())->method('save');
        $this->workoutTemplateRepository->expects($this->once())->method('delete');

        $this->handler->__invoke($workoutTemplate->getId()->getValue());
    }

    public function testDeleteUnknownTemplateThrowsTemplateNotFoundException(): void
    {
        $this->workoutTemplateRepository->method('findById')->willReturn(null);
        $this->workoutTemplateRepository->expects($this->never())->method('delete');

        $this->expectException(TemplateNotFoundException::class);

        $this->handler->__invoke('0198c5b6-0000-7000-8000-000000000000');
    }

    public function testDeleteWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
