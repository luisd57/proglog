<?php

declare(strict_types=1);

namespace App\Application\Stats\DTO\Output;

use App\Domain\Stats\ValueObject\ExerciseSeriesResult;
use App\Domain\Stats\ValueObject\PrEvent;
use App\Domain\Stats\ValueObject\SeriesPoint;

final readonly class ExerciseSeriesOutputDTO
{
    /**
     * @param array<int, SeriesPoint> $points
     * @param array<int, PrEvent>     $prs
     */
    public function __construct(
        public array $points,
        public array $prs,
    ) {
    }

    public static function fromResult(ExerciseSeriesResult $exerciseSeriesResult): self
    {
        return new self(
            points: $exerciseSeriesResult->points,
            prs: $exerciseSeriesResult->prs,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'points' => array_map(
                fn (SeriesPoint $seriesPoint) => [
                    'session_id' => $seriesPoint->sessionId,
                    'date' => $seriesPoint->date->format(\DateTimeInterface::ATOM),
                    'top_set' => [
                        'weight_kg' => $seriesPoint->topSetWeightKg,
                        'reps' => $seriesPoint->topSetReps,
                    ],
                    'volume' => $seriesPoint->volume,
                    'e1rm' => $seriesPoint->e1rm,
                ],
                $this->points,
            ),
            'prs' => array_map(
                fn (PrEvent $prEvent) => [
                    'date' => $prEvent->date->format(\DateTimeInterface::ATOM),
                    'weight_kg' => $prEvent->weightKg,
                    'reps' => $prEvent->reps,
                    'e1rm' => $prEvent->e1rm,
                ],
                $this->prs,
            ),
        ];
    }
}
