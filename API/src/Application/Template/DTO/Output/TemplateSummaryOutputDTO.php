<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Output;

use App\Domain\Template\Entity\WorkoutTemplate;

final readonly class TemplateSummaryOutputDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public int $sortOrder,
        public int $exerciseCount,
    ) {
    }

    public static function fromEntity(WorkoutTemplate $workoutTemplate, int $exerciseCount): self
    {
        return new self(
            id: $workoutTemplate->getId()->getValue(),
            name: $workoutTemplate->getName(),
            sortOrder: $workoutTemplate->getSortOrder(),
            exerciseCount: $exerciseCount,
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
            'exercise_count' => $this->exerciseCount,
        ];
    }
}
