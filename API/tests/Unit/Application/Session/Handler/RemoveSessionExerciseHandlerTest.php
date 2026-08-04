<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\RemoveSessionExerciseInputDTO;
use App\Application\Session\Handler\RemoveSessionExerciseHandler;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RemoveSessionExerciseHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private RemoveSessionExerciseHandler $handler;

    private Session $session;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new RemoveSessionExerciseHandler(
            $this->sessionRepository,
            new SessionAssembler(
                $this->sessionRepository,
                $workoutTemplateRepository,
                $exerciseRepository,
                $this->createMock(ProfileRepositoryInterface::class),
            ),
        );

        $this->session = DomainTestHelper::createSession();
    }

    public function testRemoveExerciseDeletesItAndReturnsUpdatedSession(): void
    {
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $this->session->getId());

        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->sessionRepository->method('findExerciseById')->willReturn($sessionExercise);
        $this->sessionRepository->method('findExercisesBySessionId')->willReturn(new ArrayCollection());
        $this->sessionRepository
            ->expects($this->once())
            ->method('deleteExercise')
            ->with($sessionExercise);

        $result = $this->handler->__invoke(new RemoveSessionExerciseInputDTO(
            sessionId: $this->session->getId()->getValue(),
            sessionExerciseId: $sessionExercise->getId()->getValue(),
        ));

        $this->assertSame([], $result->exercises);
    }

    public function testRemoveExerciseOfAnotherSessionThrowsNotFound(): void
    {
        $foreignExercise = DomainTestHelper::createSessionExercise(sessionId: SessionId::generate());

        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->sessionRepository->method('findExerciseById')->willReturn($foreignExercise);
        $this->sessionRepository->expects($this->never())->method('deleteExercise');

        $this->expectException(SessionExerciseNotFoundException::class);

        $this->handler->__invoke(new RemoveSessionExerciseInputDTO(
            sessionId: $this->session->getId()->getValue(),
            sessionExerciseId: $foreignExercise->getId()->getValue(),
        ));
    }

    public function testRemoveExerciseFromUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(new RemoveSessionExerciseInputDTO(
            sessionId: SessionId::generate()->getValue(),
            sessionExerciseId: '0198c5b6-0000-7000-8000-000000000000',
        ));
    }
}
