<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

use App\Domain\Stats\ValueObject\WeeklyMuscles;

final readonly class WeeklyMusclesOutputDTO
{
    /**
     * @param array<int, string> $primary
     * @param array<int, string> $secondary
     */
    public function __construct(
        public array $primary,
        public array $secondary,
        public int $sessionCount,
    ) {
    }

    public static function fromResult(WeeklyMuscles $weeklyMuscles): self
    {
        return new self(
            primary: $weeklyMuscles->primary,
            secondary: $weeklyMuscles->secondary,
            sessionCount: $weeklyMuscles->sessionCount,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'secondary' => $this->secondary,
            'session_count' => $this->sessionCount,
        ];
    }
}
