<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Session\Entity;

use App\Domain\Session\Entity\Session;
use App\Domain\Session\Id\SessionId;
use App\Domain\Template\Id\WorkoutTemplateId;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testStartSetsStartedAtToGivenInstantAndLeavesSessionRunning(): void
    {
        $id = SessionId::generate();
        $workoutTemplateId = WorkoutTemplateId::generate();
        $now = new \DateTimeImmutable('2026-08-04 10:00:00');

        $session = Session::start(id: $id, workoutTemplateId: $workoutTemplateId, now: $now);

        $this->assertTrue($id->equals($session->getId()));
        $this->assertTrue($workoutTemplateId->equals($session->getTemplateId()));
        $this->assertSame($now, $session->getStartedAt());
        $this->assertNull($session->getFinishedAt());
        $this->assertNull($session->getNotes());
        $this->assertFalse($session->isFinished());
    }

    public function testStartWithoutTemplateHasNullTemplateId(): void
    {
        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: null,
            now: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        $this->assertNull($session->getTemplateId());
    }

    public function testFinishSetsFinishedAtToGivenInstant(): void
    {
        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: null,
            now: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );
        $finishedAt = new \DateTimeImmutable('2026-08-04 11:00:00');

        $session->finish($finishedAt);

        $this->assertSame($finishedAt, $session->getFinishedAt());
        $this->assertTrue($session->isFinished());
    }

    public function testFinishOverwritesPreviousFinishedAtIdempotently(): void
    {
        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: null,
            now: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );
        $session->finish(new \DateTimeImmutable('2026-08-04 11:00:00'));
        $laterFinishedAt = new \DateTimeImmutable('2026-08-04 12:00:00');

        $session->finish($laterFinishedAt);

        $this->assertSame($laterFinishedAt, $session->getFinishedAt());
    }

    public function testChangeNotesStoresGivenStringIncludingEmpty(): void
    {
        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: null,
            now: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        $session->changeNotes('felt strong');
        $this->assertSame('felt strong', $session->getNotes());

        $session->changeNotes('');
        $this->assertSame('', $session->getNotes());
    }

    public function testClearTemplateSetsTemplateIdToNull(): void
    {
        $session = Session::start(
            id: SessionId::generate(),
            workoutTemplateId: WorkoutTemplateId::generate(),
            now: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        $session->clearTemplate();

        $this->assertNull($session->getTemplateId());
    }
}
