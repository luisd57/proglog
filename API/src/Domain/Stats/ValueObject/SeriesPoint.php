<?php

declare(strict_types=1);

namespace App\Domain\Stats\ValueObject;

/**
 * One progress point: a finished session's top set, volume and e1RM for one
 * exercise.
 */
final readonly class SeriesPoint
{
    public function __construct(
        public string $sessionId,
        public \DateTimeImmutable $date,
        public float $topSetWeightKg,
        public int $topSetReps,
        public float $volume,
        public float $e1rm,
    ) {
    }
}
