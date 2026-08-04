<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Session\Handler;

use App\Application\Session\DTO\Input\AddSessionExerciseInputDTO;
use App\Application\Session\Handler\AddSessionExerciseHandler;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddSessionExerciseHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private WorkoutTemplateRepositoryInterface&MockObject $workoutTemplateRepository;
    private AddSessionExerciseHandler $handler;

    private Session $session;
    private ExerciseId $ohpId;
    private ?SessionExercise $savedExercise = null;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->workoutTemplateRepository = $this->createMock(WorkoutTemplateRepositoryInterface::class);
        $this->handler = new AddSessionExerciseHandler(
            $this->sessionRepository,
            $this->exerciseRepository,
            new SessionAssembler(
                $this->sessionRepository,
                $this->workoutTemplateRepository,
                $this->exerciseRepository,
                $this->createMock(ProfileRepositoryInterface::class),
            ),
        );

        $this->session = DomainTestHelper::createSession();
        $this->ohpId = ExerciseId::generate();

        $this->sessionRepository
            ->method('saveExercise')
            ->willReturnCallback(function (SessionExercise $sessionExercise): void {
                $this->savedExercise = $sessionExercise;
            });

        $this->sessionRepository
            ->method('findExercisesBySessionId')
            ->willReturnCallback(
                fn (): ArrayCollection => $this->savedExercise !== null
                    ? new ArrayCollection([$this->savedExercise])
                    : new ArrayCollection()
            );

        $this->sessionRepository->method('findSetsBySessionExerciseId')->willReturn(new ArrayCollection());
        $this->sessionRepository->method('findLatestFinishedExercise')->willReturn(null);
    }

    public function testAddExerciseAppendsWithNextSortOrder(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->sessionRepository->method('countExercisesBySessionId')->willReturn(2);
        $this->exerciseRepository
            ->method('findById')
            ->willReturn(DomainTestHelper::createBuiltInExercise(id: $this->ohpId, name: 'Overhead Press'));

        $result = $this->handler->__invoke(new AddSessionExerciseInputDTO(
            sessionId: $this->session->getId()->getValue(),
            exerciseId: $this->ohpId->getValue(),
        ));

        $this->assertNotNull($this->savedExercise);
        $this->assertSame(2, $this->savedExercise->getSortOrder());
        $this->assertTrue($this->savedExercise->getExerciseId()->equals($this->ohpId));
        $this->assertTrue($this->savedExercise->getSessionId()->equals($this->session->getId()));
        $this->assertSame(
            ['Overhead Press'],
            array_map(fn ($sessionExerciseDto) => $sessionExerciseDto->exercise->name, $result->exercises),
        );
    }

    public function testAddExerciseToUnknownSessionThrowsSessionNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('saveExercise');

        $this->expectException(SessionNotFoundException::class);

        $this->handler->__invoke(new AddSessionExerciseInputDTO(
            sessionId: SessionId::generate()->getValue(),
            exerciseId: $this->ohpId->getValue(),
        ));
    }

    public function testAddUnknownExerciseThrowsExerciseNotFoundException(): void
    {
        $this->sessionRepository->method('findById')->willReturn($this->session);
        $this->exerciseRepository->method('findById')->willReturn(null);
        $this->sessionRepository->expects($this->never())->method('saveExercise');

        $this->expectException(ExerciseNotFoundException::class);

        $this->handler->__invoke(new AddSessionExerciseInputDTO(
            sessionId: $this->session->getId()->getValue(),
            exerciseId: ExerciseId::generate()->getValue(),
        ));
    }
}
