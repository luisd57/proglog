<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Measurement\Handler;

use App\Application\Measurement\DTO\Input\CreateMeasurementInputDTO;
use App\Application\Measurement\Handler\CreateMeasurementHandler;
use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateMeasurementHandlerTest extends TestCase
{
    private MeasurementRepositoryInterface&MockObject $measurementRepository;
    private MockClock $clock;
    private CreateMeasurementHandler $handler;
    private ?Measurement $savedMeasurement = null;

    protected function setUp(): void
    {
        $this->measurementRepository = $this->createMock(MeasurementRepositoryInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-08-04 10:00:00'));
        $this->handler = new CreateMeasurementHandler($this->measurementRepository, $this->clock);

        $this->measurementRepository
            ->method('save')
            ->willReturnCallback(function (Measurement $measurement): void {
                $this->savedMeasurement = $measurement;
            });
    }

    public function testCreatePersistsTheMeasurementAndReturnsIt(): void
    {
        $result = $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'weight',
            value: 82.5,
            measuredAt: '2026-06-01T07:30:00+00:00',
        ));

        $this->assertNotNull($this->savedMeasurement);
        $this->assertSame('weight', $result->type);
        $this->assertSame(82.5, $result->value);
        $this->assertSame($this->savedMeasurement->getId()->getValue(), $result->id);
        $this->assertEquals(new \DateTimeImmutable('2026-06-01T07:30:00+00:00'), $result->measuredAt);
    }

    public function testCreateWithoutMeasuredAtFallsBackToTheClock(): void
    {
        $result = $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'waist',
            value: 84.0,
            measuredAt: null,
        ));

        $this->assertEquals(new \DateTimeImmutable('2026-08-04 10:00:00'), $result->measuredAt);
    }

    public function testCreateWithUnknownTypeThrowsInvalidArgumentExceptionAndSavesNothing(): void
    {
        $this->measurementRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'mood',
            value: 5.0,
            measuredAt: null,
        ));
    }

    public function testCreateWithNonPositiveValueThrowsInvalidArgumentException(): void
    {
        $this->measurementRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'weight',
            value: 0.0,
            measuredAt: null,
        ));
    }

    public function testCreateWithMalformedMeasuredAtThrowsInvalidArgumentException(): void
    {
        $this->measurementRepository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'weight',
            value: 82.5,
            measuredAt: 'not-a-date',
        ));
    }

    public function testCreateSerialisesMeasuredAtAsAtomInTheOutputArray(): void
    {
        $result = $this->handler->__invoke(new CreateMeasurementInputDTO(
            type: 'weight',
            value: 82.5,
            measuredAt: '2026-06-01T07:30:00+00:00',
        ));

        $this->assertSame(
            [
                'id' => $result->id,
                'type' => 'weight',
                'value' => 82.5,
                'measured_at' => '2026-06-01T07:30:00+00:00',
            ],
            $result->toArray(),
        );
    }
}
