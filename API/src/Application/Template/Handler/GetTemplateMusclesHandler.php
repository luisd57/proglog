<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Application\Template\DTO\Output\TemplateMusclesOutputDTO;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

/**
 * Union of muscles across the template's exercises; muscles that appear as
 * primary anywhere are removed from the secondary list. Insertion order is
 * preserved, matching the JS Set semantics of the old service.
 */
final readonly class GetTemplateMusclesHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    public function __invoke(string $id): TemplateMusclesOutputDTO
    {
        $workoutTemplate = $this->workoutTemplateRepository->findById(WorkoutTemplateId::fromString($id));

        if ($workoutTemplate === null) {
            throw new TemplateNotFoundException($id);
        }

        $primary = [];
        $secondary = [];

        foreach ($this->workoutTemplateRepository->findExercisesByTemplateId($workoutTemplate->getId()) as $templateExercise) {
            $exercise = $this->exerciseRepository->findById($templateExercise->getExerciseId());

            if ($exercise === null) {
                continue;
            }

            foreach ($exercise->getPrimaryMuscles() as $muscle) {
                if (!in_array($muscle, $primary, true)) {
                    $primary[] = $muscle;
                }
            }

            foreach ($exercise->getSecondaryMuscles() as $muscle) {
                if (!in_array($muscle, $secondary, true)) {
                    $secondary[] = $muscle;
                }
            }
        }

        $secondary = array_values(array_filter(
            $secondary,
            fn (string $muscle): bool => !in_array($muscle, $primary, true),
        ));

        return new TemplateMusclesOutputDTO(primary: $primary, secondary: $secondary);
    }
}
