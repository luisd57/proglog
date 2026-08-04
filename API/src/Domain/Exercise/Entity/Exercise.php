<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Entity;

use App\Domain\Exercise\Id\ExerciseId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exercise catalog entry. Built-in exercises come from the seed dataset and
 * are immutable through the API; custom exercises are user-created.
 *
 * Later domains (TemplateExercise, SessionExercise) reference exercises by
 * ExerciseId only - no Doctrine relations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'exercises')]
class Exercise
{
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $name;

    /** @var array<int, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $primaryMuscles;

    /** @var array<int, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $secondaryMuscles;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $equipment;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $category;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions;

    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'exercise_id')]
        private readonly ExerciseId $id,
        string $name,
        array $primaryMuscles,
        array $secondaryMuscles,
        ?string $equipment,
        ?string $category,
        ?string $instructions,
        #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
        private readonly bool $isCustom,
    ) {
        $this->name = self::guardName($name);
        $this->primaryMuscles = self::guardMuscles($primaryMuscles, requireNonEmpty: true);
        $this->secondaryMuscles = self::guardMuscles($secondaryMuscles, requireNonEmpty: false);
        $this->equipment = $equipment;
        $this->category = $category;
        $this->instructions = $instructions;
    }

    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public static function createCustom(
        ExerciseId $id,
        string $name,
        array $primaryMuscles,
        array $secondaryMuscles = [],
        ?string $equipment = null,
        ?string $category = null,
        ?string $instructions = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            primaryMuscles: $primaryMuscles,
            secondaryMuscles: $secondaryMuscles,
            equipment: $equipment,
            category: $category,
            instructions: $instructions,
            isCustom: true,
        );
    }

    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public static function createBuiltIn(
        ExerciseId $id,
        string $name,
        array $primaryMuscles,
        array $secondaryMuscles = [],
        ?string $equipment = null,
        ?string $category = null,
        ?string $instructions = null,
    ): self {
        return new self(
            id: $id,
            name: $name,
            primaryMuscles: $primaryMuscles,
            secondaryMuscles: $secondaryMuscles,
            equipment: $equipment,
            category: $category,
            instructions: $instructions,
            isCustom: false,
        );
    }

    public function rename(string $name): void
    {
        $this->name = self::guardName($name);
    }

    /**
     * @param array<int, string> $primaryMuscles
     */
    public function replacePrimaryMuscles(array $primaryMuscles): void
    {
        $this->primaryMuscles = self::guardMuscles($primaryMuscles, requireNonEmpty: true);
    }

    /**
     * @param array<int, string> $secondaryMuscles
     */
    public function replaceSecondaryMuscles(array $secondaryMuscles): void
    {
        $this->secondaryMuscles = self::guardMuscles($secondaryMuscles, requireNonEmpty: false);
    }

    public function changeEquipment(?string $equipment): void
    {
        $this->equipment = $equipment;
    }

    public function changeCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function changeInstructions(?string $instructions): void
    {
        $this->instructions = $instructions;
    }

    public function getId(): ExerciseId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function getPrimaryMuscles(): array
    {
        return $this->primaryMuscles;
    }

    /**
     * @return array<int, string>
     */
    public function getSecondaryMuscles(): array
    {
        return $this->secondaryMuscles;
    }

    public function getEquipment(): ?string
    {
        return $this->equipment;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function isCustom(): bool
    {
        return $this->isCustom;
    }

    public function targetsMuscle(string $muscle): bool
    {
        return in_array($muscle, $this->primaryMuscles, true)
            || in_array($muscle, $this->secondaryMuscles, true);
    }

    private static function guardName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Name is required.');
        }

        return $trimmed;
    }

    /**
     * @param array<int, mixed> $muscles
     *
     * @return array<int, string>
     */
    private static function guardMuscles(array $muscles, bool $requireNonEmpty): array
    {
        if ($requireNonEmpty && $muscles === []) {
            throw new \InvalidArgumentException('At least one primary muscle is required.');
        }

        foreach ($muscles as $muscle) {
            if (!is_string($muscle) || trim($muscle) === '') {
                throw new \InvalidArgumentException('Muscles must be non-empty strings.');
            }
        }

        return array_values($muscles);
    }
}
