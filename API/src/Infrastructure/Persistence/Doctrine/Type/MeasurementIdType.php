<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Measurement\Id\MeasurementId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class MeasurementIdType extends GuidType
{
    public const string NAME = 'measurement_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MeasurementId
    {
        if ($value === null) {
            return null;
        }

        return MeasurementId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MeasurementId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
