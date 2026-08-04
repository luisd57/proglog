<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Stats\Handler;

use App\Application\Stats\DTO\Output\StrengthLevelEntryOutputDTO;
use App\Application\Stats\Handler\GetStrengthLevelsHandler;
use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetStrengthLevelsHandlerTest extends TestCase
{
    private MeasurementRepositoryInterface&MockObject $measurementRepository;
    private ProfileRepositoryInterface&MockObject $profileRepository;
    private ExerciseRepositoryInterface&MockObject $exerciseRepository;
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private GetStrengthLevelsHandler $handler;

    /** @var array<string, Exercise> */
    private array $catalogByName = [];

    /** @var array<string, ArrayCollection> */
    private array $workingSetsByExerciseId = [];

    protected function setUp(): void
    {
        $this->measurementRepository = $this->createMock(MeasurementRepositoryInterface::class);
        $this->profileRepository = $this->createMock(ProfileRepositoryInterface::class);
        $this->exerciseRepository = $this->createMock(ExerciseRepositoryInterface::class);
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);
        $this->handler = new GetStrengthLevelsHandler(
            $this->measurementRepository,
            $this->profileRepository,
            $this->exerciseRepository,
            $this->sessionRepository,
        );

        $this->exerciseRepository
            ->method('findByName')
            ->willReturnCallback(fn (string $name): ?Exercise => $this->catalogByName[$name] ?? null);
        $this->sessionRepository
            ->method('findFinishedWorkingSets')
            ->willReturnCallback(
                fn ($exerciseId): ArrayCollection => $this->workingSetsByExerciseId[$exerciseId->getValue()]
                    ?? new ArrayCollection()
            );
    }

    private function givenBodyweight(float $value): void
    {
        $this->measurementRepository
            ->method('findLatestByType')
            ->with('weight')
            ->willReturn(DomainTestHelper::createMeasurement(type: 'weight', value: $value));
    }

    private function givenProfile(?Profile $profile): void
    {
        $this->profileRepository->method('find')->willReturn($profile);
    }

    /**
     * @param array<int, array{0: float, 1: int}> $workingSets
     */
    private function givenSeededExercise(string $name, array $workingSets = []): Exercise
    {
        $exercise = DomainTestHelper::createBuiltInExercise(name: $name);
        $this->catalogByName[$name] = $exercise;

        $setLogs = [];

        foreach ($workingSets as $index => $set) {
            $setLogs[] = DomainTestHelper::createSetLog(
                setNumber: $index + 1,
                weightKg: $set[0],
                reps: $set[1],
            );
        }

        $this->workingSetsByExerciseId[$exercise->getId()->getValue()] = new ArrayCollection($setLogs);

        return $exercise;
    }

    private static function entryFor(string $lift, StrengthLevelEntryOutputDTO ...$entries): StrengthLevelEntryOutputDTO
    {
        foreach ($entries as $entry) {
            if ($entry->lift === $lift) {
                return $entry;
            }
        }

        throw new \RuntimeException("No entry for lift {$lift}.");
    }

    public function testLevelsWithoutABodyweightMeasurementReportsNotReady(): void
    {
        $this->measurementRepository->method('findLatestByType')->willReturn(null);

        $result = $this->handler->__invoke();

        $this->assertFalse($result->ready);
        $this->assertSame('no-bodyweight', $result->reason);
        $this->assertSame([], $result->levels);
        $this->assertSame(
            ['ready' => false, 'reason' => 'no-bodyweight', 'levels' => []],
            $result->toArray(),
        );
    }

    public function testLevelsWithoutAProfileRowReportsNotReady(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(null);

        $result = $this->handler->__invoke();

        $this->assertFalse($result->ready);
        $this->assertSame('no-profile', $result->reason);
    }

    public function testLevelsWithoutASexInTheProfileReportsNotReady(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: null));

        $result = $this->handler->__invoke();

        $this->assertFalse($result->ready);
        $this->assertSame('no-profile', $result->reason);
    }

    public function testLevelsComputesTheLevelOfLiftsWithHistory(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'male'));
        // 100kg x 5 -> e1rm 116.67; male bench @80kg: advanced starts at 118
        $bench = $this->givenSeededExercise('Barbell Bench Press - Medium Grip', [[100.0, 5]]);

        $result = $this->handler->__invoke();

        $this->assertTrue($result->ready);
        $this->assertSame(80.0, $result->bodyweightKg);
        $this->assertCount(5, $result->levels);

        $benchEntry = self::entryFor('bench', ...$result->levels);
        $this->assertSame('Bench Press', $benchEntry->label);
        $this->assertSame($bench->getId()->getValue(), $benchEntry->exerciseId);
        $this->assertEqualsWithDelta(116.67, $benchEntry->e1rm, 0.01);
        $this->assertSame('intermediate', $benchEntry->level);
        $this->assertSame('advanced', $benchEntry->nextLevel);
        $this->assertSame([49.0, 68.0, 91.0, 118.0, 147.0], $benchEntry->thresholds);
    }

    public function testLevelsWithoutTheSeededExerciseStillReturnsThresholds(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'male'));

        $result = $this->handler->__invoke();

        $squatEntry = self::entryFor('squat', ...$result->levels);

        $this->assertNull($squatEntry->exerciseId);
        $this->assertNull($squatEntry->e1rm);
        $this->assertNull($squatEntry->level);
        $this->assertNull($squatEntry->nextLevel);
        $this->assertNull($squatEntry->progress);
        $this->assertSame([66.0, 89.0, 115.0, 145.0, 177.0], $squatEntry->thresholds);
    }

    public function testLevelsWithASeededExerciseButNoHistoryReturnsANullLevel(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'male'));
        $deadlift = $this->givenSeededExercise('Barbell Deadlift');

        $result = $this->handler->__invoke();

        $deadliftEntry = self::entryFor('deadlift', ...$result->levels);

        $this->assertSame($deadlift->getId()->getValue(), $deadliftEntry->exerciseId);
        $this->assertNull($deadliftEntry->e1rm);
        $this->assertNull($deadliftEntry->level);
        $this->assertSame([79.0, 103.0, 132.0, 164.0, 199.0], $deadliftEntry->thresholds);
    }

    public function testLevelsUsesTheFemaleTableForAFemaleProfile(): void
    {
        $this->givenBodyweight(60.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'female'));
        $this->givenSeededExercise('Barbell Squat', [[80.0, 5]]);

        $result = $this->handler->__invoke();

        $squatEntry = self::entryFor('squat', ...$result->levels);

        // female squat @60kg
        $this->assertSame([31.0, 47.0, 66.0, 89.0, 113.0], $squatEntry->thresholds);
        $this->assertEqualsWithDelta(93.33, $squatEntry->e1rm, 0.01);
        $this->assertSame('advanced', $squatEntry->level);
        $this->assertSame('elite', $squatEntry->nextLevel);
    }

    public function testLevelsTakesTheFirstMatchingSeededExerciseName(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'male'));
        // the standard lists 'Barbell Squat' first, then 'Barbell Full Squat'
        $fullSquat = $this->givenSeededExercise('Barbell Full Squat', [[100.0, 5]]);
        $squat = $this->givenSeededExercise('Barbell Squat', [[140.0, 5]]);

        $result = $this->handler->__invoke();

        $squatEntry = self::entryFor('squat', ...$result->levels);

        $this->assertSame($squat->getId()->getValue(), $squatEntry->exerciseId);
        $this->assertNotSame($fullSquat->getId()->getValue(), $squatEntry->exerciseId);
        $this->assertEqualsWithDelta(163.33, $squatEntry->e1rm, 0.01);
    }

    public function testLevelsSerialisesTheSnakeCasePayload(): void
    {
        $this->givenBodyweight(80.0);
        $this->givenProfile(DomainTestHelper::createProfile(sex: 'male'));
        $this->givenSeededExercise('Barbell Bench Press - Medium Grip', [[100.0, 5]]);

        $payload = $this->handler->__invoke()->toArray();

        $this->assertTrue($payload['ready']);
        $this->assertSame(80.0, $payload['bodyweight_kg']);
        $this->assertSame(
            ['squat', 'bench', 'deadlift', 'ohp', 'row'],
            array_column($payload['levels'], 'lift'),
        );
        $this->assertSame(
            ['lift', 'label', 'exercise_id', 'e1rm', 'level', 'next_level', 'progress', 'thresholds'],
            array_keys($payload['levels'][0]),
        );
    }
}
