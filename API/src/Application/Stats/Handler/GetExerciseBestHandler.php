<?php

declare(strict_types=1);

namespace App\Application\Stats\Handler;

use App\Application\Stats\DTO\Input\GetExerciseBestInputDTO;
use App\Application\Stats\DTO\Output\ExerciseBestOutputDTO;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\Service\E1rmCalculator;

/**
 * Best weight and best e1RM across all working sets of the exercise in
 * finished sessions, optionally excluding one session. Unknown exercise id
 * returns nulls (no existence check, as in the old API).
 */
final readonly class GetExerciseBestHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(GetExerciseBestInputDTO $dto): ExerciseBestOutputDTO
    {
        $exerciseId = ExerciseId::fromString($dto->exerciseId);
        $sessionId = $dto->excludeSessionId !== null ? SessionId::fromString($dto->excludeSessionId) : null;

        $setLogs = $this->sessionRepository->findFinishedWorkingSets($exerciseId, $sessionId);

        if ($setLogs->isEmpty()) {
            return new ExerciseBestOutputDTO(bestWeightKg: null, bestE1rm: null);
        }

        $bestWeightKg = -INF;
        $bestE1rm = -INF;

        /** @var SetLog $setLog */
        foreach ($setLogs as $setLog) {
            $bestWeightKg = max($bestWeightKg, $setLog->getWeightKg());
            $bestE1rm = max($bestE1rm, E1rmCalculator::epley1Rm($setLog->getWeightKg(), $setLog->getReps()));
        }

        return new ExerciseBestOutputDTO(bestWeightKg: $bestWeightKg, bestE1rm: $bestE1rm);
    }
}
