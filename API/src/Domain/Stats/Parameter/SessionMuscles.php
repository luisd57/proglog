<?php

declare(strict_types=1);

namespace App\Domain\Stats\Parameter;

/**
 * Parameter object for the weekly muscle aggregator: the muscles one
 * qualifying session exercise targets (an exercise with at least one working
 * set in a finished session of the window).
 */
final readonly class SessionMuscles
{
    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public function __construct(
        public string $sessionId,
        public array $primaryMuscles,
        public array $secondaryMuscles,
    ) {
    }
}
