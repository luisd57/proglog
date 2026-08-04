<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Stats\Handler;

use App\Application\Stats\DTO\Input\GetExerciseBestInputDTO;
use App\Application\Stats\Handler\GetExerciseBestHandler;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetExerciseBestHandlerTest extends TestCase
{
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private GetExerciseBestHandler $handler;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new GetExerciseBestHandler($this->sessionRepository);
    }

    public function testBestReturnsTheHeaviestWeightAndTheHighestE1rm(): void
    {
        // the repository only returns working sets of finished sessions, so the
        // 60kg x 12 warmup (e1rm 84) never reaches the handler
        $this->sessionRepository->method('findFinishedWorkingSets')->willReturn(new ArrayCollection([
            DomainTestHelper::createSetLog(setNumber: 1, weightKg: 80.0, reps: 8),
            DomainTestHelper::createSetLog(setNumber: 2, weightKg: 85.0, reps: 3),
        ]));

        $result = $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: ExerciseId::generate()->getValue(),
            excludeSessionId: null,
        ));

        $this->assertSame(85.0, $result->bestWeightKg);
        $this->assertEqualsWithDelta(101.33, $result->bestE1rm, 0.01);
    }

    public function testBestWithoutHistoryReturnsNulls(): void
    {
        $this->sessionRepository->method('findFinishedWorkingSets')->willReturn(new ArrayCollection());

        $result = $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: ExerciseId::generate()->getValue(),
            excludeSessionId: null,
        ));

        $this->assertNull($result->bestWeightKg);
        $this->assertNull($result->bestE1rm);
        $this->assertSame(
            ['best_weight_kg' => null, 'best_e1rm' => null],
            $result->toArray(),
        );
    }

    public function testBestPassesTheExerciseAndExcludedSessionToTheRepository(): void
    {
        $exerciseId = ExerciseId::generate();
        $excludedSessionId = SessionId::generate();

        $this->sessionRepository
            ->expects($this->once())
            ->method('findFinishedWorkingSets')
            ->with(
                $this->callback(fn (ExerciseId $actual) => $actual->equals($exerciseId)),
                $this->callback(fn (SessionId $actual) => $actual->equals($excludedSessionId)),
            )
            ->willReturn(new ArrayCollection());

        $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: $exerciseId->getValue(),
            excludeSessionId: $excludedSessionId->getValue(),
        ));
    }

    public function testBestWithoutExcludeSessionPassesNullToTheRepository(): void
    {
        $this->sessionRepository
            ->expects($this->once())
            ->method('findFinishedWorkingSets')
            ->with($this->anything(), null)
            ->willReturn(new ArrayCollection());

        $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: ExerciseId::generate()->getValue(),
            excludeSessionId: null,
        ));
    }

    public function testBestWithMalformedExerciseIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: 'nope',
            excludeSessionId: null,
        ));
    }

    public function testBestWithMalformedExcludeSessionIdThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new GetExerciseBestInputDTO(
            exerciseId: ExerciseId::generate()->getValue(),
            excludeSessionId: 'nope',
        ));
    }
}
