<?php

declare(strict_types=1);

namespace App\Application\Template\Service;

use App\Application\Template\DTO\Output\TemplateExerciseOutputDTO;
use App\Application\Template\DTO\Output\TemplateOutputDTO;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

/**
 * Composes the Template response shape (template + exercise lines + catalog
 * exercises). Needed because the aggregate has no Doctrine relations: lines
 * are loaded by template id and joined to their exercises here.
 */
final readonly class TemplateAssembler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    public function assemble(WorkoutTemplate $workoutTemplate): TemplateOutputDTO
    {
        $templateExercises = $this->workoutTemplateRepository
            ->findExercisesByTemplateId($workoutTemplate->getId());

        $exerciseDtos = [];

        /** @var TemplateExercise $templateExercise */
        foreach ($templateExercises as $templateExercise) {
            $exercise = $this->exerciseRepository->findById($templateExercise->getExerciseId());

            if ($exercise === null) {
                // Orphaned reference (no FKs in the schema) - skip defensively.
                continue;
            }

            $exerciseDtos[] = TemplateExerciseOutputDTO::fromEntity($templateExercise, $exercise);
        }

        return TemplateOutputDTO::fromEntity($workoutTemplate, $exerciseDtos);
    }
}
