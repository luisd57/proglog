<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Session\Id\SetLogId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class SetLogIdType extends GuidType
{
    public const string NAME = 'set_log_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SetLogId
    {
        if ($value === null) {
            return null;
        }

        return SetLogId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SetLogId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
