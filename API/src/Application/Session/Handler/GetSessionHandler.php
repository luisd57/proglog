<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;

final readonly class GetSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionAssembler $sessionAssembler,
    ) {
    }

    public function __invoke(string $id): SessionOutputDTO
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($id));

        if ($session === null) {
            throw new SessionNotFoundException($id);
        }

        return $this->sessionAssembler->assemble($session);
    }
}
