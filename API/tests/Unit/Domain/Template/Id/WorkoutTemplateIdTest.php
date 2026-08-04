<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Template\Id;

use App\Domain\Template\Id\WorkoutTemplateId;
use PHPUnit\Framework\TestCase;

final class WorkoutTemplateIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = WorkoutTemplateId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->getValue(),
        );
    }

    public function testFromStringRoundTrips(): void
    {
        $generated = WorkoutTemplateId::generate();
        $restored = WorkoutTemplateId::fromString($generated->getValue());

        $this->assertTrue($generated->equals($restored));
        $this->assertSame($generated->getValue(), (string) $restored);
    }

    public function testFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkoutTemplateId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $this->assertFalse(WorkoutTemplateId::generate()->equals(WorkoutTemplateId::generate()));
    }
}
