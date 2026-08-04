<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\UpdateExerciseInputDTO;
use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\DuplicateExerciseNameException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;

final readonly class UpdateExerciseHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    public function __invoke(UpdateExerciseInputDTO $dto): ExerciseOutputDTO
    {
        $exercise = $this->exerciseRepository->findById(ExerciseId::fromString($dto->id));

        if ($exercise === null) {
            throw new ExerciseNotFoundException($dto->id);
        }

        if (!$exercise->isCustom()) {
            throw new BuiltInExerciseImmutableException();
        }

        if ($dto->name !== null) {
            $name = trim($dto->name);
            $existing = $name !== '' ? $this->exerciseRepository->findByName($name) : null;

            if ($existing !== null && !$existing->getId()->equals($exercise->getId())) {
                throw new DuplicateExerciseNameException($name);
            }

            $exercise->rename($dto->name);
        }

        if ($dto->primaryMuscles !== null) {
            $exercise->replacePrimaryMuscles($dto->primaryMuscles);
        }

        if ($dto->secondaryMuscles !== null) {
            $exercise->replaceSecondaryMuscles($dto->secondaryMuscles);
        }

        if ($dto->equipmentProvided) {
            $exercise->changeEquipment($dto->equipment);
        }

        if ($dto->categoryProvided) {
            $exercise->changeCategory($dto->category);
        }

        if ($dto->instructionsProvided) {
            $exercise->changeInstructions($dto->instructions);
        }

        $this->exerciseRepository->save($exercise);

        return ExerciseOutputDTO::fromEntity($exercise);
    }
}
