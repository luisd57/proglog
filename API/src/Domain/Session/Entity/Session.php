<?php

declare(strict_types=1);

namespace App\Domain\Session\Entity;

use App\Domain\Session\Id\SessionId;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Workout session aggregate root. Owns SessionExercise children (which own
 * SetLog children); all of them reference their parents by ID value objects
 * only - no Doctrine relations. The template reference is cross-aggregate
 * and nullable: deleting a template detaches its sessions (SET NULL
 * semantics, orchestrated in DeleteTemplateHandler).
 *
 * The entity never reads time: started/finished instants are passed in as
 * $now by handlers using ClockInterface.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session
{
    #[ORM\Column(name: 'template_id', type: 'workout_template_id', nullable: true)]
    private ?WorkoutTemplateId $workoutTemplateId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'session_id')]
        private readonly SessionId $id,
        ?WorkoutTemplateId $workoutTemplateId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $startedAt,
    ) {
        $this->workoutTemplateId = $workoutTemplateId;
        $this->finishedAt = null;
        $this->notes = null;
    }

    public static function start(
        SessionId $id,
        ?WorkoutTemplateId $workoutTemplateId,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $workoutTemplateId, $now);
    }

    /**
     * Idempotent overwrite, as in the old API: finishing an already finished
     * session simply updates finished_at.
     */
    public function finish(\DateTimeImmutable $now): void
    {
        $this->finishedAt = $now;
    }

    public function changeNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * SET NULL semantics when the referenced template is deleted.
     */
    public function clearTemplate(): void
    {
        $this->workoutTemplateId = null;
    }

    public function getId(): SessionId
    {
        return $this->id;
    }

    public function getTemplateId(): ?WorkoutTemplateId
    {
        return $this->workoutTemplateId;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isFinished(): bool
    {
        return $this->finishedAt !== null;
    }
}
