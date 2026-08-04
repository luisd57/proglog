<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Session\Id\SessionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\GuidType;

final class SessionIdType extends GuidType
{
    public const string NAME = 'session_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SessionId
    {
        if ($value === null) {
            return null;
        }

        return SessionId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SessionId) {
            return $value->getValue();
        }

        return (string) $value;
    }
}
