<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Profile\Repository;

use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;

final class DoctrineProfileRepositoryTest extends IntegrationTestCase
{
    private ProfileRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ProfileRepositoryInterface::class);
    }

    public function testFindWithoutARowReturnsNull(): void
    {
        $this->assertNull($this->repository->find());
    }

    public function testSaveAndFindRoundTripsTheSingletonRow(): void
    {
        $this->repository->save(DomainTestHelper::createProfile(
            sex: 'male',
            birthDate: new \DateTimeImmutable('1995-04-12'),
            defaultRestSeconds: 90,
            heightCm: 178.5,
        ));
        $this->entityManager->clear();

        $found = $this->repository->find();

        $this->assertNotNull($found);
        $this->assertSame('male', $found->getSex());
        $this->assertSame('1995-04-12', $found->getBirthDate()?->format('Y-m-d'));
        $this->assertSame(90, $found->getDefaultRestSeconds());
        $this->assertSame(178.5, $found->getHeightCm());
    }

    public function testSaveRoundTripsTheDefaultRowWithNullableColumnsEmpty(): void
    {
        $this->repository->save(Profile::createDefault());
        $this->entityManager->clear();

        $found = $this->repository->find();

        $this->assertNotNull($found);
        $this->assertNull($found->getSex());
        $this->assertNull($found->getBirthDate());
        $this->assertNull($found->getHeightCm());
        $this->assertSame(Profile::DEFAULT_REST_SECONDS, $found->getDefaultRestSeconds());
    }

    public function testSaveUpdatesTheExistingSingletonRowInsteadOfInserting(): void
    {
        $this->repository->save(DomainTestHelper::createProfile(sex: 'male', defaultRestSeconds: 90));
        $this->entityManager->clear();

        $found = $this->repository->find();
        $this->assertNotNull($found);
        $found->changeSex('female');
        $found->changeDefaultRestSeconds(150);
        $this->repository->save($found);
        $this->entityManager->clear();

        $reloaded = $this->repository->find();

        $this->assertNotNull($reloaded);
        $this->assertSame('female', $reloaded->getSex());
        $this->assertSame(150, $reloaded->getDefaultRestSeconds());
    }
}
