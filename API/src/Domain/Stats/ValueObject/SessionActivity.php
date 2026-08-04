<?php

declare(strict_types=1);

namespace App\Domain\Stats\ValueObject;

/**
 * Parameter object for the overview calculator: one finished session with
 * its working (non warmup) sets flattened across exercises.
 */
final readonly class SessionActivity
{
    /**
     * @param array<int, LoggedSet> $sets
     */
    public function __construct(
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public array $sets,
    ) {
    }
}
