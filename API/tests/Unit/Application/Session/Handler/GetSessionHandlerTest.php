<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\Handler\GetSessionHandler;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Id\ExerciseId;
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

final class GetSessionHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private ProfileRepositoryInterface&MockObject $profileRepository;
    private GetSessionHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->profileRepository = $this->createMock(ProfileRepositoryInterface::class);
        $workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new GetSessionHandler(
            $this->sessionRepository,
            new SessionAssembler(
                $this->sessionRepository,
                $workoutTemplateRepository,
                $this->exerciseRepository,
                $this->profileRepository,
            ),
        );
    }

    public function testGetReturnsSessionWithComposedExercisesAndSets(): void
    {
        $session = DomainTestHelper::createSession();
        $session->changeNotes('good day');
        $benchId = ExerciseId::generate();
        $sessionExercise = DomainTestHelper::createSessionExercise(
            sessionId: $session->getId(),
            exerciseId: $benchId,
        );

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->sessionRepository
            ->method('findExercisesBySessionId')
            ->willReturn(new ArrayCollection([$sessionExercise]));
        $this->sessionRepository
            ->method('findSetsBySessionExerciseId')
            ->willReturn(new ArrayCollection([
                DomainTestHelper::createSetLog(
                    sessionExerciseId: $sessionExercise->getId(),
                    setNumber: 1,
                    weightKg: 80.0,
                    reps: 8,
                ),
            ]));
        $this->sessionRepository->method('findLatestFinishedExercise')->willReturn(null);
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise(id: $benchId, name: 'Bench Press'));

        $result = $this->handler->__invoke($session->getId()->getValue());

        $this->assertSame('good day', $result->notes);
        $this->assertCount(1, $result->exercises);
        $this->assertSame('Bench Press', $result->exercises[0]->exercise->name);
        $this->assertSame(1, $result->exercises[0]->sets[0]->setNumber);
        $this->assertSame(80.0, $result->exercises[0]->sets[0]->weightKg);
        // no template, no profile row -> ultimate default rest
        $this->assertSame(120, $result->exercises[0]->restSeconds);
    }

    public function testGetUsesProfileDefaultRestSecondsAsFallback(): void
    {
        $session = DomainTestHelper::createSession();
        $benchId = ExerciseId::generate();
        $sessionExercise = DomainTestHelper::createSessionExercise(
            sessionId: $session->getId(),
            exerciseId: $benchId,
        );

        $this->sessionRepository->method('findById')->willReturn($session);
        $this->sessionRepository
            ->method('findExercisesBySessionId')
            ->willReturn(new ArrayCollection([$sessionExercise]));
        $this->sessionRepository
            ->method('findSetsBySessionExerciseId')
            ->willReturn(new ArrayCollection());
        $this->sessionRepository->method('findLatestFinishedExercise')->willReturn(null);
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise(id: $benchId, name: 'Bench Press'));
        $this->profileRepository
            ->method('find')
            ->willReturn(DomainTestHelper::createProfile(defaultRestSeconds: 90));

        $result = $this->handler->__invoke($session->getId()->getValue());

        $this->assertSame(90, $result->exercises[0]->restSeconds);
    }

    public function testGetUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(SessionId::generate()->getValue());
    }

    public function testGetWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
