<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Input;

final readonly class RemoveSessionExerciseInputDTO
{
    public function __construct(
        public string $sessionId,
        public string $sessionExerciseId,
    ) {
    }
}
