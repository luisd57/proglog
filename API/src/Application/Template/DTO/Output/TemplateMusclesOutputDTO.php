<?php

declare(strict_types=1);

namespace App\Application\Template\DTO\Output;

final readonly class TemplateMusclesOutputDTO
{
    /**
     * @param array<int, string> $primary
     * @param array<int, string> $secondary
     */
    public function __construct(
        public array $primary,
        public array $secondary,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'secondary' => $this->secondary,
        ];
    }
}
