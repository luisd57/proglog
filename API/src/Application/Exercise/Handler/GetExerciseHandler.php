<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;

final readonly class GetExerciseHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    public function __invoke(string $id): ExerciseOutputDTO
    {
        $exercise = $this->exerciseRepository->findById(ExerciseId::fromString($id));

        if ($exercise === null) {
            throw new ExerciseNotFoundException($id);
        }

        return ExerciseOutputDTO::fromEntity($exercise);
    }
}
