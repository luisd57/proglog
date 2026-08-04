<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Output\SessionSummaryOutputDTO;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListSessionsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, SessionSummaryOutputDTO>
     */
    public function __invoke(): ArrayCollection
    {
        /** @var array<string, string|null> $templateNames */
        $templateNames = [];

        return $this->sessionRepository->findAll()->map(
            function (Session $session) use (&$templateNames): SessionSummaryOutputDTO {
                $templateName = null;

                if ($session->getTemplateId() !== null) {
                    $templateIdValue = $session->getTemplateId()->getValue();

                    if (!array_key_exists($templateIdValue, $templateNames)) {
                        $templateNames[$templateIdValue] = $this->workoutTemplateRepository
                            ->findById($session->getTemplateId())
                            ?->getName();
                    }

                    $templateName = $templateNames[$templateIdValue];
                }

                return SessionSummaryOutputDTO::fromEntity(
                    $session,
                    $templateName,
                    $this->sessionRepository->countExercisesBySessionId($session->getId()),
                    $this->sessionRepository->countSetsBySessionId($session->getId()),
                );
            }
        );
    }
}
