<?php

declare(strict_types=1);

namespace App\Application\Measurement\Handler;

use App\Domain\Measurement\Exception\MeasurementNotFoundException;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;

final readonly class DeleteMeasurementHandler
{
    public function __construct(
        private MeasurementRepositoryInterface $measurementRepository,
    ) {
    }

    public function __invoke(string $id): void
    {
        $measurement = $this->measurementRepository->findById(MeasurementId::fromString($id));

        if ($measurement === null) {
            throw new MeasurementNotFoundException($id);
        }

        $this->measurementRepository->delete($measurement);
    }
}
