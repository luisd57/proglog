<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Session\Entity;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use PHPUnit\Framework\TestCase;

final class SessionExerciseTest extends TestCase
{
    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = SessionExerciseId::generate();
        $sessionId = SessionId::generate();
        $exerciseId = ExerciseId::generate();

        $sessionExercise = SessionExercise::create(
            id: $id,
            sessionId: $sessionId,
            exerciseId: $exerciseId,
            sortOrder: 1,
        );

        $this->assertTrue($id->equals($sessionExercise->getId()));
        $this->assertTrue($sessionId->equals($sessionExercise->getSessionId()));
        $this->assertTrue($exerciseId->equals($sessionExercise->getExerciseId()));
        $this->assertSame(1, $sessionExercise->getSortOrder());
        $this->assertNull($sessionExercise->getNotes());
    }

    public function testCreateWithNegativeSortOrderThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SessionExercise::create(
            id: SessionExerciseId::generate(),
            sessionId: SessionId::generate(),
            exerciseId: ExerciseId::generate(),
            sortOrder: -1,
        );
    }

    public function testChangeNotesStoresGivenString(): void
    {
        $sessionExercise = SessionExercise::create(
            id: SessionExerciseId::generate(),
            sessionId: SessionId::generate(),
            exerciseId: ExerciseId::generate(),
            sortOrder: 0,
        );

        $sessionExercise->changeNotes('slow eccentric');

        $this->assertSame('slow eccentric', $sessionExercise->getNotes());
    }

    public function testBelongsToSessionComparesSessionIds(): void
    {
        $sessionId = SessionId::generate();
        $sessionExercise = SessionExercise::create(
            id: SessionExerciseId::generate(),
            sessionId: $sessionId,
            exerciseId: ExerciseId::generate(),
            sortOrder: 0,
        );

        $this->assertTrue($sessionExercise->belongsToSession($sessionId));
        $this->assertFalse($sessionExercise->belongsToSession(SessionId::generate()));
    }
}
