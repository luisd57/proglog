<?php

declare(strict_types=1);

namespace App\Domain\Stats\Parameter;

/**
 * Strength standard for one lift: seeded exercise names it applies to (first
 * match wins) and the per-sex bodyweight tables.
 */
final readonly class LiftStandard
{
    /**
     * @param array<int, string>      $exerciseNames
     * @param array<int, StandardRow> $male
     * @param array<int, StandardRow> $female
     */
    public function __construct(
        public string $lift,
        public string $label,
        public array $exerciseNames,
        public array $male,
        public array $female,
    ) {
    }

    /**
     * @return array<int, StandardRow>
     */
    public function rowsForSex(string $sex): array
    {
        return $sex === 'female' ? $this->female : $this->male;
    }
}
