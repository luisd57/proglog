<?php

declare(strict_types=1);

namespace App\Domain\Stats\Result;

final readonly class OverviewTotals
{
    public function __construct(
        public int $workouts,
        public float $volumeKg,
        public int $reps,
        public int $sets,
        public float $heaviestKg,
        public int $timeSeconds,
    ) {
    }
}
