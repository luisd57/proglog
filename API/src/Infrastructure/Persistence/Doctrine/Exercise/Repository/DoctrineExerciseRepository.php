<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Exercise\Repository;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class DoctrineExerciseRepository implements ExerciseRepositoryInterface
{
    /** @var EntityRepository<Exercise> */
    private EntityRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->repository = $entityManager->getRepository(Exercise::class);
    }

    public function save(Exercise $exercise): void
    {
        if (!$this->entityManager->contains($exercise)) {
            $this->entityManager->persist($exercise);
        }

        $this->entityManager->flush();
    }

    /**
     * @param ArrayCollection<int, Exercise> $exercises
     */
    public function saveAll(ArrayCollection $exercises): void
    {
        foreach ($exercises as $exercise) {
            if (!$this->entityManager->contains($exercise)) {
                $this->entityManager->persist($exercise);
            }
        }

        $this->entityManager->flush();
    }

    public function findById(ExerciseId $id): ?Exercise
    {
        return $this->entityManager->find(Exercise::class, $id->getValue());
    }

    public function findByName(string $name): ?Exercise
    {
        return $this->repository->findOneBy(['name' => $name]);
    }

    /**
     * @param array<int, string> $tokens
     *
     * @return ArrayCollection<int, Exercise>
     */
    public function search(array $tokens, ?string $muscle, ?string $equipment): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(Exercise::class, 'e')
            ->orderBy('e.name', 'ASC');

        foreach (array_values($tokens) as $tokenIndex => $token) {
            $qb->andWhere("LOWER(e.name) LIKE :token{$tokenIndex}")
                ->setParameter("token{$tokenIndex}", '%' . mb_strtolower($token) . '%');
        }

        if ($equipment !== null) {
            $qb->andWhere('e.equipment = :equipment')
                ->setParameter('equipment', $equipment);
        }

        /** @var array<int, Exercise> $exercises */
        $exercises = $qb->getQuery()->getResult();

        // Muscles are JSON string arrays; exact-element matching is done in PHP
        // on the hydrated entities (small catalog, single-user tool).
        if ($muscle !== null) {
            $exercises = array_values(array_filter(
                $exercises,
                fn (Exercise $exercise): bool => $exercise->targetsMuscle($muscle),
            ));
        }

        return new ArrayCollection($exercises);
    }

    public function countBuiltIn(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from(Exercise::class, 'e')
            ->where('e.isCustom = :isCustom')
            ->setParameter('isCustom', false);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function delete(Exercise $exercise): void
    {
        $managed = $this->entityManager->find(Exercise::class, $exercise->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
            $this->entityManager->flush();
        }
    }
}
