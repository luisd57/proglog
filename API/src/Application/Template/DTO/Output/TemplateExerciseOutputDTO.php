<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Output;

use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Template\Entity\TemplateExercise;

final readonly class TemplateExerciseOutputDTO
{
    public function __construct(
        public string $id,
        public int $sortOrder,
        public ?int $targetSets,
        public ?int $targetReps,
        public ?int $restSeconds,
        public ExerciseOutputDTO $exercise,
    ) {
    }

    public static function fromEntity(TemplateExercise $templateExercise, Exercise $exercise): self
    {
        return new self(
            id: $templateExercise->getId()->getValue(),
            sortOrder: $templateExercise->getSortOrder(),
            targetSets: $templateExercise->getTargetSets(),
            targetReps: $templateExercise->getTargetReps(),
            restSeconds: $templateExercise->getRestSeconds(),
            exercise: ExerciseOutputDTO::fromEntity($exercise),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sortOrder,
            'target_sets' => $this->targetSets,
            'target_reps' => $this->targetReps,
            'rest_seconds' => $this->restSeconds,
            'exercise' => $this->exercise->toArray(),
        ];
    }
}
