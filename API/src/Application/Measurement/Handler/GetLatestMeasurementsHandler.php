<?php

declare(strict_types=1);

namespace App\Application\Measurement\Handler;

use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;

/**
 * Latest value per type (types never measured are absent). Walks the full
 * ascending series so the last write wins, as in the old API.
 */
final readonly class GetLatestMeasurementsHandler
{
    public function __construct(
        private MeasurementRepositoryInterface $measurementRepository,
    ) {
    }

    /**
     * @return array<string, float>
     */
    public function __invoke(): array
    {
        $latestByType = [];

        foreach ($this->measurementRepository->findAll() as $measurement) {
            $latestByType[$measurement->getType()] = $measurement->getValue();
        }

        return $latestByType;
    }
}
