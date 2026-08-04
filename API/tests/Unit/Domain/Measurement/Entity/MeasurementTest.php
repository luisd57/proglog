<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Measurement\Entity;

use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use PHPUnit\Framework\TestCase;

final class MeasurementTest extends TestCase
{
    public function testCreateSetsAllPropertiesCorrectly(): void
    {
        $id = MeasurementId::generate();
        $measuredAt = new \DateTimeImmutable('2026-06-01 07:30:00');

        $measurement = Measurement::create(
            id: $id,
            type: 'weight',
            value: 82.5,
            measuredAt: $measuredAt,
        );

        $this->assertTrue($id->equals($measurement->getId()));
        $this->assertSame('weight', $measurement->getType());
        $this->assertSame(82.5, $measurement->getValue());
        $this->assertEquals($measuredAt, $measurement->getMeasuredAt());
    }

    public function testCreateAcceptsEveryKnownTypeIncludingTheCamelCaseSuffixes(): void
    {
        $this->assertContains('bicepL', Measurement::TYPES);
        $this->assertContains('calfR', Measurement::TYPES);

        foreach (Measurement::TYPES as $type) {
            $measurement = Measurement::create(
                id: MeasurementId::generate(),
                type: $type,
                value: 42.0,
                measuredAt: new \DateTimeImmutable('2026-06-01 07:30:00'),
            );

            $this->assertSame($type, $measurement->getType());
        }
    }

    public function testCreateWithUnknownTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Measurement::create(
            id: MeasurementId::generate(),
            type: 'mood',
            value: 5.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:30:00'),
        );
    }

    public function testCreateWithTypeInTheWrongCaseThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Measurement::create(
            id: MeasurementId::generate(),
            type: 'bicepl',
            value: 38.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:30:00'),
        );
    }

    public function testCreateWithZeroValueThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Measurement::create(
            id: MeasurementId::generate(),
            type: 'weight',
            value: 0.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:30:00'),
        );
    }

    public function testCreateWithNegativeValueThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Measurement::create(
            id: MeasurementId::generate(),
            type: 'weight',
            value: -1.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:30:00'),
        );
    }
}
