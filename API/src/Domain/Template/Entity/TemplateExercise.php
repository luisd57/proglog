<?php

declare(strict_types=1);

namespace App\Domain\Template\Entity;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Template\Id\TemplateExerciseId;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One exercise line of a workout template. Immutable: PUT /templates/{id}
 * replaces the whole line set, so lines are always deleted and recreated.
 *
 * References the owning template and the catalog exercise by ID value
 * objects only - no Doctrine relations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'template_exercises')]
class TemplateExercise
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'template_exercise_id')]
        private readonly TemplateExerciseId $id,
        #[ORM\Column(name: 'template_id', type: 'workout_template_id')]
        private readonly WorkoutTemplateId $workoutTemplateId,
        #[ORM\Column(type: 'exercise_id')]
        private readonly ExerciseId $exerciseId,
        #[ORM\Column(type: Types::INTEGER)]
        private readonly int $sortOrder,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private readonly ?int $targetSets,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private readonly ?int $targetReps,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private readonly ?int $restSeconds,
    ) {
        if ($sortOrder < 0) {
            throw new \InvalidArgumentException('Sort order must not be negative.');
        }
    }

    public static function create(
        TemplateExerciseId $id,
        WorkoutTemplateId $workoutTemplateId,
        ExerciseId $exerciseId,
        int $sortOrder,
        ?int $targetSets = null,
        ?int $targetReps = null,
        ?int $restSeconds = null,
    ): self {
        return new self(
            id: $id,
            workoutTemplateId: $workoutTemplateId,
            exerciseId: $exerciseId,
            sortOrder: $sortOrder,
            targetSets: $targetSets,
            targetReps: $targetReps,
            restSeconds: $restSeconds,
        );
    }

    public function getId(): TemplateExerciseId
    {
        return $this->id;
    }

    public function getTemplateId(): WorkoutTemplateId
    {
        return $this->workoutTemplateId;
    }

    public function getExerciseId(): ExerciseId
    {
        return $this->exerciseId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getTargetSets(): ?int
    {
        return $this->targetSets;
    }

    public function getTargetReps(): ?int
    {
        return $this->targetReps;
    }

    public function getRestSeconds(): ?int
    {
        return $this->restSeconds;
    }
}
