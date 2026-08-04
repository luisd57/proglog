<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Measurement\Repository;

use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineMeasurementRepositoryTest extends IntegrationTestCase
{
    private MeasurementRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(MeasurementRepositoryInterface::class);
    }

    private function seedSeries(): void
    {
        $this->repository->save(DomainTestHelper::createMeasurement(
            type: 'weight',
            value: 82.0,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:00:00'),
        ));
        $this->repository->save(DomainTestHelper::createMeasurement(
            type: 'weight',
            value: 81.4,
            measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
        ));
        $this->repository->save(DomainTestHelper::createMeasurement(
            type: 'waist',
            value: 84.0,
            measuredAt: new \DateTimeImmutable('2026-06-08 07:00:00'),
        ));
    }

    public function testSaveAndFindByIdRoundTrips(): void
    {
        $id = MeasurementId::generate();
        $this->repository->save(DomainTestHelper::createMeasurement(
            id: $id,
            type: 'bicepL',
            value: 38.5,
            measuredAt: new \DateTimeImmutable('2026-06-01 07:00:00'),
        ));
        $this->entityManager->clear();

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertTrue($id->equals($found->getId()));
        $this->assertSame('bicepL', $found->getType());
        $this->assertSame(38.5, $found->getValue());
        $this->assertEquals(new \DateTimeImmutable('2026-06-01 07:00:00'), $found->getMeasuredAt());
    }

    public function testFindByIdWithUnknownIdReturnsNull(): void
    {
        $this->assertNull($this->repository->findById(MeasurementId::generate()));
    }

    public function testFindByTypeReturnsOnlyThatTypeOrderedByMeasuredAtAsc(): void
    {
        $this->seedSeries();

        $result = $this->repository->findByType('weight');

        $this->assertCount(2, $result);
        $this->assertSame(
            [82.0, 81.4],
            array_map(fn (Measurement $measurement) => $measurement->getValue(), $result->toArray()),
        );
    }

    public function testFindByTypeWithoutMeasurementsReturnsAnEmptyCollection(): void
    {
        $this->seedSeries();

        $this->assertCount(0, $this->repository->findByType('bodyfat'));
    }

    public function testFindAllReturnsEveryTypeOrderedByMeasuredAtAsc(): void
    {
        $this->seedSeries();

        $result = $this->repository->findAll();

        $this->assertCount(3, $result);
        $this->assertSame(
            ['weight', 'weight', 'waist'],
            array_map(fn (Measurement $measurement) => $measurement->getType(), $result->toArray()),
        );
        $this->assertSame(
            [82.0, 81.4, 84.0],
            array_map(fn (Measurement $measurement) => $measurement->getValue(), $result->toArray()),
        );
    }

    public function testFindLatestByTypeReturnsTheMostRecentSample(): void
    {
        $this->seedSeries();

        $latest = $this->repository->findLatestByType('weight');

        $this->assertNotNull($latest);
        $this->assertSame(81.4, $latest->getValue());
        $this->assertEquals(new \DateTimeImmutable('2026-06-08 07:00:00'), $latest->getMeasuredAt());
    }

    public function testFindLatestByTypeForANeverMeasuredTypeReturnsNull(): void
    {
        $this->seedSeries();

        $this->assertNull($this->repository->findLatestByType('bodyfat'));
    }

    public function testDeleteRemovesTheRow(): void
    {
        $id = MeasurementId::generate();
        $measurement = DomainTestHelper::createMeasurement(id: $id, type: 'weight', value: 82.0);
        $this->repository->save($measurement);

        $this->repository->delete($measurement);
        $this->entityManager->clear();

        $this->assertNull($this->repository->findById($id));
        $this->assertCount(0, $this->repository->findByType('weight'));
    }

    public function testDeleteAfterClearStillRemovesTheManagedRow(): void
    {
        $id = MeasurementId::generate();
        $measurement = DomainTestHelper::createMeasurement(id: $id, type: 'weight', value: 82.0);
        $this->repository->save($measurement);
        $this->entityManager->clear();

        $this->repository->delete($measurement);

        $this->assertNull($this->repository->findById($id));
    }
}
