<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Input;

final readonly class TemplateExerciseLineInputDTO
{
    public function __construct(
        public string $exerciseId,
        public ?int $targetSets = null,
        public ?int $targetReps = null,
        public ?int $restSeconds = null,
    ) {
    }
}
