<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Id\SetLogId;
use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Id\TemplateExerciseId;
use App\Domain\Template\Id\WorkoutTemplateId;

/**
 * Factory methods for domain objects in controlled states.
 * Tests use these instead of calling constructors directly.
 */
final class DomainTestHelper
{
    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public static function createCustomExercise(
        ?ExerciseId $id = null,
        string $name = 'My Custom Press',
        array $primaryMuscles = ['shoulders'],
        array $secondaryMuscles = ['triceps'],
        ?string $equipment = 'machine',
        ?string $category = 'strength',
        ?string $instructions = null,
    ): Exercise {
        return Exercise::createCustom(
            id: $id ?? ExerciseId::generate(),
            name: $name,
            primaryMuscles: $primaryMuscles,
            secondaryMuscles: $secondaryMuscles,
            equipment: $equipment,
            category: $category,
            instructions: $instructions,
        );
    }

    /**
     * @param array<int, string> $primaryMuscles
     * @param array<int, string> $secondaryMuscles
     */
    public static function createBuiltInExercise(
        ?ExerciseId $id = null,
        string $name = 'Barbell Bench Press',
        array $primaryMuscles = ['chest'],
        array $secondaryMuscles = ['shoulders', 'triceps'],
        ?string $equipment = 'barbell',
        ?string $category = 'strength',
        ?string $instructions = null,
    ): Exercise {
        return Exercise::createBuiltIn(
            id: $id ?? ExerciseId::generate(),
            name: $name,
            primaryMuscles: $primaryMuscles,
            secondaryMuscles: $secondaryMuscles,
            equipment: $equipment,
            category: $category,
            instructions: $instructions,
        );
    }

    public static function createWorkoutTemplate(
        ?WorkoutTemplateId $id = null,
        string $name = 'Push Day',
        int $sortOrder = 0,
    ): WorkoutTemplate {
        return WorkoutTemplate::create(
            id: $id ?? WorkoutTemplateId::generate(),
            name: $name,
            sortOrder: $sortOrder,
        );
    }

    public static function createTemplateExercise(
        ?TemplateExerciseId $id = null,
        ?WorkoutTemplateId $workoutTemplateId = null,
        ?ExerciseId $exerciseId = null,
        int $sortOrder = 0,
        ?int $targetSets = null,
        ?int $targetReps = null,
        ?int $restSeconds = null,
    ): TemplateExercise {
        return TemplateExercise::create(
            id: $id ?? TemplateExerciseId::generate(),
            workoutTemplateId: $workoutTemplateId ?? WorkoutTemplateId::generate(),
            exerciseId: $exerciseId ?? ExerciseId::generate(),
            sortOrder: $sortOrder,
            targetSets: $targetSets,
            targetReps: $targetReps,
            restSeconds: $restSeconds,
        );
    }

    /**
     * Started (running) session; pass $finishedAt for a finished one.
     */
    public static function createSession(
        ?SessionId $id = null,
        ?WorkoutTemplateId $workoutTemplateId = null,
        ?\DateTimeImmutable $startedAt = null,
        ?\DateTimeImmutable $finishedAt = null,
    ): Session {
        $session = Session::start(
            id: $id ?? SessionId::generate(),
            workoutTemplateId: $workoutTemplateId,
            now: $startedAt ?? new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        if ($finishedAt !== null) {
            $session->finish($finishedAt);
        }

        return $session;
    }

    public static function createSessionExercise(
        ?SessionExerciseId $id = null,
        ?SessionId $sessionId = null,
        ?ExerciseId $exerciseId = null,
        int $sortOrder = 0,
    ): SessionExercise {
        return SessionExercise::create(
            id: $id ?? SessionExerciseId::generate(),
            sessionId: $sessionId ?? SessionId::generate(),
            exerciseId: $exerciseId ?? ExerciseId::generate(),
            sortOrder: $sortOrder,
        );
    }

    public static function createSetLog(
        ?SetLogId $id = null,
        ?SessionExerciseId $sessionExerciseId = null,
        int $setNumber = 1,
        float $weightKg = 80.0,
        int $reps = 8,
        bool $isWarmup = false,
        ?string $notes = null,
    ): SetLog {
        return SetLog::create(
            id: $id ?? SetLogId::generate(),
            sessionExerciseId: $sessionExerciseId ?? SessionExerciseId::generate(),
            setNumber: $setNumber,
            weightKg: $weightKg,
            reps: $reps,
            isWarmup: $isWarmup,
            notes: $notes,
        );
    }

    public static function createMeasurement(
        ?MeasurementId $id = null,
        string $type = 'weight',
        float $value = 82.5,
        ?\DateTimeImmutable $measuredAt = null,
    ): Measurement {
        return Measurement::create(
            id: $id ?? MeasurementId::generate(),
            type: $type,
            value: $value,
            measuredAt: $measuredAt ?? new \DateTimeImmutable('2026-08-04 10:00:00'),
        );
    }

    public static function createProfile(
        ?string $sex = null,
        ?\DateTimeImmutable $birthDate = null,
        int $defaultRestSeconds = Profile::DEFAULT_REST_SECONDS,
        ?float $heightCm = null,
    ): Profile {
        $profile = Profile::createDefault();
        $profile->changeSex($sex);
        $profile->changeBirthDate($birthDate);
        $profile->changeDefaultRestSeconds($defaultRestSeconds);
        $profile->changeHeightCm($heightCm);

        return $profile;
    }
}
