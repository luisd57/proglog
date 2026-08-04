<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Session\Id;

use App\Domain\Session\Id\SessionId;
use PHPUnit\Framework\TestCase;

final class SessionIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = SessionId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $id->getValue(),
        );
    }

    public function testFromStringRoundTrips(): void
    {
        $generated = SessionId::generate();
        $restored = SessionId::fromString($generated->getValue());

        $this->assertTrue($generated->equals($restored));
        $this->assertSame($generated->getValue(), (string) $restored);
    }

    public function testFromStringWithInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SessionId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsFalseForDifferentIds(): void
    {
        $this->assertFalse(SessionId::generate()->equals(SessionId::generate()));
    }
}
