<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Stats;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;

final class StatsControllerTest extends ApiTestCase
{
    private const string NOW = '2026-08-04 12:00:00';

    private ExerciseRepositoryInterface $exerciseRepository;
    private SessionRepositoryInterface $sessionRepository;
    private MeasurementRepositoryInterface $measurementRepository;
    private ProfileRepositoryInterface $profileRepository;

    private Exercise $bench;
    private Exercise $row;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ExerciseRepositoryInterface $exerciseRepository */
        $exerciseRepository = self::getContainer()->get(ExerciseRepositoryInterface::class);
        /** @var SessionRepositoryInterface $sessionRepository */
        $sessionRepository = self::getContainer()->get(SessionRepositoryInterface::class);
        /** @var MeasurementRepositoryInterface $measurementRepository */
        $measurementRepository = self::getContainer()->get(MeasurementRepositoryInterface::class);
        /** @var ProfileRepositoryInterface $profileRepository */
        $profileRepository = self::getContainer()->get(ProfileRepositoryInterface::class);

        $this->exerciseRepository = $exerciseRepository;
        $this->sessionRepository = $sessionRepository;
        $this->measurementRepository = $measurementRepository;
        $this->profileRepository = $profileRepository;

        // the name the bench strength standard matches on
        $this->bench = DomainTestHelper::createBuiltInExercise(
            name: 'Barbell Bench Press - Medium Grip',
            primaryMuscles: ['chest'],
            secondaryMuscles: ['triceps'],
        );
        $this->row = DomainTestHelper::createBuiltInExercise(
            name: 'Bent Over Barbell Row',
            primaryMuscles: ['middle back'],
            secondaryMuscles: ['biceps'],
        );
        $this->exerciseRepository->save($this->bench);
        $this->exerciseRepository->save($this->row);
    }

    /**
     * @param array<int, array{0: float, 1: int, 2: bool}> $sets
     */
    private function logFinishedSession(
        Exercise $exercise,
        array $sets,
        string $startedAt,
        ?string $finishedAt = null,
    ): Session {
        $session = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable($startedAt),
            finishedAt: new \DateTimeImmutable($finishedAt ?? $startedAt),
        );
        $this->sessionRepository->save($session);

        $sessionExercise = DomainTestHelper::createSessionExercise(
            sessionId: $session->getId(),
            exerciseId: $exercise->getId(),
        );
        $this->sessionRepository->saveExercise($sessionExercise);

        $setLogs = [];

        foreach ($sets as $index => $set) {
            $setLogs[] = DomainTestHelper::createSetLog(
                sessionExerciseId: $sessionExercise->getId(),
                setNumber: $index + 1,
                weightKg: $set[0],
                reps: $set[1],
                isWarmup: $set[2],
            );
        }

        $this->sessionRepository->replaceSets($sessionExercise->getId(), new ArrayCollection($setLogs));

        return $session;
    }

    // -------------------------------------------------------------- best

    public function testExerciseBestReturnsTheHeaviestWeightAndHighestE1rmIgnoringWarmups(): void
    {
        $this->logFinishedSession(
            $this->bench,
            [[60.0, 12, true], [80.0, 8, false], [85.0, 3, false]],
            '2026-08-03 10:00:00',
        );

        $this->jsonRequest('GET', "/api/stats/exercise/{$this->bench->getId()->getValue()}/best");

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);
        $this->assertSame(['best_weight_kg', 'best_e1rm'], array_keys($data['data']));
        $this->assertEqualsWithDelta(85.0, $data['data']['best_weight_kg'], 0.01);
        $this->assertEqualsWithDelta(101.33, $data['data']['best_e1rm'], 0.01);
    }

    public function testExerciseBestIgnoresUnfinishedSessions(): void
    {
        $this->logFinishedSession($this->bench, [[80.0, 5, false]], '2026-08-03 10:00:00');

        $running = DomainTestHelper::createSession(startedAt: new \DateTimeImmutable('2026-08-04 09:00:00'));
        $this->sessionRepository->save($running);
        $runningExercise = DomainTestHelper::createSessionExercise(
            sessionId: $running->getId(),
            exerciseId: $this->bench->getId(),
        );
        $this->sessionRepository->saveExercise($runningExercise);
        $this->sessionRepository->replaceSets($runningExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(
                sessionExerciseId: $runningExercise->getId(),
                setNumber: 1,
                weightKg: 200.0,
                reps: 1,
            ),
        ]));

        $this->jsonRequest('GET', "/api/stats/exercise/{$this->bench->getId()->getValue()}/best");

        $this->assertEqualsWithDelta(80.0, $this->getResponseData()['data']['best_weight_kg'], 0.01);
    }

    public function testExerciseBestExcludesTheGivenSession(): void
    {
        $session = $this->logFinishedSession($this->bench, [[80.0, 5, false]], '2026-08-03 10:00:00');

        $this->jsonRequest(
            'GET',
            "/api/stats/exercise/{$this->bench->getId()->getValue()}/best"
            . "?exclude_session={$session->getId()->getValue()}",
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->getResponseData()['data']['best_weight_kg']);
        $this->assertNull($this->getResponseData()['data']['best_e1rm']);
    }

    public function testExerciseBestWithoutHistoryReturnsNulls(): void
    {
        $this->jsonRequest('GET', "/api/stats/exercise/{$this->bench->getId()->getValue()}/best");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['best_weight_kg' => null, 'best_e1rm' => null],
            $this->getResponseData()['data'],
        );
    }

    public function testExerciseBestForAnUnknownExerciseReturnsNulls(): void
    {
        $this->jsonRequest('GET', '/api/stats/exercise/' . ExerciseId::generate()->getValue() . '/best');

        $this->assertResponseStatusCodeSame(200);
        $this->assertNull($this->getResponseData()['data']['best_weight_kg']);
    }

    public function testExerciseBestWithAMalformedIdReturns422(): void
    {
        $this->jsonRequest('GET', '/api/stats/exercise/nope/best');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    // ------------------------------------------------------------ series

    public function testExerciseSeriesReturnsOnePointPerSessionWithPrs(): void
    {
        $first = $this->logFinishedSession(
            $this->bench,
            [[60.0, 10, true], [80.0, 8, false], [80.0, 6, false]],
            '2026-08-01 10:00:00',
        );
        $second = $this->logFinishedSession($this->bench, [[82.5, 8, false]], '2026-08-03 10:00:00');
        // a session with warmups only is skipped entirely
        $this->logFinishedSession($this->bench, [[60.0, 10, true]], '2026-08-04 10:00:00');

        $this->jsonRequest('GET', "/api/stats/exercise/{$this->bench->getId()->getValue()}/series");

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];

        $this->assertSame(['points', 'prs'], array_keys($data));
        $this->assertCount(2, $data['points']);
        $this->assertSame(
            [$first->getId()->getValue(), $second->getId()->getValue()],
            array_column($data['points'], 'session_id'),
        );

        $this->assertSame(
            ['session_id', 'date', 'top_set', 'volume', 'e1rm'],
            array_keys($data['points'][0]),
        );
        $this->assertSame(
            (new \DateTimeImmutable('2026-08-01 10:00:00'))->format(\DateTimeInterface::ATOM),
            $data['points'][0]['date'],
        );
        $this->assertEqualsWithDelta(80.0, $data['points'][0]['top_set']['weight_kg'], 0.01);
        $this->assertSame(8, $data['points'][0]['top_set']['reps']);
        $this->assertEqualsWithDelta(80.0 * 8 + 80.0 * 6, $data['points'][0]['volume'], 0.01);
        $this->assertEqualsWithDelta(101.33, $data['points'][0]['e1rm'], 0.01);
        $this->assertEqualsWithDelta(82.5 * 8, $data['points'][1]['volume'], 0.01);
        $this->assertEqualsWithDelta(104.5, $data['points'][1]['e1rm'], 0.01);

        $this->assertCount(2, $data['prs']);
        $this->assertSame(['date', 'weight_kg', 'reps', 'e1rm'], array_keys($data['prs'][0]));
        $this->assertEqualsWithDelta(80.0, $data['prs'][0]['weight_kg'], 0.01);
        $this->assertEqualsWithDelta(82.5, $data['prs'][1]['weight_kg'], 0.01);
    }

    public function testExerciseSeriesWithoutHistoryReturnsEmptyLists(): void
    {
        $this->jsonRequest('GET', "/api/stats/exercise/{$this->bench->getId()->getValue()}/series");

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(['points' => [], 'prs' => []], $this->getResponseData()['data']);
    }

    public function testExerciseSeriesWithAMalformedIdReturns422(): void
    {
        $this->jsonRequest('GET', '/api/stats/exercise/nope/series');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    // --------------------------------------------------- strength levels

    public function testStrengthLevelsWithoutBodyweightIsNotReady(): void
    {
        $this->profileRepository->save(DomainTestHelper::createProfile(sex: 'male'));

        $this->jsonRequest('GET', '/api/stats/strength-levels');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['ready' => false, 'reason' => 'no-bodyweight', 'levels' => []],
            $this->getResponseData()['data'],
        );
    }

    public function testStrengthLevelsWithoutProfileSexIsNotReady(): void
    {
        $this->measurementRepository->save(DomainTestHelper::createMeasurement(type: 'weight', value: 80.0));

        $this->jsonRequest('GET', '/api/stats/strength-levels');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['ready' => false, 'reason' => 'no-profile', 'levels' => []],
            $this->getResponseData()['data'],
        );
    }

    public function testStrengthLevelsClassifiesLiftsWithHistory(): void
    {
        $this->measurementRepository->save(DomainTestHelper::createMeasurement(
            type: 'weight',
            value: 80.0,
            measuredAt: new \DateTimeImmutable('2026-08-01 07:00:00'),
        ));
        $this->profileRepository->save(DomainTestHelper::createProfile(sex: 'male'));
        // 100kg x 5 -> e1rm 116.67 (advanced starts at 118 for an 80kg male)
        $this->logFinishedSession($this->bench, [[100.0, 5, false]], '2026-08-03 10:00:00');

        $this->jsonRequest('GET', '/api/stats/strength-levels');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];

        $this->assertTrue($data['ready']);
        $this->assertEqualsWithDelta(80.0, $data['bodyweight_kg'], 0.01);
        $this->assertSame(
            ['squat', 'bench', 'deadlift', 'ohp', 'row'],
            array_column($data['levels'], 'lift'),
        );

        $levelsByLift = array_column($data['levels'], null, 'lift');

        $this->assertSame(
            ['lift', 'label', 'exercise_id', 'e1rm', 'level', 'next_level', 'progress', 'thresholds'],
            array_keys($levelsByLift['bench']),
        );
        $this->assertSame('Bench Press', $levelsByLift['bench']['label']);
        $this->assertSame($this->bench->getId()->getValue(), $levelsByLift['bench']['exercise_id']);
        $this->assertEqualsWithDelta(116.67, $levelsByLift['bench']['e1rm'], 0.01);
        $this->assertSame('intermediate', $levelsByLift['bench']['level']);
        $this->assertSame('advanced', $levelsByLift['bench']['next_level']);
        $this->assertEqualsWithDelta(0.9506, $levelsByLift['bench']['progress'], 0.0001);
        $this->assertEqualsWithDelta(
            [49.0, 68.0, 91.0, 118.0, 147.0],
            $levelsByLift['bench']['thresholds'],
            0.01,
        );

        // a seeded exercise with no history keeps its thresholds but has no level
        $this->assertSame($this->row->getId()->getValue(), $levelsByLift['row']['exercise_id']);
        $this->assertNull($levelsByLift['row']['e1rm']);
        $this->assertNull($levelsByLift['row']['level']);
        $this->assertNull($levelsByLift['row']['progress']);
        $this->assertEqualsWithDelta([39.0, 56.0, 76.0, 99.0, 124.0], $levelsByLift['row']['thresholds'], 0.01);

        // a lift whose seeded exercise is absent from the catalog
        $this->assertNull($levelsByLift['squat']['exercise_id']);
        $this->assertNull($levelsByLift['squat']['e1rm']);
        $this->assertEqualsWithDelta([66.0, 89.0, 115.0, 145.0, 177.0], $levelsByLift['squat']['thresholds'], 0.01);
    }

    // ---------------------------------------------------- weekly muscles

    public function testWeeklyMusclesUnionsMusclesOfTheLastSevenDays(): void
    {
        $this->freezeClock(self::NOW);

        $this->logFinishedSession($this->bench, [[80.0, 8, false]], '2026-08-03 10:00:00');
        // 10 days ago: outside the window
        $this->logFinishedSession($this->row, [[60.0, 10, false]], '2026-07-25 10:00:00');

        $this->jsonRequest('GET', '/api/stats/weekly-muscles');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['primary' => ['chest'], 'secondary' => ['triceps'], 'session_count' => 1],
            $this->getResponseData()['data'],
        );
    }

    public function testWeeklyMusclesIgnoresExercisesWithoutWorkingSets(): void
    {
        $this->freezeClock(self::NOW);

        $this->logFinishedSession($this->bench, [[60.0, 10, true]], '2026-08-03 10:00:00');

        $this->jsonRequest('GET', '/api/stats/weekly-muscles');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['primary' => [], 'secondary' => [], 'session_count' => 0],
            $this->getResponseData()['data'],
        );
    }

    public function testWeeklyMusclesWithoutSessionsReturnsEmptyLists(): void
    {
        $this->freezeClock(self::NOW);

        $this->jsonRequest('GET', '/api/stats/weekly-muscles');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['primary' => [], 'secondary' => [], 'session_count' => 0],
            $this->getResponseData()['data'],
        );
    }

    // ---------------------------------------------------------- overview

    public function testOverviewReturnsTheCurrentAndPreviousWindowTotals(): void
    {
        $this->freezeClock(self::NOW);

        // current window (last 7 days)
        $this->logFinishedSession(
            $this->bench,
            [[60.0, 10, true], [80.0, 8, false], [100.0, 5, false]],
            '2026-08-04 09:00:00',
            '2026-08-04 09:30:00',
        );
        // previous window (7-14 days ago)
        $this->logFinishedSession($this->bench, [[50.0, 10, false]], '2026-07-25 09:00:00', '2026-07-25 10:00:00');
        // older than both windows
        $this->logFinishedSession($this->bench, [[40.0, 10, false]], '2026-07-01 09:00:00', '2026-07-01 10:00:00');

        $this->jsonRequest('GET', '/api/stats/overview?period=7d');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];

        $this->assertSame(['period', 'current', 'previous', 'cumulative_volume'], array_keys($data));
        $this->assertSame('7d', $data['period']);
        $this->assertSame(
            ['workouts', 'volume_kg', 'reps', 'sets', 'heaviest_kg', 'time_seconds'],
            array_keys($data['current']),
        );

        $this->assertSame(1, $data['current']['workouts']);
        $this->assertEqualsWithDelta(80.0 * 8 + 100.0 * 5, $data['current']['volume_kg'], 0.01);
        $this->assertSame(13, $data['current']['reps']);
        $this->assertSame(2, $data['current']['sets']);
        $this->assertEqualsWithDelta(100.0, $data['current']['heaviest_kg'], 0.01);
        $this->assertSame(30 * 60, $data['current']['time_seconds']);

        $this->assertNotNull($data['previous']);
        $this->assertSame(1, $data['previous']['workouts']);
        $this->assertEqualsWithDelta(500.0, $data['previous']['volume_kg'], 0.01);
    }

    public function testOverviewForAllTimeHasNoPreviousWindowAndCountsEverySession(): void
    {
        $this->freezeClock(self::NOW);

        $this->logFinishedSession($this->bench, [[100.0, 5, false]], '2026-08-04 09:00:00', '2026-08-04 10:00:00');
        $this->logFinishedSession($this->bench, [[40.0, 10, false]], '2026-04-26 09:00:00', '2026-04-26 10:00:00');

        $this->jsonRequest('GET', '/api/stats/overview?period=all');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];

        $this->assertSame('all', $data['period']);
        $this->assertNull($data['previous']);
        $this->assertSame(2, $data['current']['workouts']);
        $this->assertEqualsWithDelta(100.0 * 5 + 40.0 * 10, $data['current']['volume_kg'], 0.01);
    }

    public function testOverviewWithAnUnknownPeriodFallsBackToSevenDays(): void
    {
        $this->freezeClock(self::NOW);

        $this->jsonRequest('GET', '/api/stats/overview?period=nonsense');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('7d', $this->getResponseData()['data']['period']);
    }

    public function testOverviewWithoutAPeriodDefaultsToSevenDays(): void
    {
        $this->freezeClock(self::NOW);

        $this->jsonRequest('GET', '/api/stats/overview');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('7d', $this->getResponseData()['data']['period']);
    }

    public function testOverviewCumulativeVolumeCoversTheWindowAndEndsAtTheCurrentVolume(): void
    {
        $this->freezeClock(self::NOW);

        $this->logFinishedSession($this->bench, [[100.0, 5, false]], '2026-08-02 09:00:00', '2026-08-02 10:00:00');

        $this->jsonRequest('GET', '/api/stats/overview?period=7d');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];
        $points = $data['cumulative_volume'];

        $this->assertCount(8, $points);
        $this->assertSame(['date', 'value'], array_keys($points[0]));
        $this->assertSame('2026-07-28', $points[0]['date']);
        $this->assertSame('2026-08-04', $points[count($points) - 1]['date']);

        $values = array_column($points, 'value');

        for ($index = 1; $index < count($values); $index++) {
            $this->assertGreaterThanOrEqual($values[$index - 1], $values[$index]);
        }

        $this->assertEqualsWithDelta(
            $data['current']['volume_kg'],
            $values[count($values) - 1],
            0.01,
        );
    }

    public function testOverviewWithoutSessionsReturnsZeroTotals(): void
    {
        $this->freezeClock(self::NOW);

        $this->jsonRequest('GET', '/api/stats/overview?period=30d');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData()['data'];

        $this->assertSame('30d', $data['period']);
        $this->assertSame(0, $data['current']['workouts']);
        $this->assertEqualsWithDelta(0.0, $data['current']['volume_kg'], 0.01);
        $this->assertEqualsWithDelta(0.0, $data['current']['heaviest_kg'], 0.01);
        $this->assertSame(0, $data['current']['time_seconds']);
        $this->assertNotNull($data['previous']);
    }
}
