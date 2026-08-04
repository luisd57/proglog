<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

final readonly class ExerciseBestOutputDTO
{
    public function __construct(
        public ?float $bestWeightKg,
        public ?float $bestE1rm,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'best_weight_kg' => $this->bestWeightKg,
            'best_e1rm' => $this->bestE1rm,
        ];
    }
}
