<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Session\Repository;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

final class DoctrineSessionRepository implements SessionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Session $session): void
    {
        if (!$this->entityManager->contains($session)) {
            $this->entityManager->persist($session);
        }

        $this->entityManager->flush();
    }

    public function findById(SessionId $sessionId): ?Session
    {
        return $this->entityManager->find(Session::class, $sessionId->getValue());
    }

    /**
     * @return ArrayCollection<int, Session>
     */
    public function findAll(): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(Session::class, 's')
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Session>
     */
    public function findByTemplateId(WorkoutTemplateId $workoutTemplateId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(Session::class, 's')
            ->where('s.workoutTemplateId = :templateId')
            ->setParameter('templateId', $workoutTemplateId->getValue());

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function delete(Session $session): void
    {
        // Cascade exercises + sets manually: the schema has no FKs.
        foreach ($this->findExercisesBySessionId($session->getId()) as $sessionExercise) {
            $this->removeExerciseWithSets($sessionExercise);
        }

        $managed = $this->entityManager->find(Session::class, $session->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
        }

        $this->entityManager->flush();
    }

    public function saveExercise(SessionExercise $sessionExercise): void
    {
        if (!$this->entityManager->contains($sessionExercise)) {
            $this->entityManager->persist($sessionExercise);
        }

        $this->entityManager->flush();
    }

    /**
     * @param ArrayCollection<int, SessionExercise> $sessionExercises
     */
    public function addExercises(ArrayCollection $sessionExercises): void
    {
        foreach ($sessionExercises as $sessionExercise) {
            if (!$this->entityManager->contains($sessionExercise)) {
                $this->entityManager->persist($sessionExercise);
            }
        }

        $this->entityManager->flush();
    }

    public function findExerciseById(SessionExerciseId $sessionExerciseId): ?SessionExercise
    {
        return $this->entityManager->find(SessionExercise::class, $sessionExerciseId->getValue());
    }

    /**
     * @return ArrayCollection<int, SessionExercise>
     */
    public function findExercisesBySessionId(SessionId $sessionId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('se')
            ->from(SessionExercise::class, 'se')
            ->where('se.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId->getValue())
            ->orderBy('se.sortOrder', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    public function countExercisesBySessionId(SessionId $sessionId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(se.id)')
            ->from(SessionExercise::class, 'se')
            ->where('se.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId->getValue());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countExercisesByExerciseId(ExerciseId $exerciseId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(se.id)')
            ->from(SessionExercise::class, 'se')
            ->where('se.exerciseId = :exerciseId')
            ->setParameter('exerciseId', $exerciseId->getValue());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countSetsBySessionId(SessionId $sessionId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(sl.id)')
            ->from(SetLog::class, 'sl')
            ->join(SessionExercise::class, 'se', Join::WITH, 'se.id = sl.sessionExerciseId')
            ->where('se.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId->getValue());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function deleteExercise(SessionExercise $sessionExercise): void
    {
        $this->removeExerciseWithSets($sessionExercise);
        $this->entityManager->flush();
    }

    /**
     * @return ArrayCollection<int, SetLog>
     */
    public function findSetsBySessionExerciseId(SessionExerciseId $sessionExerciseId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('sl')
            ->from(SetLog::class, 'sl')
            ->where('sl.sessionExerciseId = :sessionExerciseId')
            ->setParameter('sessionExerciseId', $sessionExerciseId->getValue())
            ->orderBy('sl.setNumber', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @param ArrayCollection<int, SetLog> $setLogs
     */
    public function replaceSets(SessionExerciseId $sessionExerciseId, ArrayCollection $setLogs): void
    {
        foreach ($this->findSetsBySessionExerciseId($sessionExerciseId) as $existingSetLog) {
            $this->entityManager->remove($existingSetLog);
        }

        foreach ($setLogs as $setLog) {
            if (!$this->entityManager->contains($setLog)) {
                $this->entityManager->persist($setLog);
            }
        }

        $this->entityManager->flush();
    }

    public function findLatestFinishedExercise(ExerciseId $exerciseId, SessionId $sessionId): ?SessionExercise
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('se')
            ->from(SessionExercise::class, 'se')
            ->join(Session::class, 's', Join::WITH, 's.id = se.sessionId')
            ->where('se.exerciseId = :exerciseId')
            ->andWhere('s.id != :sessionId')
            ->andWhere('s.finishedAt IS NOT NULL')
            ->setParameter('exerciseId', $exerciseId->getValue())
            ->setParameter('sessionId', $sessionId->getValue())
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return ArrayCollection<int, SetLog>
     */
    public function findFinishedWorkingSets(ExerciseId $exerciseId, ?SessionId $sessionId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('sl')
            ->from(SetLog::class, 'sl')
            ->join(SessionExercise::class, 'se', Join::WITH, 'se.id = sl.sessionExerciseId')
            ->join(Session::class, 's', Join::WITH, 's.id = se.sessionId')
            ->where('se.exerciseId = :exerciseId')
            ->andWhere('s.finishedAt IS NOT NULL')
            ->andWhere('sl.isWarmup = :isWarmup')
            ->setParameter('exerciseId', $exerciseId->getValue())
            ->setParameter('isWarmup', false);

        if ($sessionId !== null) {
            $qb->andWhere('s.id != :sessionId')
                ->setParameter('sessionId', $sessionId->getValue());
        }

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Session>
     */
    public function findFinishedSessionsByExerciseId(ExerciseId $exerciseId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT s')
            ->from(Session::class, 's')
            ->join(SessionExercise::class, 'se', Join::WITH, 'se.sessionId = s.id')
            ->where('se.exerciseId = :exerciseId')
            ->andWhere('s.finishedAt IS NOT NULL')
            ->setParameter('exerciseId', $exerciseId->getValue())
            ->orderBy('s.startedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, SessionExercise>
     */
    public function findFinishedExercisesByExerciseId(ExerciseId $exerciseId): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('se')
            ->from(SessionExercise::class, 'se')
            ->join(Session::class, 's', Join::WITH, 's.id = se.sessionId')
            ->where('se.exerciseId = :exerciseId')
            ->andWhere('s.finishedAt IS NOT NULL')
            ->setParameter('exerciseId', $exerciseId->getValue())
            ->orderBy('s.startedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->addOrderBy('se.id', 'ASC');

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    /**
     * @return ArrayCollection<int, Session>
     */
    public function findFinishedSessionsBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): ArrayCollection
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(Session::class, 's')
            ->where('s.finishedAt IS NOT NULL')
            ->orderBy('s.startedAt', 'ASC')
            ->addOrderBy('s.id', 'ASC');

        if ($from !== null) {
            $qb->andWhere('s.startedAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('s.startedAt < :to')
                ->setParameter('to', $to);
        }

        return new ArrayCollection($qb->getQuery()->getResult());
    }

    private function removeExerciseWithSets(SessionExercise $sessionExercise): void
    {
        foreach ($this->findSetsBySessionExerciseId($sessionExercise->getId()) as $setLog) {
            $this->entityManager->remove($setLog);
        }

        $managed = $this->entityManager->find(SessionExercise::class, $sessionExercise->getId()->getValue());

        if ($managed !== null) {
            $this->entityManager->remove($managed);
        }
    }
}
