<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Exception;

use App\Domain\Exception\DomainException;

final class DuplicateExerciseNameException extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct(
            message: "An exercise named \"{$name}\" already exists.",
            errorCode: 'DUPLICATE_EXERCISE_NAME',
        );
    }
}
