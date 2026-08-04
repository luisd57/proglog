<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Input;

final readonly class ReplaceSetsInputDTO
{
    /**
     * @param array<int, SetLineInputDTO> $sets
     */
    public function __construct(
        public string $sessionId,
        public string $sessionExerciseId,
        public array $sets,
    ) {
    }
}
