<?php

declare(strict_types=1);

namespace App\Domain\Stats\Parameter;

/**
 * One bodyweight row of a strength-standards table.
 */
final readonly class StandardRow
{
    /**
     * @param array<int, float> $thresholds thresholds for
     *                                      [beginner, novice, intermediate, advanced, elite]
     */
    public function __construct(
        public float $bodyweightKg,
        public array $thresholds,
    ) {
        if (count($thresholds) !== 5) {
            throw new \InvalidArgumentException('A standard row needs exactly 5 thresholds.');
        }
    }
}
