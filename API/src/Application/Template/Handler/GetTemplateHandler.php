<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Application\Template\DTO\Output\TemplateOutputDTO;
use App\Application\Template\Service\TemplateAssembler;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;

final readonly class GetTemplateHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
        private TemplateAssembler $templateAssembler,
    ) {
    }

    public function __invoke(string $id): TemplateOutputDTO
    {
        $workoutTemplate = $this->workoutTemplateRepository->findById(WorkoutTemplateId::fromString($id));

        if ($workoutTemplate === null) {
            throw new TemplateNotFoundException($id);
        }

        return $this->templateAssembler->assemble($workoutTemplate);
    }
}
