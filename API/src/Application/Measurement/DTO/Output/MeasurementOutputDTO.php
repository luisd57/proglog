<?php

declare(strict_types=1);

namespace App\Application\Measurement\DTO\Output;

use App\Domain\Measurement\Entity\Measurement;

final readonly class MeasurementOutputDTO
{
    public function __construct(
        public string $id,
        public string $type,
        public float $value,
        public \DateTimeImmutable $measuredAt,
    ) {
    }

    public static function fromEntity(Measurement $measurement): self
    {
        return new self(
            id: $measurement->getId()->getValue(),
            type: $measurement->getType(),
            value: $measurement->getValue(),
            measuredAt: $measurement->getMeasuredAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'value' => $this->value,
            'measured_at' => $this->measuredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
