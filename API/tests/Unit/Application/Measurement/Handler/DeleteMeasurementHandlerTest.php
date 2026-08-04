<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Measurement\Handler;

use App\Application\Measurement\Handler\DeleteMeasurementHandler;
use App\Domain\Measurement\Exception\MeasurementNotFoundException;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteMeasurementHandlerTest extends TestCase
{
    private MeasurementRepositoryInterface&MockObject $measurementRepository;
    private DeleteMeasurementHandler $handler;

    protected function setUp(): void
    {
        $this->measurementRepository = $this->createMock(MeasurementRepositoryInterface::class);
        $this->handler = new DeleteMeasurementHandler($this->measurementRepository);
    }

    public function testDeleteExistingMeasurementCallsRepository(): void
    {
        $id = MeasurementId::generate();
        $measurement = DomainTestHelper::createMeasurement(id: $id);

        $this->measurementRepository->method('findById')->willReturn($measurement);
        $this->measurementRepository
            ->expects($this->once())
            ->method('delete')
            ->with($measurement);

        $this->handler->__invoke($id->getValue());
    }

    public function testDeleteUnknownMeasurementThrowsMeasurementNotFoundException(): void
    {
        $this->measurementRepository->method('findById')->willReturn(null);
        $this->measurementRepository->expects($this->never())->method('delete');

        $this->expectException(MeasurementNotFoundException::class);

        $this->handler->__invoke(MeasurementId::generate()->getValue());
    }

    public function testDeleteWithMalformedIdThrowsInvalidArgumentException(): void
    {
        $this->measurementRepository->expects($this->never())->method('delete');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler->__invoke('nope');
    }
}
