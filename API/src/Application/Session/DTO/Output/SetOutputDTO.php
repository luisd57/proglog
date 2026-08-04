<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Output;

use App\Domain\Session\Entity\SetLog;

final readonly class SetOutputDTO
{
    public function __construct(
        public string $id,
        public int $setNumber,
        public float $weightKg,
        public int $reps,
        public bool $isWarmup,
        public ?string $notes,
    ) {
    }

    public static function fromEntity(SetLog $setLog): self
    {
        return new self(
            id: $setLog->getId()->getValue(),
            setNumber: $setLog->getSetNumber(),
            weightKg: $setLog->getWeightKg(),
            reps: $setLog->getReps(),
            isWarmup: $setLog->isWarmup(),
            notes: $setLog->getNotes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'set_number' => $this->setNumber,
            'weight_kg' => $this->weightKg,
            'reps' => $this->reps,
            'is_warmup' => $this->isWarmup,
            'notes' => $this->notes,
        ];
    }
}
