<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Application\Template\DTO\Input\CreateTemplateInputDTO;
use App\Application\Template\DTO\Output\TemplateOutputDTO;
use App\Application\Template\Service\TemplateAssembler;
use App\Application\Template\Service\TemplateExerciseLineFactory;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

final readonly class CreateTemplateHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private TemplateExerciseLineFactory $templateExerciseLineFactory,
        private TemplateAssembler $templateAssembler,
    ) {
    }

    public function __invoke(CreateTemplateInputDTO $dto): TemplateOutputDTO
    {
        $workoutTemplateId = WorkoutTemplateId::generate();

        // Validates exercise refs before anything is persisted.
        $templateExercises = $this->templateExerciseLineFactory->createLines($workoutTemplateId, $dto->exercises);

        // Template sort_order = highest existing + 1 (0 for the first),
        // archived templates included - as in the old service.
        $sortOrder = ($this->workoutTemplateRepository->findHighestSortOrder() ?? -1) + 1;

        $workoutTemplate = WorkoutTemplate::create(
            id: $workoutTemplateId,
            name: $dto->name,
            sortOrder: $sortOrder,
        );

        $this->workoutTemplateRepository->save($workoutTemplate);
        $this->workoutTemplateRepository->addExercises($templateExercises);

        return $this->templateAssembler->assemble($workoutTemplate);
    }
}
