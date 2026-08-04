<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Measurement\Repository;

use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use App\Domain\Measurement\Repository\MeasurementRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMeasurementRepository implements MeasurementRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Measurement $measurement): void
    {
        if (!$this->entityManager->contains($measurement)) {
            $this->entityManager->persist($measurement);
        }

        $this->entityManager->flush();
    }

    public function findById(MeasurementId $measurementId): ?Measurement
    {
        return $this->entityManager->find(Measurement::class, $measurementId->getValue());
    }

    /**
     * @return ArrayCollection<int, Measurement>
     */
    public function findByType(string $type): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m')
            ->from(Measurement::class, 'm')
            ->where('m.type = :type')
            ->setParameter('type', $type)
            ->orderBy('m.measuredAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Measurement>
     */
    public function findAll(): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m')
            ->from(Measurement::class, 'm')
            ->orderBy('m.measuredAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function findLatestByType(string $type): ?Measurement
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m')
            ->from(Measurement::class, 'm')
            ->where('m.type = :type')
            ->setParameter('type', $type)
            ->orderBy('m.measuredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function delete(Measurement $measurement): void
    {
        $managed = $this->entityManager->find(Measurement::class, $measurement->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
