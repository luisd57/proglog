<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Input;

final readonly class UpdateTemplateInputDTO
{
    /**
     * @param array<int, TemplateExerciseLineInputDTO> $exercises
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $exercises,
    ) {
    }
}
