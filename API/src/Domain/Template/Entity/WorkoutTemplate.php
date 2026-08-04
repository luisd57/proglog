<?php

declare(strict_types=1);

namespace App\Domain\Template\Entity;

use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Workout template aggregate root. Owns its TemplateExercise lines, which
 * reference it by WorkoutTemplateId only (no Doctrine relations) - the
 * repository loads lines by template id and handlers compose them.
 *
 * archivedAt is part of the persisted schema (list endpoints only return
 * non-archived templates); the API currently hard-deletes templates, as the
 * old NestJS service did, so nothing archives yet.
 */
#[ORM\Entity]
#[ORM\Table(name: 'workout_templates')]
class WorkoutTemplate
{
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'workout_template_id')]
        private readonly WorkoutTemplateId $id,
        string $name,
        #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
        private readonly int $sortOrder,
    ) {
        if ($sortOrder < 0) {
            throw new \InvalidArgumentException('Sort order must not be negative.');
        }

        $this->name = self::guardName($name);
        $this->archivedAt = null;
    }

    public static function create(WorkoutTemplateId $id, string $name, int $sortOrder): self
    {
        return new self($id, $name, $sortOrder);
    }

    public function rename(string $name): void
    {
        $this->name = self::guardName($name);
    }

    public function archive(\DateTimeImmutable $now): void
    {
        $this->archivedAt = $now;
    }

    public function getId(): WorkoutTemplateId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    private static function guardName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Name is required.');
        }

        return $trimmed;
    }
}
