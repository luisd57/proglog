<?php

declare(strict_types=1);

namespace App\Application\Exercise\Handler;

use App\Application\Exercise\DTO\Input\ListExercisesInputDTO;
use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Catalog listing with tokenized search, muscle/equipment filters and
 * relevance ranking - faithful port of the NestJS exercises.service.ts.
 */
final readonly class ListExercisesHandler
{
    public function __construct(
        private ExerciseRepositoryInterface $exerciseRepository,
    ) {
    }

    /**
     * @return ArrayCollection<int, ExerciseOutputDTO>
     */
    public function __invoke(ListExercisesInputDTO $dto): ArrayCollection
    {
        // tokenize: each word must appear in the name (any order), tolerant of
        // hyphens/punctuation and simple plurals, so "chin ups" finds "Chin-Up"
        // and "front raise" finds "Front Cable Raise"
        $tokens = $dto->search !== null ? self::searchTokens($dto->search) : [];

        $exercises = $this->exerciseRepository->search($tokens, $dto->muscle, $dto->equipment);

        if ($tokens !== []) {
            $exercises = self::rankBySearch($exercises, $tokens, self::normalizeName((string) $dto->search));
        }

        return $exercises->map(
            fn (Exercise $exercise) => ExerciseOutputDTO::fromEntity($exercise)
        );
    }

    /**
     * @return array<int, string>
     */
    public static function searchTokens(string $search): array
    {
        $tokens = [];

        foreach (preg_split('/\s+/', mb_strtolower($search)) ?: [] as $rawToken) {
            $token = (string) preg_replace('/[^a-z0-9]/', '', $rawToken);

            if ($token === '') {
                continue;
            }

            if (strlen($token) > 2 && str_ends_with($token, 's')) {
                $token = substr($token, 0, -1);
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    private static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    /**
     * Rank already-filtered matches so the closest one floats to the top:
     * exact name match, then fewest words, then genuine whole-word matches over
     * mid-word coincidences (e.g. "chin" inside "machine"), then shorter name.
     * Relies on a stable sort over an alphabetically-ordered input for ties.
     *
     * @param ArrayCollection<int, Exercise> $exercises
     * @param array<int, string> $tokens
     *
     * @return ArrayCollection<int, Exercise>
     */
    private static function rankBySearch(
        ArrayCollection $exercises,
        array $tokens,
        string $normalizedQuery,
    ): ArrayCollection {
        $ranked = $exercises->map(function (Exercise $exercise) use ($tokens, $normalizedQuery): array {
            $normalizedName = self::normalizeName($exercise->getName());
            $words = explode(' ', $normalizedName);

            $allWholeWord = true;
            foreach ($tokens as $token) {
                $tokenMatchesWord = false;
                foreach ($words as $word) {
                    if ($word === $token || str_starts_with($word, $token)) {
                        $tokenMatchesWord = true;
                        break;
                    }
                }
                if (!$tokenMatchesWord) {
                    $allWholeWord = false;
                    break;
                }
            }

            return [
                'exercise' => $exercise,
                'exact' => $normalizedName === $normalizedQuery ? 0 : 1,
                'wordCount' => count($words),
                'wholeWord' => $allWholeWord ? 0 : 1,
                'length' => strlen($exercise->getName()),
            ];
        })->toArray();

        // usort is stable in PHP >= 8.0, preserving the name-ASC input order for ties
        usort(
            $ranked,
            fn (array $rankedA, array $rankedB): int => $rankedA['exact'] <=> $rankedB['exact']
                ?: $rankedA['wordCount'] <=> $rankedB['wordCount']
                ?: $rankedA['wholeWord'] <=> $rankedB['wholeWord']
                ?: $rankedA['length'] <=> $rankedB['length'],
        );

        return new ArrayCollection(
            array_map(fn (array $rankedEntry) => $rankedEntry['exercise'], $ranked)
        );
    }
}
