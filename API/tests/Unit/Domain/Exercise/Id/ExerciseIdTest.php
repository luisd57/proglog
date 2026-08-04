<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exercise\Id;

use App\Domain\Exercise\Id\ExerciseId;
use PHPUnit\Framework\TestCase;

final class ExerciseIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = ExerciseId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->getValue(),
        );
    }

    public function testFromStringRoundTrips(): void
    {
        $generated = ExerciseId::generate();
        $restored = ExerciseId::fromString($generated->getValue());

        $this->assertTrue($generated->equals($restored));
        $this->assertSame($generated->getValue(), (string) $restored);
    }

    public function testFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExerciseId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $this->assertFalse(ExerciseId::generate()->equals(ExerciseId::generate()));
    }
}
