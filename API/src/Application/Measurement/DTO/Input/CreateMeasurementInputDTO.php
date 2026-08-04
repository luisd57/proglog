<?php

declare(strict_types=1);

namespace App\Application\Measurement\DTO\Input;

final readonly class CreateMeasurementInputDTO
{
    public function __construct(
        public string $type,
        public float $value,
        public ?string $measuredAt,
    ) {
    }
}
