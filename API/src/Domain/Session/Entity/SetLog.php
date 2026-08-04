<?php

declare(strict_types=1);

namespace App\Domain\Session\Entity;

use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SetLogId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One logged set. Immutable: PUT .../sets replaces the whole set list of a
 * session exercise, so rows are always deleted and recreated.
 */
#[ORM\Entity]
#[ORM\Table(name: 'set_logs')]
class SetLog
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'set_log_id')]
        private readonly SetLogId $id,
        #[ORM\Column(type: 'session_exercise_id')]
        private readonly SessionExerciseId $sessionExerciseId,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $setNumber,
        #[ORM\Column(type: Types::FLOAT)]
        private readonly float $weightKg,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $reps,
        #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
        private readonly bool $isWarmup,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $notes,
    ) {
        if ($setNumber < 1) {
            throw new \InvalidArgumentException('Set number must be at least 1.');
        }

        if ($weightKg < 0) {
            throw new \InvalidArgumentException('Weight must not be negative.');
        }

        if ($reps < 0) {
            throw new \InvalidArgumentException('Reps must not be negative.');
        }
    }

    public static function create(
        SetLogId $id,
        SessionExerciseId $sessionExerciseId,
        int $setNumber,
        float $weightKg,
        int $reps,
        bool $isWarmup = false,
        ?string $notes = null,
    ): self {
        return new self(
            id: $id,
            sessionExerciseId: $sessionExerciseId,
            setNumber: $setNumber,
            weightKg: $weightKg,
            reps: $reps,
            isWarmup: $isWarmup,
            notes: $notes,
        );
    }

    public function getId(): SetLogId
    {
        return $this->id;
    }

    public function getSessionExerciseId(): SessionExerciseId
    {
        return $this->sessionExerciseId;
    }

    public function getSetNumber(): int
    {
        return $this->setNumber;
    }

    public function getWeightKg(): float
    {
        return $this->weightKg;
    }

    public function getReps(): int
    {
        return $this->reps;
    }

    public function isWarmup(): bool
    {
        return $this->isWarmup;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
}
