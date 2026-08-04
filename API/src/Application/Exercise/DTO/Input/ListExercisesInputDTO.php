<?php

declare(strict_types=1);

namespace App\Application\Exercise\DTO\Input;

final readonly class ListExercisesInputDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $muscle = null,
        public ?string $equipment = null,
    ) {
    }
}
