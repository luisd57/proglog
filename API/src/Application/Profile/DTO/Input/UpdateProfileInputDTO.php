<?php

declare(strict_types=1);

namespace App\Application\Profile\DTO\Input;

/**
 * Patch semantics: *Provided flags distinguish "key absent" (leave untouched)
 * from an explicit null (clear the value), as in UpdateExerciseInputDTO.
 */
final readonly class UpdateProfileInputDTO
{
    public function __construct(
        public bool $sexProvided,
        public ?string $sex,
        public bool $birthDateProvided,
        public ?string $birthDate,
        public bool $defaultRestSecondsProvided,
        public ?int $defaultRestSeconds,
        public bool $heightCmProvided,
        public ?float $heightCm,
    ) {
    }
}
