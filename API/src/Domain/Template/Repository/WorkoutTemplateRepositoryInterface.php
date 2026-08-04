<?php

declare(strict_types=1);

namespace App\Domain\Template\Repository;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Repository for the WorkoutTemplate aggregate (template + its exercise
 * lines). Lines have no Doctrine relation to the template: they are loaded
 * by template id and composed by the application layer.
 */
interface WorkoutTemplateRepositoryInterface
{
    public function save(WorkoutTemplate $workoutTemplate): void;

    public function findById(WorkoutTemplateId $workoutTemplateId): ?WorkoutTemplate;

    /**
     * Non-archived templates ordered by sort_order ASC.
     *
     * @return ArrayCollection<int, WorkoutTemplate>
     */
    public function findAllActive(): ArrayCollection;

    /**
     * Highest sort_order across ALL templates (archived included, as in the
     * old API); null when no templates exist.
     */
    public function findHighestSortOrder(): ?int;

    /**
     * Lines of one template ordered by sort_order ASC.
     *
     * @return ArrayCollection<int, TemplateExercise>
     */
    public function findExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): ArrayCollection;

    public function countExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): int;

    /**
     * Lines referencing an exercise, across all templates (referential guard
     * before deleting a custom exercise).
     */
    public function countExercisesByExerciseId(ExerciseId $exerciseId): int;

    /**
     * Bulk add with a single flush.
     *
     * @param ArrayCollection<int, TemplateExercise> $templateExercises
     */
    public function addExercises(ArrayCollection $templateExercises): void;

    public function deleteExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): void;

    /**
     * Deletes the template together with its exercise lines (aggregate
     * cascade, with an ON DELETE CASCADE FK as the backstop). Detaching
     * sessions that reference the template is orchestrated in the handler.
     */
    public function delete(WorkoutTemplate $workoutTemplate): void;
}
