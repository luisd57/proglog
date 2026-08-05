<?php

declare(strict_types=1);

namespace App\Domain\Stats\Parameter;

/**
 * Minimal set line the stats calculators work on (decouples them from the
 * Session aggregate's SetLog entity).
 */
final readonly class LoggedSet
{
    public function __construct(
        public float $weightKg,
        public int $reps,
    ) {
    }
}
