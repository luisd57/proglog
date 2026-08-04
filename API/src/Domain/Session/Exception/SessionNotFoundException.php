<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

use App\Domain\Exception\DomainException;

final class SessionNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            message: "Session with ID {$id} not found.",
            errorCode: 'SESSION_NOT_FOUND',
        );
    }
}
