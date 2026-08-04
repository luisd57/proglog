<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Service\WeeklyMuscleAggregator;
use App\Domain\Stats\ValueObject\SessionMuscles;
use PHPUnit\Framework\TestCase;

final class WeeklyMuscleAggregatorTest extends TestCase
{
    public function testAggregateUnionsMusclesAndCountsDistinctSessions(): void
    {
        $result = WeeklyMuscleAggregator::aggregate([
            new SessionMuscles('session-a', ['chest'], ['triceps']),
            new SessionMuscles('session-a', ['shoulders'], ['triceps']),
            new SessionMuscles('session-b', ['lats'], ['biceps']),
        ]);

        $this->assertSame(['chest', 'shoulders', 'lats'], $result->primary);
        $this->assertSame(['triceps', 'biceps'], $result->secondary);
        $this->assertSame(2, $result->sessionCount);
    }

    public function testAggregateRemovesPrimaryMusclesFromSecondary(): void
    {
        $result = WeeklyMuscleAggregator::aggregate([
            new SessionMuscles('session-a', ['chest'], ['triceps', 'shoulders']),
            new SessionMuscles('session-a', ['triceps'], ['chest']),
        ]);

        $this->assertSame(['chest', 'triceps'], $result->primary);
        $this->assertSame(['shoulders'], $result->secondary);
        $this->assertSame(1, $result->sessionCount);
    }

    public function testAggregatePreservesFirstSeenOrder(): void
    {
        $result = WeeklyMuscleAggregator::aggregate([
            new SessionMuscles('session-a', ['quadriceps', 'glutes'], ['hamstrings']),
            new SessionMuscles('session-b', ['glutes', 'lower back'], ['hamstrings', 'calves']),
        ]);

        $this->assertSame(['quadriceps', 'glutes', 'lower back'], $result->primary);
        $this->assertSame(['hamstrings', 'calves'], $result->secondary);
    }

    public function testAggregateWithoutEntriesReturnsEmptyResult(): void
    {
        $result = WeeklyMuscleAggregator::aggregate([]);

        $this->assertSame([], $result->primary);
        $this->assertSame([], $result->secondary);
        $this->assertSame(0, $result->sessionCount);
    }

    public function testAggregateWithoutSecondaryMusclesReturnsEmptySecondary(): void
    {
        $result = WeeklyMuscleAggregator::aggregate([
            new SessionMuscles('session-a', ['chest'], []),
        ]);

        $this->assertSame(['chest'], $result->primary);
        $this->assertSame([], $result->secondary);
        $this->assertSame(1, $result->sessionCount);
    }
}
