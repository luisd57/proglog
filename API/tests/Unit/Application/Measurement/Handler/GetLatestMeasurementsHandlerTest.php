<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Measurement\Handler;

use App\Application\Measurement\Handler\GetLatestMeasurementsHandler;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetLatestMeasurementsHandlerTest extends TestCase
{
    private MeasurementRepositoryInterface&MockObject $measurementRepository;
    private GetLatestMeasurementsHandler $handler;

    protected function setUp(): void
    {
        $this->measurementRepository = $this->createMock(MeasurementRepositoryInterface::class);
        $this->handler = new GetLatestMeasurementsHandler($this->measurementRepository);
    }

    public function testLatestReturnsTheLastValuePerTypeOfTheAscendingSeries(): void
    {
        // repository returns measured_at ASC, so the last write of a type wins
        $this->measurementRepository->method('findAll')->willReturn(new ArrayCollection([
            DomainTestHelper::createMeasurement(
                type: 'weight',
                value: 82.0,
                measuredAt: new \DateTimeImmutable('2026-06-01 07:00:00'),
            ),
            DomainTestHelper::createMeasurement(
                type: 'weight',
                value: 81.4,
                measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
            ),
            DomainTestHelper::createMeasurement(
                type: 'waist',
                value: 84.0,
                measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
            ),
        ]));

        $this->assertSame(['weight' => 81.4, 'waist' => 84.0], $this->handler->__invoke()->toArray());
    }

    public function testLatestWithoutMeasurementsReturnsAnEmptyMap(): void
    {
        $this->measurementRepository->method('findAll')->willReturn(new ArrayCollection());

        $this->assertSame([], $this->handler->__invoke()->toArray());
    }
}
