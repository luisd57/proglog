<?php

declare(strict_types=1);

namespace App\Application\Stats\Handler;

use App\Application\Stats\DTO\Output\ExerciseSeriesOutputDTO;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\Service\ExerciseSeriesCalculator;
use App\Domain\Stats\ValueObject\LoggedSet;
use App\Domain\Stats\ValueObject\SessionSets;

/**
 * Progress series of one exercise over its finished sessions. Composes the
 * per-session working sets and delegates points/PR detection to the domain
 * calculator.
 */
final readonly class GetExerciseSeriesHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $id): ExerciseSeriesOutputDTO
    {
        $exerciseId = ExerciseId::fromString($id);

        /** @var array<string, Session> $sessionsById */
        $sessionsById = [];

        foreach ($this->sessionRepository->findFinishedSessionsByExerciseId($exerciseId) as $session) {
            $sessionsById[$session->getId()->getValue()] = $session;
        }

        $sessionSetsHistory = [];

        foreach ($this->sessionRepository->findFinishedExercisesByExerciseId($exerciseId) as $sessionExercise) {
            $session = $sessionsById[$sessionExercise->getSessionId()->getValue()] ?? null;

            if ($session === null) {
                continue;
            }

            $sessionSetsHistory[] = new SessionSets(
                sessionId: $session->getId()->getValue(),
                startedAt: $session->getStartedAt(),
                sets: $this->workingSetsOf($sessionExercise->getId()),
            );
        }

        return ExerciseSeriesOutputDTO::fromResult(ExerciseSeriesCalculator::calculate($sessionSetsHistory));
    }

    /**
     * @return array<int, LoggedSet>
     */
    private function workingSetsOf(SessionExerciseId $sessionExerciseId): array
    {
        $workingSets = [];

        /** @var SetLog $setLog */
        foreach ($this->sessionRepository->findSetsBySessionExerciseId($sessionExerciseId) as $setLog) {
            if ($setLog->isWarmup()) {
                continue;
            }

            $workingSets[] = new LoggedSet(weightKg: $setLog->getWeightKg(), reps: $setLog->getReps());
        }

        return $workingSets;
    }
}
