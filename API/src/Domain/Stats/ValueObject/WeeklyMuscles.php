<?php

declare(strict_types=1);

namespace App\Domain\Stats\ValueObject;

/**
 * Result of the weekly muscle aggregation: muscles unioned across qualifying
 * session exercises, primary winning over secondary.
 */
final readonly class WeeklyMuscles
{
    /**
     * @param array<int, string> $primary
     * @param array<int, string> $secondary
     */
    public function __construct(
        public array $primary,
        public array $secondary,
        public int $sessionCount,
    ) {
    }
}
