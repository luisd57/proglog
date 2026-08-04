<?php

declare(strict_types=1);

namespace App\Application\Stats\Handler;

use App\Application\Stats\DTO\Output\WeeklyMusclesOutputDTO;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\Service\WeeklyMuscleAggregator;
use App\Domain\Stats\ValueObject\SessionMuscles;
use Symfony\Component\Clock\ClockInterface;

/**
 * Muscles trained in finished sessions started within the last 7 days
 * (rolling window from now); only session exercises with at least one
 * working (non warmup) set count.
 */
final readonly class GetWeeklyMusclesHandler
{
    private const int WINDOW_DAYS = 7;

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): WeeklyMusclesOutputDTO
    {
        $since = $this->clock->now()->modify(sprintf('-%d days', self::WINDOW_DAYS));

        $sessionMuscleEntries = [];

        foreach ($this->sessionRepository->findFinishedSessionsBetween($since, null) as $session) {
            /** @var SessionExercise $sessionExercise */
            foreach ($this->sessionRepository->findExercisesBySessionId($session->getId()) as $sessionExercise) {
                if (!$this->hasWorkingSet($sessionExercise)) {
                    continue;
                }

                $exercise = $this->exerciseRepository->findById($sessionExercise->getExerciseId());

                if ($exercise === null) {
                    // Orphaned reference (no FKs in the schema) - skip defensively.
                    continue;
                }

                $sessionMuscleEntries[] = new SessionMuscles(
                    sessionId: $session->getId()->getValue(),
                    primaryMuscles: $exercise->getPrimaryMuscles(),
                    secondaryMuscles: $exercise->getSecondaryMuscles(),
                );
            }
        }

        return WeeklyMusclesOutputDTO::fromResult(WeeklyMuscleAggregator::aggregate($sessionMuscleEntries));
    }

    private function hasWorkingSet(SessionExercise $sessionExercise): bool
    {
        /** @var SetLog $setLog */
        foreach ($this->sessionRepository->findSetsBySessionExerciseId($sessionExercise->getId()) as $setLog) {
            if (!$setLog->isWarmup()) {
                return true;
            }
        }

        return false;
    }
}
