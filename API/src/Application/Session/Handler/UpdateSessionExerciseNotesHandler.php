<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\UpdateSessionExerciseNotesInputDTO;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

final readonly class UpdateSessionExerciseNotesHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(UpdateSessionExerciseNotesInputDTO $dto): void
    {
        $sessionId = SessionId::fromString($dto->sessionId);

        $sessionExercise = $this->sessionRepository->findExerciseById(
            SessionExerciseId::fromString($dto->sessionExerciseId)
        );

        if ($sessionExercise === null || !$sessionExercise->belongsToSession($sessionId)) {
            throw new SessionExerciseNotFoundException($dto->sessionExerciseId, $dto->sessionId);
        }

        $sessionExercise->changeNotes($dto->notes);
        $this->sessionRepository->saveExercise($sessionExercise);
    }
}
