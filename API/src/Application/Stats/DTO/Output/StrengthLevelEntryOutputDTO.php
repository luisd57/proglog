<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

final readonly class StrengthLevelEntryOutputDTO
{
    /**
     * @param array<int, float> $thresholds beginner..elite, always present
     */
    public function __construct(
        public string $lift,
        public string $label,
        public ?string $exerciseId,
        public ?float $e1rm,
        public ?string $level,
        public ?string $nextLevel,
        public ?float $progress,
        public array $thresholds,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lift' => $this->lift,
            'label' => $this->label,
            'exercise_id' => $this->exerciseId,
            'e1rm' => $this->e1rm,
            'level' => $this->level,
            'next_level' => $this->nextLevel,
            'progress' => $this->progress,
            'thresholds' => $this->thresholds,
        ];
    }
}
