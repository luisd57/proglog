<?php

declare(strict_types=1);

namespace App\Application\Exercise\DTO\Input;

final readonly class CreateExerciseInputDTO
{
    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public function __construct(
        public string $name,
        public array $primaryMuscles,
        public array $secondaryMuscles = [],
        public ?string $equipment = null,
        public ?string $category = null,
        public ?string $instructions = null,
    ) {
    }
}
