<?php

declare(strict_types=1);

namespace App\Domain\Stats\Result;

/**
 * A personal-record event: the session's top set beat every previous
 * session-best weight or top e1RM.
 */
final readonly class PrEvent
{
    public function __construct(
        public \DateTimeImmutable $date,
        public float $weightKg,
        public int $reps,
        public float $e1rm,
    ) {
    }
}
