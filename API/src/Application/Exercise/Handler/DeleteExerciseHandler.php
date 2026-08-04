<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;

final readonly class DeleteExerciseHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
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

        $this->exerciseRepository->delete($exercise);
    }
}
