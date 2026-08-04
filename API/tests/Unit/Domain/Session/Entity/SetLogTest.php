<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Session\Entity;

use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SetLogId;
use PHPUnit\Framework\TestCase;

final class SetLogTest extends TestCase
{
    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = SetLogId::generate();
        $sessionExerciseId = SessionExerciseId::generate();

        $setLog = SetLog::create(
            id: $id,
            sessionExerciseId: $sessionExerciseId,
            setNumber: 2,
            weightKg: 82.5,
            reps: 6,
            isWarmup: true,
            notes: 'felt heavy',
        );

        $this->assertTrue($id->equals($setLog->getId()));
        $this->assertTrue($sessionExerciseId->equals($setLog->getSessionExerciseId()));
        $this->assertSame(2, $setLog->getSetNumber());
        $this->assertSame(82.5, $setLog->getWeightKg());
        $this->assertSame(6, $setLog->getReps());
        $this->assertTrue($setLog->isWarmup());
        $this->assertSame('felt heavy', $setLog->getNotes());
    }

    public function testCreateDefaultsWarmupFalseAndNotesNull(): void
    {
        $setLog = SetLog::create(
            id: SetLogId::generate(),
            sessionExerciseId: SessionExerciseId::generate(),
            setNumber: 1,
            weightKg: 0.0,
            reps: 0,
        );

        $this->assertFalse($setLog->isWarmup());
        $this->assertNull($setLog->getNotes());
    }

    public function testCreateWithZeroSetNumberThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SetLog::create(
            id: SetLogId::generate(),
            sessionExerciseId: SessionExerciseId::generate(),
            setNumber: 0,
            weightKg: 80.0,
            reps: 8,
        );
    }

    public function testCreateWithNegativeWeightThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SetLog::create(
            id: SetLogId::generate(),
            sessionExerciseId: SessionExerciseId::generate(),
            setNumber: 1,
            weightKg: -1.0,
            reps: 8,
        );
    }

    public function testCreateWithNegativeRepsThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SetLog::create(
            id: SetLogId::generate(),
            sessionExerciseId: SessionExerciseId::generate(),
            setNumber: 1,
            weightKg: 80.0,
            reps: -1,
        );
    }
}
