<?php

declare(strict_types=1);

namespace App\Application\Measurement\Handler;

use App\Application\Measurement\DTO\Output\MeasurementOutputDTO;
use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Full series of one measurement type ordered by measured_at ASC. The type is
 * required and must be a known type (deliberate deviation: the old API
 * silently returned all types when absent).
 */
final readonly class ListMeasurementsHandler
{
    public function __construct(
        private MeasurementRepositoryInterface $measurementRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, MeasurementOutputDTO>
     */
    public function __invoke(string $type): ArrayCollection
    {
        if (!in_array($type, Measurement::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown measurement type: {$type}");
        }

        return $this->measurementRepository
            ->findByType($type)
            ->map(fn (Measurement $measurement) => MeasurementOutputDTO::fromEntity($measurement));
    }
}
