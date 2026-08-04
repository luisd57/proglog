<?php

declare(strict_types=1);

namespace App\Application\Exercise\DTO\Input;

/**
 * Patch semantics: null on $name / $primaryMuscles / $secondaryMuscles means
 * "not provided" (they are non-nullable fields). The nullable string fields
 * carry an explicit *Provided flag so that a provided null clears the value.
 */
final readonly class UpdateExerciseInputDTO
{
    /**
     * @param array<int, string>|null $primaryMuscles
     * @param array<int, string>|null $secondaryMuscles
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?array $primaryMuscles = null,
        public ?array $secondaryMuscles = null,
        public bool $equipmentProvided = false,
        public ?string $equipment = null,
        public bool $categoryProvided = false,
        public ?string $category = null,
        public bool $instructionsProvided = false,
        public ?string $instructions = null,
    ) {
    }
}
