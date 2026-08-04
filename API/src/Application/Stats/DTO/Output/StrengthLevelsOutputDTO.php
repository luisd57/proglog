<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

final readonly class StrengthLevelsOutputDTO
{
    /**
     * @param array<int, StrengthLevelEntryOutputDTO> $levels
     */
    private function __construct(
        public bool $ready,
        public ?string $reason,
        public ?float $bodyweightKg,
        public array $levels,
    ) {
    }

    public static function notReady(string $reason): self
    {
        return new self(
            ready: false,
            reason: $reason,
            bodyweightKg: null,
            levels: [],
        );
    }

    /**
     * @param array<int, StrengthLevelEntryOutputDTO> $levels
     */
    public static function ready(float $bodyweightKg, array $levels): self
    {
        return new self(
            ready: true,
            reason: null,
            bodyweightKg: $bodyweightKg,
            levels: $levels,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (!$this->ready) {
            return [
                'ready' => false,
                'reason' => $this->reason,
                'levels' => [],
            ];
        }

        return [
            'ready' => true,
            'bodyweight_kg' => $this->bodyweightKg,
            'levels' => array_map(
                fn (StrengthLevelEntryOutputDTO $strengthLevelEntryOutputDTO) => $strengthLevelEntryOutputDTO->toArray(),
                $this->levels,
            ),
        ];
    }
}
