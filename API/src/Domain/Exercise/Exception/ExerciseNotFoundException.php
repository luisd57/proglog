<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Exception;

use App\Domain\Exception\DomainException;

final class ExerciseNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            message: "Exercise with ID {$id} not found.",
            errorCode: 'EXERCISE_NOT_FOUND',
        );
    }
}
