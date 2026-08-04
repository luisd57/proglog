<?php

declare(strict_types=1);

namespace App\Application\Template\Handler;

use App\Application\Template\DTO\Output\TemplateSummaryOutputDTO;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListTemplatesHandler
{
    public function __construct(
        private WorkoutTemplateRepositoryInterface $workoutTemplateRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, TemplateSummaryOutputDTO>
     */
    public function __invoke(): ArrayCollection
    {
        return $this->workoutTemplateRepository->findAllActive()->map(
            fn (WorkoutTemplate $workoutTemplate) => TemplateSummaryOutputDTO::fromEntity(
                $workoutTemplate,
                $this->workoutTemplateRepository->countExercisesByTemplateId($workoutTemplate->getId()),
            )
        );
    }
}
