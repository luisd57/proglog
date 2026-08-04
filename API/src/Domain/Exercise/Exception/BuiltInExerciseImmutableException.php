<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Exception;

use App\Domain\Exception\DomainException;

final class BuiltInExerciseImmutableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Built-in exercises cannot be modified or deleted.',
            errorCode: 'BUILT_IN_EXERCISE_IMMUTABLE',
        );
    }
}
