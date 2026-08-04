<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Exception;

use App\Domain\Exception\DomainException;

final class ExerciseInUseException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Exercise is referenced by a workout template or a logged session.',
            errorCode: 'EXERCISE_IN_USE',
        );
    }
}
