<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Profile\Repository;

use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProfileRepository implements ProfileRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(): ?Profile
    {
        return $this->entityManager->find(Profile::class, Profile::SINGLETON_ID);
    }

    public function save(Profile $profile): void
    {
        if (!$this->entityManager->contains($profile)) {
            $this->entityManager->persist($profile);
        }

        $this->entityManager->flush();
    }
}
