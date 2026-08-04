<?php

declare(strict_types=1);

namespace App\Application\Session\Service;

use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Application\Session\DTO\Output\SessionExerciseOutputDTO;
use App\Application\Session\DTO\Output\SessionOutputDTO;
use App\Application\Session\DTO\Output\SetOutputDTO;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

/**
 * Composes the Session detail response shape: session + exercises + sets,
 * enriched with the template's targets (matched by exercise id against the
 * CURRENT template lines, as in the old service), a rest-seconds fallback,
 * and the previous sets of each exercise from the most recent OTHER finished
 * session. Needed because the aggregate has no Doctrine relations.
 */
final readonly class SessionAssembler
{
    /**
     * Ultimate rest-seconds fallback when the template line has no
     * rest_seconds and the profile row does not exist yet (matches the
     * profiles.default_rest_seconds schema default).
     */
    private const int FALLBACK_REST_SECONDS = 120;

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
        private ProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function assemble(Session $session): SessionOutputDTO
    {
        $templateName = null;

        /** @var array<string, TemplateExercise> $targetsByExerciseId */
        $targetsByExerciseId = [];

        if ($session->getTemplateId() !== null) {
            $workoutTemplate = $this->workoutTemplateRepository->findById($session->getTemplateId());

            if ($workoutTemplate !== null) {
                $templateName = $workoutTemplate->getName();

                foreach ($this->workoutTemplateRepository->findExercisesByTemplateId($workoutTemplate->getId()) as $templateExercise) {
                    $targetsByExerciseId[$templateExercise->getExerciseId()->getValue()] = $templateExercise;
                }
            }
        }

        $exerciseDtos = [];
        $defaultRestSeconds = $this->defaultRestSeconds();

        /** @var SessionExercise $sessionExercise */
        foreach ($this->sessionRepository->findExercisesBySessionId($session->getId()) as $sessionExercise) {
            $exercise = $this->exerciseRepository->findById($sessionExercise->getExerciseId());

            if ($exercise === null) {
                // Orphaned reference (no FKs in the schema) - skip defensively.
                continue;
            }

            $targetLine = $targetsByExerciseId[$sessionExercise->getExerciseId()->getValue()] ?? null;

            $exerciseDtos[] = new SessionExerciseOutputDTO(
                id: $sessionExercise->getId()->getValue(),
                sortOrder: $sessionExercise->getSortOrder(),
                notes: $sessionExercise->getNotes(),
                exercise: ExerciseOutputDTO::fromEntity($exercise),
                sets: $this->setsOf($sessionExercise),
                targetSets: $targetLine?->getTargetSets(),
                targetReps: $targetLine?->getTargetReps(),
                restSeconds: $targetLine?->getRestSeconds() ?? $defaultRestSeconds,
                previousSets: $this->previousSetsOf($session, $sessionExercise),
            );
        }

        return new SessionOutputDTO(
            id: $session->getId()->getValue(),
            templateId: $session->getTemplateId()?->getValue(),
            templateName: $templateName,
            startedAt: $session->getStartedAt(),
            finishedAt: $session->getFinishedAt(),
            notes: $session->getNotes(),
            exercises: $exerciseDtos,
        );
    }

    /**
     * @return array<int, SetOutputDTO>
     */
    private function setsOf(SessionExercise $sessionExercise): array
    {
        return $this->sessionRepository
            ->findSetsBySessionExerciseId($sessionExercise->getId())
            ->map(fn (SetLog $setLog) => SetOutputDTO::fromEntity($setLog))
            ->toArray();
    }

    /**
     * Rest-seconds fallback when the template line has no rest_seconds (or
     * the session has no template): profile.default_rest_seconds, 120 when
     * the profile row does not exist yet.
     */
    private function defaultRestSeconds(): int
    {
        return $this->profileRepository->find()?->getDefaultRestSeconds() ?? self::FALLBACK_REST_SECONDS;
    }

    /**
     * Sets of this exercise in the most recent OTHER finished session; empty
     * when the exercise was never performed in a finished session.
     *
     * @return array<int, SetOutputDTO>
     */
    private function previousSetsOf(Session $session, SessionExercise $sessionExercise): array
    {
        $previousExercise = $this->sessionRepository->findLatestFinishedExercise(
            $sessionExercise->getExerciseId(),
            $session->getId(),
        );

        if ($previousExercise === null) {
            return [];
        }

        return $this->setsOf($previousExercise);
    }
}
