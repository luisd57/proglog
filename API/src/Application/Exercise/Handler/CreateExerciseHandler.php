<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\CreateExerciseInputDTO;
use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Exception\DuplicateExerciseNameException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;

final readonly class CreateExerciseHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    public function __invoke(CreateExerciseInputDTO $dto): ExerciseOutputDTO
    {
        $name = trim($dto->name);

        if ($name !== '' && $this->exerciseRepository->findByName($name) !== null) {
            throw new DuplicateExerciseNameException($name);
        }

        $exercise = Exercise::createCustom(
            id: ExerciseId::generate(),
            name: $dto->name,
            primaryMuscles: $dto->primaryMuscles,
            secondaryMuscles: $dto->secondaryMuscles,
            equipment: $dto->equipment,
            category: $dto->category,
            instructions: $dto->instructions,
        );

        $this->exerciseRepository->save($exercise);

        return ExerciseOutputDTO::fromEntity($exercise);
    }
}
