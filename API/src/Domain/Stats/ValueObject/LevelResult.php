<?php

declare(strict_types=1);

namespace App\Domain\Stats\ValueObject;

/**
 * Result of classifying an e1RM against a standards table.
 */
final readonly class LevelResult
{
    /**
     * @param array<int, float> $thresholds interpolated for the bodyweight,
     *                                      [beginner..elite]
     */
    public function __construct(
        public string $level,
        public ?string $nextLevel,
        public float $progress,
        public array $thresholds,
    ) {
    }
}
