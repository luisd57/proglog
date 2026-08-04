<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Input;

final readonly class StartSessionInputDTO
{
    public function __construct(
        public ?string $templateId = null,
    ) {
    }
}
