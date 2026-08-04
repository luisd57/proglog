<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Stats\Service;

use App\Domain\Stats\Service\E1rmCalculator;
use PHPUnit\Framework\TestCase;

final class E1rmCalculatorTest extends TestCase
{
    public function testSingleRepReturnsSlightlyMoreThanTheWeight(): void
    {
        // 100kg x (1 + 1/30) = 103.33
        $this->assertEqualsWithDelta(103.33, E1rmCalculator::epley1Rm(100.0, 1), 0.01);
    }

    public function testTenRepsUsesTheEpleyFormula(): void
    {
        // 100kg x (1 + 10/30) = 133.33
        $this->assertEqualsWithDelta(133.33, E1rmCalculator::epley1Rm(100.0, 10), 0.01);
    }

    public function testZeroRepsReturnsZero(): void
    {
        $this->assertSame(0.0, E1rmCalculator::epley1Rm(100.0, 0));
    }

    public function testNegativeRepsReturnsZero(): void
    {
        $this->assertSame(0.0, E1rmCalculator::epley1Rm(100.0, -3));
    }

    public function testFractionalWeightIsCarriedThrough(): void
    {
        // 82.5kg x (1 + 8/30) = 104.5
        $this->assertEqualsWithDelta(104.5, E1rmCalculator::epley1Rm(82.5, 8), 0.01);
    }

    public function testZeroWeightReturnsZero(): void
    {
        $this->assertSame(0.0, E1rmCalculator::epley1Rm(0.0, 8));
    }

    public function testThirtyRepsDoublesTheWeight(): void
    {
        $this->assertEqualsWithDelta(200.0, E1rmCalculator::epley1Rm(100.0, 30), 0.01);
    }
}
