<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

/**
 * Deletes a session; the repository cascades to its exercises and sets.
 */
final readonly class DeleteSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $id): void
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($id));

        if ($session === null) {
            throw new SessionNotFoundException($id);
        }

        $this->sessionRepository->delete($session);
    }
}
