<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Session\Id\SessionExerciseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class SessionExerciseIdType extends GuidType
{
    public const string NAME = 'session_exercise_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SessionExerciseId
    {
        if ($value === null) {
            return null;
        }

        return SessionExerciseId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SessionExerciseId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
