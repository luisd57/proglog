<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Measurement;

use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class MeasurementControllerTest extends ApiTestCase
{
    private MeasurementRepositoryInterface $measurementRepository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var MeasurementRepositoryInterface $measurementRepository */
        $measurementRepository = self::getContainer()->get(MeasurementRepositoryInterface::class);
        $this->measurementRepository = $measurementRepository;
    }

    private function seedSeries(): void
    {
        $this->measurementRepository->save(DomainTestHelper::createMeasurement(
            type: 'weight',
            value: 82.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:00:00'),
        ));
        $this->measurementRepository->save(DomainTestHelper::createMeasurement(
            type: 'weight',
            value: 81.4,
            measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
        ));
        $this->measurementRepository->save(DomainTestHelper::createMeasurement(
            type: 'waist',
            value: 84.5,
            measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
        ));
    }

    public function testSeriesWithoutTypeReturns422(): void
    {
        $this->jsonRequest('GET', '/api/measurements');

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('type', $data['error']['details']);
    }

    public function testSeriesWithUnknownTypeReturns422(): void
    {
        $this->jsonRequest('GET', '/api/measurements?type=mood');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testSeriesReturnsOneTypeOrderedByMeasuredAtAsc(): void
    {
        $this->seedSeries();

        $this->jsonRequest('GET', '/api/measurements?type=weight');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertTrue($data['success']);

        $measurements = $data['data']['measurements'];
        $this->assertCount(2, $measurements);
        $this->assertSame(['id', 'type', 'value', 'measured_at'], array_keys($measurements[0]));
        $this->assertSame(['weight', 'weight'], array_column($measurements, 'type'));
        $this->assertEquals([82.0, 81.4], array_column($measurements, 'value'));
        $this->assertSame(
            (new \DateTimeImmutable('2026-06-01 07:00:00'))->format(\DateTimeInterface::ATOM),
            $measurements[0]['measured_at'],
        );
    }

    public function testSeriesForANeverMeasuredTypeReturnsAnEmptyList(): void
    {
        $this->seedSeries();

        $this->jsonRequest('GET', '/api/measurements?type=bodyfat');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([], $this->getResponseData()['data']['measurements']);
    }

    public function testLatestReturnsTheLatestValuePerType(): void
    {
        $this->seedSeries();

        $this->jsonRequest('GET', '/api/measurements/latest');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame(
            ['weight' => 81.4, 'waist' => 84.5],
            $this->getResponseData()['data']['latest'],
        );
    }

    public function testLatestWithoutMeasurementsReturnsAnEmptyObject(): void
    {
        $this->jsonRequest('GET', '/api/measurements/latest');

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('"latest":{}', $this->client->getResponse()->getContent());
    }

    public function testCreateReturns201WithTheMeasurement(): void
    {
        $this->jsonRequest('POST', '/api/measurements', [
            'type' => 'weight',
            'value' => 82.5,
            'measured_at' => '2026-06-01T07:30:00+00:00',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $measurement = $this->getResponseData()['data']['measurement'];
        $this->assertSame('weight', $measurement['type']);
        $this->assertSame(82.5, $measurement['value']);
        $this->assertSame('2026-06-01T07:30:00+00:00', $measurement['measured_at']);
        $this->assertNotNull(MeasurementId::fromString($measurement['id']));

        $this->jsonRequest('GET', '/api/measurements?type=weight');
        $this->assertCount(1, $this->getResponseData()['data']['measurements']);
    }

    public function testCreateWithoutMeasuredAtUsesTheServerClock(): void
    {
        $this->freezeClock('2026-08-04T10:00:00+00:00');

        $this->jsonRequest('POST', '/api/measurements', ['type' => 'bodyfat', 'value' => 14.2]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(
            '2026-08-04T10:00:00+00:00',
            $this->getResponseData()['data']['measurement']['measured_at'],
        );
    }

    public function testCreateAcceptsAnIntegerValue(): void
    {
        $this->jsonRequest('POST', '/api/measurements', ['type' => 'waist', 'value' => 84]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertEquals(84, $this->getResponseData()['data']['measurement']['value']);
    }

    public function testCreateWithUnknownTypeReturns422(): void
    {
        $this->jsonRequest('POST', '/api/measurements', ['type' => 'mood', 'value' => 5]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testCreateWithNonPositiveValueReturns422(): void
    {
        $this->jsonRequest('POST', '/api/measurements', ['type' => 'weight', 'value' => 0]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testCreateWithoutValueReturns422(): void
    {
        $this->jsonRequest('POST', '/api/measurements', ['type' => 'weight']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('value', $this->getResponseData()['error']['details']);
    }

    public function testCreateWithoutTypeReturns422(): void
    {
        $this->jsonRequest('POST', '/api/measurements', ['value' => 82.5]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('type', $this->getResponseData()['error']['details']);
    }

    public function testCreateWithAMalformedMeasuredAtReturns422(): void
    {
        $this->jsonRequest('POST', '/api/measurements', [
            'type' => 'weight',
            'value' => 82.5,
            'measured_at' => 'not-a-date',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testDeleteReturns204AndRemovesTheMeasurement(): void
    {
        $measurement = DomainTestHelper::createMeasurement(type: 'weight', value: 82.0);
        $this->measurementRepository->save($measurement);

        $this->jsonRequest('DELETE', '/api/measurements/' . $measurement->getId()->getValue());

        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/measurements?type=weight');
        $this->assertSame([], $this->getResponseData()['data']['measurements']);
    }

    public function testDeleteWithUnknownIdReturns404(): void
    {
        $this->jsonRequest('DELETE', '/api/measurements/' . MeasurementId::generate()->getValue());

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('MEASUREMENT_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testDeleteWithAMalformedIdReturns422(): void
    {
        $this->jsonRequest('DELETE', '/api/measurements/nope');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }
}
