<?php

declare(strict_types=1);

namespace App\Application\Session\Handler;

use App\Application\Session\DTO\Input\StartSessionInputDTO;
use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\Service\SessionAssembler;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Clock\ClockInterface;

/**
 * Starts a session, optionally prefilled from a template: the template's
 * exercise lines are copied as session exercises (sort_order = index, no
 * sets). started_at = now via ClockInterface.
 */
final readonly class StartSessionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private SessionAssembler $sessionAssembler,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartSessionInputDTO $dto): SessionOutputDTO
    {
        $workoutTemplateId = null;
        $templateExercises = new ArrayCollection();

        if ($dto->templateId !== null) {
            $workoutTemplateId = WorkoutTemplateId::fromString($dto->templateId);
            $workoutTemplate = $this->workoutTemplateRepository->findById($workoutTemplateId);

            if ($workoutTemplate === null) {
                throw new TemplateNotFoundException($dto->templateId);
            }

            $templateExercises = $this->workoutTemplateRepository->findExercisesByTemplateId($workoutTemplateId);
        }

        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: $workoutTemplateId,
            now: $this->clock->now(),
        );

        $this->sessionRepository->save($session);

        if (!$templateExercises->isEmpty()) {
            $sessionExercises = new ArrayCollection();

            foreach (array_values($templateExercises->toArray()) as $index => $templateExercise) {
                $sessionExercises->add(SessionExercise::create(
                    id: SessionExerciseId::generate(),
                    sessionId: $session->getId(),
                    exerciseId: $templateExercise->getExerciseId(),
                    sortOrder: $index,
                ));
            }

            $this->sessionRepository->addExercises($sessionExercises);
        }

        return $this->sessionAssembler->assemble($session);
    }
}
