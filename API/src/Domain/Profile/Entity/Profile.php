<?php

declare(strict_types=1);

namespace App\Domain\Profile\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User profile - a singleton row (single-user tool, old schema: id = 1). The
 * internal id is never exposed on the wire. Created lazily with defaults on
 * first access (GET/PATCH /api/profile), as in the old API.
 */
#[ORM\Entity]
#[ORM\Table(name: 'profiles')]
class Profile
{
    public const int SINGLETON_ID = 1;

    public const int DEFAULT_REST_SECONDS = 120;

    public const array SEXES = ['male', 'female'];

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $sex;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $birthDate;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => self::DEFAULT_REST_SECONDS])]
    private int $defaultRestSeconds;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $heightCm;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $id,
    ) {
        $this->sex = null;
        $this->birthDate = null;
        $this->defaultRestSeconds = self::DEFAULT_REST_SECONDS;
        $this->heightCm = null;
    }

    public static function createDefault(): self
    {
        return new self(self::SINGLETON_ID);
    }

    public function changeSex(?string $sex): void
    {
        if ($sex !== null && !in_array($sex, self::SEXES, true)) {
            throw new \InvalidArgumentException('Sex must be male, female or null.');
        }

        $this->sex = $sex;
    }

    public function changeBirthDate(?\DateTimeImmutable $birthDate): void
    {
        $this->birthDate = $birthDate;
    }

    public function changeDefaultRestSeconds(int $defaultRestSeconds): void
    {
        if ($defaultRestSeconds <= 0) {
            throw new \InvalidArgumentException('Default rest seconds must be positive.');
        }

        $this->defaultRestSeconds = $defaultRestSeconds;
    }

    public function changeHeightCm(?float $heightCm): void
    {
        $this->heightCm = $heightCm;
    }

    public function getSex(): ?string
    {
        return $this->sex;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function getDefaultRestSeconds(): int
    {
        return $this->defaultRestSeconds;
    }

    public function getHeightCm(): ?float
    {
        return $this->heightCm;
    }
}
