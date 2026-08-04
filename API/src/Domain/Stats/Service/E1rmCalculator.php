<?php

declare(strict_types=1);

namespace App\Domain\Stats\Service;

/**
 * Estimated one-rep max. Faithful port of e1rm.ts.
 */
final class E1rmCalculator
{
    private function __construct()
    {
    }

    /**
     * Epley formula: 1RM = weight x (1 + reps/30); 0 when reps <= 0.
     */
    public static function epley1Rm(float $weightKg, int $reps): float
    {
        if ($reps <= 0) {
            return 0.0;
        }

        return $weightKg * (1 + $reps / 30);
    }
}
