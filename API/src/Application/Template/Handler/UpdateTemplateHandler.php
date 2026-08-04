<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Application\Template\DTO\Input\UpdateTemplateInputDTO;
use App\Application\Template\DTO\Output\TemplateOutputDTO;
use App\Application\Template\Service\TemplateAssembler;
use App\Application\Template\Service\TemplateExerciseLineFactory;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

/**
 * Full replace (PUT semantics): the name is updated and the exercise line
 * list is deleted and recreated - existing template-exercise ids change.
 * The template's own sort_order is unchanged.
 */
final readonly class UpdateTemplateHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private TemplateExerciseLineFactory $templateExerciseLineFactory,
        private TemplateAssembler $templateAssembler,
    ) {
    }

    public function __invoke(UpdateTemplateInputDTO $dto): TemplateOutputDTO
    {
        $workoutTemplate = $this->workoutTemplateRepository->findById(WorkoutTemplateId::fromString($dto->id));

        if ($workoutTemplate === null) {
            throw new TemplateNotFoundException($dto->id);
        }

        // Validates exercise refs before anything is persisted or deleted.
        $templateExercises = $this->templateExerciseLineFactory->createLines(
            $workoutTemplate->getId(),
            $dto->exercises,
        );

        $workoutTemplate->rename($dto->name);
        $this->workoutTemplateRepository->save($workoutTemplate);

        $this->workoutTemplateRepository->deleteExercisesByTemplateId($workoutTemplate->getId());
        $this->workoutTemplateRepository->addExercises($templateExercises);

        return $this->templateAssembler->assemble($workoutTemplate);
    }
}
