<?php

declare(strict_types=1);

namespace App\Domain\Session\Entity;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One exercise performed in a session. Owns SetLog children, which reference
 * it by SessionExerciseId only - no Doctrine relations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'session_exercises')]
class SessionExercise
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'session_exercise_id')]
        private readonly SessionExerciseId $id,
        #[ORM\Column(type: 'session_id')]
        private readonly SessionId $sessionId,
        #[ORM\Column(type: 'exercise_id')]
        private readonly ExerciseId $exerciseId,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $sortOrder,
    ) {
        if ($sortOrder < 0) {
            throw new \InvalidArgumentException('Sort order must not be negative.');
        }

        $this->notes = null;
    }

    public static function create(
        SessionExerciseId $id,
        SessionId $sessionId,
        ExerciseId $exerciseId,
        int $sortOrder,
    ): self {
        return new self($id, $sessionId, $exerciseId, $sortOrder);
    }

    public function changeNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    public function getId(): SessionExerciseId
    {
        return $this->id;
    }

    public function getSessionId(): SessionId
    {
        return $this->sessionId;
    }

    public function getExerciseId(): ExerciseId
    {
        return $this->exerciseId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function belongsToSession(SessionId $sessionId): bool
    {
        return $this->sessionId->equals($sessionId);
    }
}
