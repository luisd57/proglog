<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class WorkoutTemplateIdType extends GuidType
{
    public const string NAME = 'workout_template_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?WorkoutTemplateId
    {
        if ($value === null) {
            return null;
        }

        return WorkoutTemplateId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof WorkoutTemplateId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
