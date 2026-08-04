<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\RemoveSessionExerciseInputDTO;
use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

/**
 * Removes a session exercise and its sets, then returns the updated session.
 */
final readonly class RemoveSessionExerciseHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionAssembler $sessionAssembler,
    ) {
    }

    public function __invoke(RemoveSessionExerciseInputDTO $dto): SessionOutputDTO
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($dto->sessionId));

        if ($session === null) {
            throw new SessionNotFoundException($dto->sessionId);
        }

        $sessionExercise = $this->sessionRepository->findExerciseById(
            SessionExerciseId::fromString($dto->sessionExerciseId)
        );

        if ($sessionExercise === null || !$sessionExercise->belongsToSession($session->getId())) {
            throw new SessionExerciseNotFoundException($dto->sessionExerciseId, $dto->sessionId);
        }

        $this->sessionRepository->deleteExercise($sessionExercise);

        return $this->sessionAssembler->assemble($session);
    }
}
