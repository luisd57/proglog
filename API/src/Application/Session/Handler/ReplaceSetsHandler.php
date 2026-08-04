<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\ReplaceSetsInputDTO;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Id\SetLogId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Replaces ALL sets of a session exercise. set_number is assigned from the
 * array index (1-based); an empty list clears the sets.
 */
final readonly class ReplaceSetsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(ReplaceSetsInputDTO $dto): void
    {
        $sessionId = SessionId::fromString($dto->sessionId);
        $sessionExerciseId = SessionExerciseId::fromString($dto->sessionExerciseId);

        $sessionExercise = $this->sessionRepository->findExerciseById($sessionExerciseId);

        if ($sessionExercise === null || !$sessionExercise->belongsToSession($sessionId)) {
            throw new SessionExerciseNotFoundException($dto->sessionExerciseId, $dto->sessionId);
        }

        $setLogs = new ArrayCollection();

        foreach (array_values($dto->sets) as $index => $setLine) {
            $setLogs->add(SetLog::create(
                id: SetLogId::generate(),
                sessionExerciseId: $sessionExerciseId,
                setNumber: $index + 1,
                weightKg: $setLine->weightKg,
                reps: $setLine->reps,
                isWarmup: $setLine->isWarmup,
                notes: $setLine->notes,
            ));
        }

        $this->sessionRepository->replaceSets($sessionExerciseId, $setLogs);
    }
}
