<?php

declare(strict_types=1);

namespace App\Domain\Exercise\Repository;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use Doctrine\Common\Collections\ArrayCollection;

interface ExerciseRepositoryInterface
{
    public function save(Exercise $exercise): void;

    /**
     * Bulk save with a single flush (seeding).
     *
     * @param ArrayCollection<int, Exercise> $exercises
     */
    public function saveAll(ArrayCollection $exercises): void;

    public function findById(ExerciseId $id): ?Exercise;

    public function findByName(string $name): ?Exercise;

    /**
     * Filtered catalog listing, ordered by name ASC.
     *
     * - $tokens: every token must appear in the name (case-insensitive substring)
     * - $muscle: exact match against primary OR secondary muscles
     * - $equipment: exact match
     *
     * @param array<int, string> $tokens
     *
     * @return ArrayCollection<int, Exercise>
     */
    public function search(array $tokens, ?string $muscle, ?string $equipment): ArrayCollection;

    public function countBuiltIn(): int;

    public function delete(Exercise $exercise): void;
}
