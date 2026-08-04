<?php

declare(strict_types=1);

namespace App\Application\Measurement\Handler;

use App\Application\Measurement\DTO\Input\CreateMeasurementInputDTO;
use App\Application\Measurement\DTO\Output\MeasurementOutputDTO;
use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Records a measurement. measured_at defaults to now (server clock via
 * ClockInterface) when absent, as in the old API.
 */
final readonly class CreateMeasurementHandler
{
    public function __construct(
        private MeasurementRepositoryInterface $measurementRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateMeasurementInputDTO $dto): MeasurementOutputDTO
    {
        $measurement = Measurement::create(
            id: MeasurementId::generate(),
            type: $dto->type,
            value: $dto->value,
            measuredAt: $dto->measuredAt !== null ? self::parseMeasuredAt($dto->measuredAt) : $this->clock->now(),
        );

        $this->measurementRepository->save($measurement);

        return MeasurementOutputDTO::fromEntity($measurement);
    }

    private static function parseMeasuredAt(string $measuredAt): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($measuredAt);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException("Invalid measured_at date: {$measuredAt}", 0, $exception);
        }
    }
}
