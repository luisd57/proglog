<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Template\Id\TemplateExerciseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class TemplateExerciseIdType extends GuidType
{
    public const string NAME = 'template_exercise_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TemplateExerciseId
    {
        if ($value === null) {
            return null;
        }

        return TemplateExerciseId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TemplateExerciseId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
