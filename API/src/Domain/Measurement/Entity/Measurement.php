<?php

declare(strict_types=1);

namespace App\Domain\Measurement\Entity;

use App\Domain\Measurement\Id\MeasurementId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One body measurement sample (weight, body fat, girths). Immutable: rows are
 * only created and deleted, never updated. The entity never reads time:
 * measured_at is passed in by the handler (request value or ClockInterface).
 */
#[ORM\Entity]
#[ORM\Table(name: 'measurements')]
class Measurement
{
    /**
     * Valid measurement types, exactly as they appear on the wire (camelCase
     * L/R suffixes preserved as-is, matching the old API).
     */
    public const array TYPES = [
        'weight', 'bodyfat', 'neck', 'shoulders', 'chest', 'waist', 'hips',
        'bicepL', 'bicepR', 'forearmL', 'forearmR', 'thighL', 'thighR',
        'calfL', 'calfR',
    ];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'measurement_id')]
        private readonly MeasurementId $id,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private readonly string $type,
        #[ORM\Column(type: Types::FLOAT)]
        private readonly float $value,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $measuredAt,
    ) {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown measurement type: {$type}");
        }

        if ($value <= 0) {
            throw new \InvalidArgumentException('Value must be positive.');
        }
    }

    public static function create(
        MeasurementId $id,
        string $type,
        float $value,
        \DateTimeImmutable $measuredAt,
    ): self {
        return new self($id, $type, $value, $measuredAt);
    }

    public function getId(): MeasurementId
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }
}
