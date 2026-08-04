<?php

declare(strict_types=1);

namespace App\Domain\Template\Exception;

use App\Domain\Exception\DomainException;

final class TemplateNotFoundException extends DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(
            message: "Template with ID {$id} not found.",
            errorCode: 'TEMPLATE_NOT_FOUND',
        );
    }
}
