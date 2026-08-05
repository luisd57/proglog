<?php

declare(strict_types=1);

namespace App\Domain\Stats\Result;

/**
 * One running-sum volume point; date is a server-local calendar day
 * (YYYY-MM-DD).
 */
final readonly class CumulativeVolumePoint
{
    public function __construct(
        public string $date,
        public float $value,
    ) {
    }
}
