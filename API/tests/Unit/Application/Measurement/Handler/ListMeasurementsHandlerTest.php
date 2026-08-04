<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Measurement\Handler;

use App\Application\Measurement\DTO\Output\MeasurementOutputDTO;
use App\Application\Measurement\Handler\ListMeasurementsHandler;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListMeasurementsHandlerTest extends TestCase
{
    private MeasurementRepositoryInterface&MockObject $measurementRepository;
    private ListMeasurementsHandler $handler;

    protected function setUp(): void
    {
        $this->measurementRepository = $this->createMock(MeasurementRepositoryInterface::class);
        $this->handler = new ListMeasurementsHandler($this->measurementRepository);
    }

    public function testListMapsTheSeriesOfOneTypeInRepositoryOrder(): void
    {
        $this->measurementRepository
            ->expects($this->once())
            ->method('findByType')
            ->with('weight')
            ->willReturn(new ArrayCollection([
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
            ]));

        $result = $this->handler->__invoke('weight');

        $this->assertCount(2, $result);
        $this->assertSame(
            [82.0, 81.4],
            array_map(fn (MeasurementOutputDTO $dto) => $dto->value, $result->toArray()),
        );
        $this->assertSame('weight', $result->get(0)->type);
        $this->assertEquals(new \DateTimeImmutable('2026-06-01 07:00:00'), $result->get(0)->measuredAt);
    }

    public function testListWithUnknownTypeThrowsInvalidArgumentException(): void
    {
        $this->measurementRepository->expects($this->never())->method('findByType');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('mood');
    }

    public function testListWithoutMeasurementsReturnsAnEmptyCollection(): void
    {
        $this->measurementRepository->method('findByType')->willReturn(new ArrayCollection());

        $this->assertCount(0, $this->handler->__invoke('bodyfat'));
    }
}
