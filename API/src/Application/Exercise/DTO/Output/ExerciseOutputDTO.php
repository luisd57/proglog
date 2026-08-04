<?php

declare(strict_types=1);

namespace App\Application\Exercise\DTO\Output;

use App\Domain\Exercise\Entity\Exercise;

final readonly class ExerciseOutputDTO
{
    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $primaryMuscles,
        public array $secondaryMuscles,
        public ?string $equipment,
        public ?string $category,
        public ?string $instructions,
        public bool $isCustom,
    ) {
    }

    public static function fromEntity(Exercise $exercise): self
    {
        return new self(
            id: $exercise->getId()->getValue(),
            name: $exercise->getName(),
            primaryMuscles: $exercise->getPrimaryMuscles(),
            secondaryMuscles: $exercise->getSecondaryMuscles(),
            equipment: $exercise->getEquipment(),
            category: $exercise->getCategory(),
            instructions: $exercise->getInstructions(),
            isCustom: $exercise->isCustom(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'primary_muscles' => $this->primaryMuscles,
            'secondary_muscles' => $this->secondaryMuscles,
            'equipment' => $this->equipment,
            'category' => $this->category,
            'instructions' => $this->instructions,
            'is_custom' => $this->isCustom,
        ];
    }
}
