<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Input;

final readonly class GetExerciseBestInputDTO
{
    public function __construct(
        public string $exerciseId,
        public ?string $excludeSessionId,
    ) {
    }
}
