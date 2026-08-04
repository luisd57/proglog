<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Template\Entity;

use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Id\WorkoutTemplateId;
use PHPUnit\Framework\TestCase;

final class WorkoutTemplateTest extends TestCase
{
    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = WorkoutTemplateId::generate();

        $workoutTemplate = WorkoutTemplate::create(id: $id, name: 'Push Day', sortOrder: 3);

        $this->assertTrue($id->equals($workoutTemplate->getId()));
        $this->assertSame('Push Day', $workoutTemplate->getName());
        $this->assertSame(3, $workoutTemplate->getSortOrder());
        $this->assertNull($workoutTemplate->getArchivedAt());
        $this->assertFalse($workoutTemplate->isArchived());
    }

    public function testCreateTrimsName(): void
    {
        $workoutTemplate = WorkoutTemplate::create(
            id: WorkoutTemplateId::generate(),
            name: '  Push Day  ',
            sortOrder: 0,
        );

        $this->assertSame('Push Day', $workoutTemplate->getName());
    }

    public function testCreateWithBlankNameThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkoutTemplate::create(id: WorkoutTemplateId::generate(), name: '  ', sortOrder: 0);
    }

    public function testCreateWithNegativeSortOrderThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkoutTemplate::create(id: WorkoutTemplateId::generate(), name: 'Push Day', sortOrder: -1);
    }

    public function testRenameReplacesTrimmedName(): void
    {
        $workoutTemplate = WorkoutTemplate::create(
            id: WorkoutTemplateId::generate(),
            name: 'Push Day',
            sortOrder: 0,
        );

        $workoutTemplate->rename(' Push Day A ');

        $this->assertSame('Push Day A', $workoutTemplate->getName());
    }

    public function testRenameWithBlankNameThrowsInvalidArgumentException(): void
    {
        $workoutTemplate = WorkoutTemplate::create(
            id: WorkoutTemplateId::generate(),
            name: 'Push Day',
            sortOrder: 0,
        );

        $this->expectException(\InvalidArgumentException::class);

        $workoutTemplate->rename('');
    }

    public function testArchiveSetsArchivedAtToGivenInstant(): void
    {
        $workoutTemplate = WorkoutTemplate::create(
            id: WorkoutTemplateId::generate(),
            name: 'Push Day',
            sortOrder: 0,
        );
        $now = new \DateTimeImmutable('2026-08-04 10:00:00');

        $workoutTemplate->archive($now);

        $this->assertTrue($workoutTemplate->isArchived());
        $this->assertSame($now, $workoutTemplate->getArchivedAt());
    }
}
