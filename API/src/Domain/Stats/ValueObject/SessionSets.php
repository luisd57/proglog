<?php

declare(strict_types=1);

namespace App\Domain\Stats\ValueObject;

/**
 * Parameter object for the exercise series calculator: the working (non
 * warmup) sets one exercise received in one finished session.
 */
final readonly class SessionSets
{
    /**
     * @param array<int, LoggedSet> $sets ordered by set_number ASC
     */
    public function __construct(
        public string $sessionId,
        public \DateTimeImmutable $startedAt,
        public array $sets,
    ) {
    }
}
