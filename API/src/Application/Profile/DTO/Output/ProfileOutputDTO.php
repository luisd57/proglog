<?php

declare(strict_types=1);

namespace App\Application\Profile\DTO\Output;

use App\Domain\Profile\Entity\Profile;

final readonly class ProfileOutputDTO
{
    public function __construct(
        public ?string $sex,
        public ?\DateTimeImmutable $birthDate,
        public int $defaultRestSeconds,
        public ?float $heightCm,
    ) {
    }

    public static function fromEntity(Profile $profile): self
    {
        return new self(
            sex: $profile->getSex(),
            birthDate: $profile->getBirthDate(),
            defaultRestSeconds: $profile->getDefaultRestSeconds(),
            heightCm: $profile->getHeightCm(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sex' => $this->sex,
            'birth_date' => $this->birthDate?->format('Y-m-d'),
            'default_rest_seconds' => $this->defaultRestSeconds,
            'height_cm' => $this->heightCm,
        ];
    }
}
