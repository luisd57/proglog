<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Exercise\Id\ExerciseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class ExerciseIdType extends GuidType
{
    public const string NAME = 'exercise_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExerciseId
    {
        if ($value === null) {
            return null;
        }

        return ExerciseId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ExerciseId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
