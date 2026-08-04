<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

use App\Domain\Stats\ValueObject\CumulativeVolumePoint;
use App\Domain\Stats\ValueObject\OverviewTotals;

final readonly class OverviewOutputDTO
{
    /**
     * @param array<int, CumulativeVolumePoint> $cumulativeVolume
     */
    public function __construct(
        public string $period,
        public OverviewTotals $current,
        public ?OverviewTotals $previous,
        public array $cumulativeVolume,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'current' => self::totalsToArray($this->current),
            'previous' => $this->previous !== null ? self::totalsToArray($this->previous) : null,
            'cumulative_volume' => array_map(
                fn (CumulativeVolumePoint $cumulativeVolumePoint) => [
                    'date' => $cumulativeVolumePoint->date,
                    'value' => $cumulativeVolumePoint->value,
                ],
                $this->cumulativeVolume,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function totalsToArray(OverviewTotals $overviewTotals): array
    {
        return [
            'workouts' => $overviewTotals->workouts,
            'volume_kg' => $overviewTotals->volumeKg,
            'reps' => $overviewTotals->reps,
            'sets' => $overviewTotals->sets,
            'heaviest_kg' => $overviewTotals->heaviestKg,
            'time_seconds' => $overviewTotals->timeSeconds,
        ];
    }
}
