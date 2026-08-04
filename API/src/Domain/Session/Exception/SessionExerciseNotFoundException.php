<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

use App\Domain\Exception\DomainException;

final class SessionExerciseNotFoundException extends DomainException
{
    public function __construct(string $sessionExerciseId, string $sessionId)
    {
        parent::__construct(
            message: "Session exercise {$sessionExerciseId} not found in session {$sessionId}.",
            errorCode: 'SESSION_EXERCISE_NOT_FOUND',
        );
    }
}
