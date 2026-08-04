<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Sets finished_at = now (idempotent overwrite, as in the old API).
 */
final readonly class FinishSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionAssembler $sessionAssembler,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id): SessionOutputDTO
    {
        $session = $this->sessionRepository->findById(SessionId::fromString($id));

        if ($session === null) {
            throw new SessionNotFoundException($id);
        }

        $session->finish($this->clock->now());
        $this->sessionRepository->save($session);

        return $this->sessionAssembler->assemble($session);
    }
}
