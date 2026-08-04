<?php

declare(strict_types=1);

namespace App\Application\Stats\Handler;

use App\Application\Stats\DTO\Output\OverviewOutputDTO;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Stats\Service\OverviewCalculator;
use App\Domain\Stats\ValueObject\LoggedSet;
use App\Domain\Stats\ValueObject\SessionActivity;
use Symfony\Component\Clock\ClockInterface;

/**
 * Training overview totals for a rolling window, with the preceding
 * equal-length window for comparison (null for all-time) and the cumulative
 * volume series. Unknown periods silently fall back to 7d, as in the old API.
 */
final readonly class GetOverviewHandler
{
    private const array PERIOD_DAYS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '365d' => 365,
        'all' => null,
    ];

    private const string DEFAULT_PERIOD = '7d';

    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $period): OverviewOutputDTO
    {
        $resolved = array_key_exists($period, self::PERIOD_DAYS) ? $period : self::DEFAULT_PERIOD;
        $days = self::PERIOD_DAYS[$resolved];
        $now = $this->clock->now();

        $currentStart = $days === null ? null : $now->modify(sprintf('-%d days', $days));
        $fetchSince = $days === null ? null : $now->modify(sprintf('-%d days', 2 * $days));

        $current = [];
        $previous = [];

        foreach ($this->sessionRepository->findFinishedSessionsBetween($fetchSince, null) as $session) {
            $sessionActivity = $this->activityOf($session);

            if ($currentStart === null || $session->getStartedAt() >= $currentStart) {
                $current[] = $sessionActivity;
            } else {
                $previous[] = $sessionActivity;
            }
        }

        return new OverviewOutputDTO(
            period: $resolved,
            current: OverviewCalculator::totals($current),
            previous: $days === null ? null : OverviewCalculator::totals($previous),
            cumulativeVolume: OverviewCalculator::cumulativeVolume($current, $currentStart, $now),
        );
    }

    private function activityOf(Session $session): SessionActivity
    {
        $workingSets = [];

        foreach ($this->sessionRepository->findExercisesBySessionId($session->getId()) as $sessionExercise) {
            /** @var SetLog $setLog */
            foreach ($this->sessionRepository->findSetsBySessionExerciseId($sessionExercise->getId()) as $setLog) {
                if ($setLog->isWarmup()) {
                    continue;
                }

                $workingSets[] = new LoggedSet(weightKg: $setLog->getWeightKg(), reps: $setLog->getReps());
            }
        }

        return new SessionActivity(
            startedAt: $session->getStartedAt(),
            finishedAt: $session->getFinishedAt(),
            sets: $workingSets,
        );
    }
}
