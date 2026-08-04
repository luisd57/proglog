<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Output;

use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;

final readonly class SessionExerciseOutputDTO
{
    /**
     * @param array<int, SetOutputDTO> $sets
     * @param array<int, SetOutputDTO> $previousSets
     */
    public function __construct(
        public string $id,
        public int $sortOrder,
        public ?string $notes,
        public ExerciseOutputDTO $exercise,
        public array $sets,
        public ?int $targetSets,
        public ?int $targetReps,
        public int $restSeconds,
        public array $previousSets,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sortOrder,
            'notes' => $this->notes,
            'exercise' => $this->exercise->toArray(),
            'sets' => array_map(fn (SetOutputDTO $setOutputDTO) => $setOutputDTO->toArray(), $this->sets),
            'target_sets' => $this->targetSets,
            'target_reps' => $this->targetReps,
            'rest_seconds' => $this->restSeconds,
            'previous_sets' => array_map(fn (SetOutputDTO $setOutputDTO) => $setOutputDTO->toArray(), $this->previousSets),
        ];
    }
}
