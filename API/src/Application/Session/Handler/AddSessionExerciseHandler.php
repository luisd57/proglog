<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\AddSessionExerciseInputDTO;
use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

/**
 * Appends an exercise to a running session with the next sort_order.
 */
final readonly class AddSessionExerciseHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
        private SessionAssembler $sessionAssembler,
    ) {
    }

    public function __invoke(AddSessionExerciseInputDTO $dto): SessionOutputDTO
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($dto->sessionId));

        if ($session === null) {
            throw new SessionNotFoundException($dto->sessionId);
        }

        $exerciseId = ExerciseId::fromString($dto->exerciseId);

        if ($this->exerciseRepository->findById($exerciseId) === null) {
            throw new ExerciseNotFoundException($dto->exerciseId);
        }

        $this->sessionRepository->saveExercise(SessionExercise::create(
            id: SessionExerciseId::generate(),
            sessionId: $session->getId(),
            exerciseId: $exerciseId,
            sortOrder: $this->sessionRepository->countExercisesBySessionId($session->getId()),
        ));

        return $this->sessionAssembler->assemble($session);
    }
}
