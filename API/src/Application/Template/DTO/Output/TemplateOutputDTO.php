<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Output;

use App\Domain\Template\Entity\WorkoutTemplate;

final readonly class TemplateOutputDTO
{
    /**
     * @param array<int, TemplateExerciseOutputDTO> $exercises
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $sortOrder,
        public array $exercises,
    ) {
    }

    /**
     * @param array<int, TemplateExerciseOutputDTO> $exercises
     */
    public static function fromEntity(WorkoutTemplate $workoutTemplate, array $exercises): self
    {
        return new self(
            id: $workoutTemplate->getId()->getValue(),
            name: $workoutTemplate->getName(),
            sortOrder: $workoutTemplate->getSortOrder(),
            exercises: $exercises,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sortOrder,
            'exercises' => array_map(
                fn (TemplateExerciseOutputDTO $templateExerciseOutputDTO) => $templateExerciseOutputDTO->toArray(),
                $this->exercises,
            ),
        ];
    }
}
