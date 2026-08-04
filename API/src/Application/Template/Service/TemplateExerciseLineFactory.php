<?php

declare(strict_types=1);

namespace App\Application\Template\Service;

use App\Application\Template\DTO\Input\TemplateExerciseLineInputDTO;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Id\TemplateExerciseId;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Builds TemplateExercise lines from request input, validating that every
 * referenced exercise exists (404 EXERCISE_NOT_FOUND on bad refs - the old
 * API surfaced a raw FK error). Line sort_order = array index. Shared by
 * create (initial lines) and update (full replace).
 */
final readonly class TemplateExerciseLineFactory
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    /**
     * @param array<int, TemplateExerciseLineInputDTO> $exerciseLines
     *
     * @return ArrayCollection<int, TemplateExercise>
     */
    public function createLines(WorkoutTemplateId $workoutTemplateId, array $exerciseLines): ArrayCollection
    {
        $templateExercises = new ArrayCollection();

        foreach (array_values($exerciseLines) as $index => $exerciseLine) {
            $exerciseId = ExerciseId::fromString($exerciseLine->exerciseId);

            if ($this->exerciseRepository->findById($exerciseId) === null) {
                throw new ExerciseNotFoundException($exerciseLine->exerciseId);
            }

            $templateExercises->add(TemplateExercise::create(
                id: TemplateExerciseId::generate(),
                workoutTemplateId: $workoutTemplateId,
                exerciseId: $exerciseId,
                sortOrder: $index,
                targetSets: $exerciseLine->targetSets,
                targetReps: $exerciseLine->targetReps,
                restSeconds: $exerciseLine->restSeconds,
            ));
        }

        return $templateExercises;
    }
}
