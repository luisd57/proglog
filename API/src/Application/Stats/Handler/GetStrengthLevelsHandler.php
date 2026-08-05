<?php

declare(strict_types=1);

namespace App\Application\Stats\Handler;

use App\Application\Stats\DTO\Output\StrengthLevelEntryOutputDTO;
use App\Application\Stats\DTO\Output\StrengthLevelsOutputDTO;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\Parameter\LiftStandard;
use App\Domain\Stats\Service\E1rmCalculator;
use App\Domain\Stats\Service\StrengthLevelCalculator;
use App\Domain\Stats\Service\StrengthStandards;

/**
 * Classifies the five main lifts against the strength standards. Requires the
 * latest bodyweight measurement and the profile sex; per lift, the first
 * seeded exercise matching the standard's names wins.
 */
final readonly class GetStrengthLevelsHandler
{
    public function __construct(
        private MeasurementRepositoryInterface $measurementRepository,
        private ProfileRepositoryInterface $profileRepository,
        private ExerciseRepositoryInterface $exerciseRepository,
        private SessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(): StrengthLevelsOutputDTO
    {
        $bodyweight = $this->measurementRepository->findLatestByType('weight');

        if ($bodyweight === null) {
            return StrengthLevelsOutputDTO::notReady('no-bodyweight');
        }

        $sex = $this->profileSex();

        if ($sex === null) {
            return StrengthLevelsOutputDTO::notReady('no-profile');
        }

        $entries = [];

        foreach (StrengthStandards::all() as $liftStandard) {
            $entries[] = $this->entryFor($liftStandard, $sex, $bodyweight->getValue());
        }

        return StrengthLevelsOutputDTO::ready($bodyweight->getValue(), $entries);
    }

    private function profileSex(): ?string
    {
        $sex = $this->profileRepository->find()?->getSex();

        return in_array($sex, Profile::SEXES, true) ? $sex : null;
    }

    private function entryFor(LiftStandard $liftStandard, string $sex, float $bodyweightKg): StrengthLevelEntryOutputDTO
    {
        $exercise = $this->findFirstExerciseByNames($liftStandard->exerciseNames);
        $bestE1rm = $exercise !== null ? $this->bestE1rmOf($exercise->getId()) : null;
        $standardRows = $liftStandard->rowsForSex($sex);

        if ($exercise !== null && $bestE1rm !== null) {
            $levelResult = StrengthLevelCalculator::levelFor($standardRows, $bodyweightKg, $bestE1rm);

            return new StrengthLevelEntryOutputDTO(
                lift: $liftStandard->lift,
                label: $liftStandard->label,
                exerciseId: $exercise->getId()->getValue(),
                e1rm: $bestE1rm,
                level: $levelResult->level,
                nextLevel: $levelResult->nextLevel,
                progress: $levelResult->progress,
                thresholds: $levelResult->thresholds,
            );
        }

        return new StrengthLevelEntryOutputDTO(
            lift: $liftStandard->lift,
            label: $liftStandard->label,
            exerciseId: $exercise?->getId()->getValue(),
            e1rm: null,
            level: null,
            nextLevel: null,
            progress: null,
            thresholds: StrengthLevelCalculator::levelFor($standardRows, $bodyweightKg, 0.0)->thresholds,
        );
    }

    /**
     * First match wins, in the order the standard lists its exercise names.
     *
     * @param array<int, string> $names
     */
    private function findFirstExerciseByNames(array $names): ?Exercise
    {
        foreach ($names as $name) {
            $exercise = $this->exerciseRepository->findByName($name);

            if ($exercise !== null) {
                return $exercise;
            }
        }

        return null;
    }

    private function bestE1rmOf(ExerciseId $exerciseId): ?float
    {
        $setLogs = $this->sessionRepository->findFinishedWorkingSets($exerciseId, null);

        if ($setLogs->isEmpty()) {
            return null;
        }

        $bestE1rm = -INF;

        /** @var SetLog $setLog */
        foreach ($setLogs as $setLog) {
            $bestE1rm = max($bestE1rm, E1rmCalculator::epley1Rm($setLog->getWeightKg(), $setLog->getReps()));
        }

        return $bestE1rm;
    }
}
