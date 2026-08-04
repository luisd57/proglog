<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Input;

final readonly class SetLineInputDTO
{
    public function __construct(
        public float $weightKg,
        public int $reps,
        public bool $isWarmup = false,
        public ?string $notes = null,
    ) {
    }
}
