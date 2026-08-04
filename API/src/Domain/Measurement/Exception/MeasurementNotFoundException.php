<?php

declare(strict_types=1);

namespace App\Domain\Measurement\Exception;

use App\Domain\Exception\DomainException;

final class MeasurementNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            message: "Measurement with ID {$id} not found.",
            errorCode: 'MEASUREMENT_NOT_FOUND',
        );
    }
}
