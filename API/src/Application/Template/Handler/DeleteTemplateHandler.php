<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

/**
 * Hard delete, as in the old API. Sessions that referenced the template keep
 * running: their template_id is cleared first (ON DELETE SET NULL semantics
 * of the old schema, orchestrated here because the schema has no FKs).
 */
final readonly class DeleteTemplateHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $id): void
    {
        $workoutTemplate = $this->workoutTemplateRepository->findById(WorkoutTemplateId::fromString($id));

        if ($workoutTemplate === null) {
            throw new TemplateNotFoundException($id);
        }

        foreach ($this->sessionRepository->findByTemplateId($workoutTemplate->getId()) as $session) {
            $session->clearTemplate();
            $this->sessionRepository->save($session);
        }

        $this->workoutTemplateRepository->delete($workoutTemplate);
    }
}
