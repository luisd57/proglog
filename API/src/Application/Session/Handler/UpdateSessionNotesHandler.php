<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\UpdateSessionNotesInputDTO;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

final readonly class UpdateSessionNotesHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(UpdateSessionNotesInputDTO $dto): void
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($dto->sessionId));

        if ($session === null) {
            throw new SessionNotFoundException($dto->sessionId);
        }

        $session->changeNotes($dto->notes);
        $this->sessionRepository->save($session);
    }
}
