<?php

declare(strict_types=1);

namespace App\Application\Measurement\Handler;

use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

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
     * @return ArrayCollection<string, float>
     */
    public function __invoke(): ArrayCollection
    {
        $latestByType = [];

        foreach ($this->measurementRepository->findAll() as $measurement) {
            $latestByType[$measurement->getType()] = $measurement->getValue();
        }

        return new ArrayCollection($latestByType);
    }
}
