<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Template\Repository;

use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineWorkoutTemplateRepository implements WorkoutTemplateRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(WorkoutTemplate $workoutTemplate): void
    {
        if (!$this->entityManager->contains($workoutTemplate)) {
            $this->entityManager->persist($workoutTemplate);
        }

        $this->entityManager->flush();
    }

    public function findById(WorkoutTemplateId $workoutTemplateId): ?WorkoutTemplate
    {
        return $this->entityManager->find(WorkoutTemplate::class, $workoutTemplateId->getValue());
    }

    /**
     * @return ArrayCollection<int, WorkoutTemplate>
     */
    public function findAllActive(): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(WorkoutTemplate::class, 't')
            ->where('t.archivedAt IS NULL')
            ->orderBy('t.sortOrder', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function findHighestSortOrder(): ?int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('MAX(t.sortOrder)')
            ->from(WorkoutTemplate::class, 't');

        $highestSortOrder = $qb->getQuery()->getSingleScalarResult();

        return $highestSortOrder === null ? null : (int) $highestSortOrder;
    }

    /**
     * @return ArrayCollection<int, TemplateExercise>
     */
    public function findExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('te')
            ->from(TemplateExercise::class, 'te')
            ->where('te.workoutTemplateId = :templateId')
            ->setParameter('templateId', $workoutTemplateId->getValue())
            ->orderBy('te.sortOrder', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function countExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(te.id)')
            ->from(TemplateExercise::class, 'te')
            ->where('te.workoutTemplateId = :templateId')
            ->setParameter('templateId', $workoutTemplateId->getValue());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param ArrayCollection<int, TemplateExercise> $templateExercises
     */
    public function addExercises(ArrayCollection $templateExercises): void
    {
        foreach ($templateExercises as $templateExercise) {
            if (!$this->entityManager->contains($templateExercise)) {
                $this->entityManager->persist($templateExercise);
            }
        }

        $this->entityManager->flush();
    }

    public function deleteExercisesByTemplateId(WorkoutTemplateId $workoutTemplateId): void
    {
        // Load-and-remove (not bulk DQL DELETE) keeps the identity map
        // consistent within the request; templates hold few lines.
        foreach ($this->findExercisesByTemplateId($workoutTemplateId) as $templateExercise) {
            $this->entityManager->remove($templateExercise);
        }

        $this->entityManager->flush();
    }

    public function delete(WorkoutTemplate $workoutTemplate): void
    {
        foreach ($this->findExercisesByTemplateId($workoutTemplate->getId()) as $templateExercise) {
            $this->entityManager->remove($templateExercise);
        }

        $managed = $this->entityManager->find(WorkoutTemplate::class, $workoutTemplate->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
        }

        $this->entityManager->flush();
    }
}
