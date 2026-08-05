<?php

declare(strict_types=1);

namespace App\Domain\Stats\Result;

final readonly class ExerciseSeriesResult
{
    /**
     * @param array<int, SeriesPoint> $points
     * @param array<int, PrEvent>     $prs
     */
    public function __construct(
        public array $points,
        public array $prs,
    ) {
    }
}
