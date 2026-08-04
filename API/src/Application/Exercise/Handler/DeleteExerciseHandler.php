<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\ExerciseInUseException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

final readonly class DeleteExerciseHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $id): void
    {
        $exercise = $this->exerciseRepository->findById(ExerciseId::fromString($id));

        if ($exercise === null) {
            throw new ExerciseNotFoundException($id);
        }

        if (!$exercise->isCustom()) {
            throw new BuiltInExerciseImmutableException();
        }

        $exerciseId = $exercise->getId();
        $references = $this->workoutTemplateRepository->countExercisesByExerciseId($exerciseId)
            + $this->sessionRepository->countExercisesByExerciseId($exerciseId);

        if ($references > 0) {
            throw new ExerciseInUseException();
        }

        $this->exerciseRepository->delete($exercise);
    }
}
