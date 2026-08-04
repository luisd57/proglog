<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Session\Repository;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionId;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use Doctrine\Common\Collections\ArrayCollection;

final class DoctrineSessionRepositoryTest extends IntegrationTestCase
{
    private SessionRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(SessionRepositoryInterface::class);
    }

    public function testSaveAndFindByIdRoundTrips(): void
    {
        $id = SessionId::generate();
        $workoutTemplateId = WorkoutTemplateId::generate();
        $session = DomainTestHelper::createSession(
            id: $id,
            workoutTemplateId: $workoutTemplateId,
            startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );
        $session->changeNotes('good day');
        $this->repository->save($session);
        $this->entityManager->clear();

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertTrue($id->equals($found->getId()));
        $this->assertTrue($workoutTemplateId->equals($found->getTemplateId()));
        $this->assertEquals(new \DateTimeImmutable('2026-08-04 10:00:00'), $found->getStartedAt());
        $this->assertNull($found->getFinishedAt());
        $this->assertSame('good day', $found->getNotes());
    }

    public function testSaveRoundTripsNullTemplateAndFinishedAt(): void
    {
        $id = SessionId::generate();
        $this->repository->save(DomainTestHelper::createSession(
            id: $id,
            startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-04 11:00:00'),
        ));
        $this->entityManager->clear();

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertNull($found->getTemplateId());
        $this->assertEquals(new \DateTimeImmutable('2026-08-04 11:00:00'), $found->getFinishedAt());
        $this->assertTrue($found->isFinished());
    }

    public function testFindAllOrdersByStartedAtDesc(): void
    {
        $older = DomainTestHelper::createSession(startedAt: new \DateTimeImmutable('2026-08-01 10:00:00'));
        $newest = DomainTestHelper::createSession(startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'));
        $middle = DomainTestHelper::createSession(startedAt: new \DateTimeImmutable('2026-08-02 10:00:00'));

        $this->repository->save($older);
        $this->repository->save($newest);
        $this->repository->save($middle);

        $result = $this->repository->findAll();

        $this->assertSame(
            [
                $newest->getId()->getValue(),
                $middle->getId()->getValue(),
                $older->getId()->getValue(),
            ],
            $result->map(fn (Session $session) => $session->getId()->getValue())->toArray(),
        );
    }

    public function testFindByTemplateIdReturnsOnlyReferencingSessions(): void
    {
        $workoutTemplateId = WorkoutTemplateId::generate();
        $referencing = DomainTestHelper::createSession(workoutTemplateId: $workoutTemplateId);
        $blank = DomainTestHelper::createSession();

        $this->repository->save($referencing);
        $this->repository->save($blank);

        $result = $this->repository->findByTemplateId($workoutTemplateId);

        $this->assertCount(1, $result);
        $this->assertTrue($referencing->getId()->equals($result->first()->getId()));
    }

    public function testAddExercisesAndFindThemOrderedBySortOrder(): void
    {
        $session = DomainTestHelper::createSession();
        $this->repository->save($session);

        $second = DomainTestHelper::createSessionExercise(sessionId: $session->getId(), sortOrder: 1);
        $first = DomainTestHelper::createSessionExercise(sessionId: $session->getId(), sortOrder: 0);
        $this->repository->addExercises(new ArrayCollection([$second, $first]));
        $this->entityManager->clear();

        $result = $this->repository->findExercisesBySessionId($session->getId());

        $this->assertSame(
            [$first->getId()->getValue(), $second->getId()->getValue()],
            $result->map(fn ($sessionExercise) => $sessionExercise->getId()->getValue())->toArray(),
        );
        $this->assertSame(2, $this->repository->countExercisesBySessionId($session->getId()));
    }

    public function testReplaceSetsDeletesOldSetsAndInsertsNewOnesOrderedBySetNumber(): void
    {
        $session = DomainTestHelper::createSession();
        $this->repository->save($session);
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $session->getId());
        $this->repository->saveExercise($sessionExercise);

        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId(), setNumber: 1, weightKg: 100.0, reps: 5),
        ]));

        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId(), setNumber: 2, weightKg: 82.5, reps: 6),
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId(), setNumber: 1, weightKg: 80.0, reps: 8, notes: 'felt heavy'),
        ]));

        $result = $this->repository->findSetsBySessionExerciseId($sessionExercise->getId());

        $this->assertSame(
            [[1, 80.0], [2, 82.5]],
            $result->map(fn (SetLog $setLog) => [$setLog->getSetNumber(), $setLog->getWeightKg()])->toArray(),
        );
        $this->assertSame('felt heavy', $result->first()->getNotes());
        $this->assertSame(2, $this->repository->countSetsBySessionId($session->getId()));
    }

    public function testReplaceSetsWithEmptyCollectionClearsSets(): void
    {
        $session = DomainTestHelper::createSession();
        $this->repository->save($session);
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $session->getId());
        $this->repository->saveExercise($sessionExercise);
        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId()),
        ]));

        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection());

        $this->assertCount(0, $this->repository->findSetsBySessionExerciseId($sessionExercise->getId()));
    }

    public function testDeleteExerciseRemovesItsSets(): void
    {
        $session = DomainTestHelper::createSession();
        $this->repository->save($session);
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $session->getId());
        $this->repository->saveExercise($sessionExercise);
        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId()),
        ]));

        $this->repository->deleteExercise($sessionExercise);

        $this->assertNull($this->repository->findExerciseById($sessionExercise->getId()));
        $this->assertCount(0, $this->repository->findSetsBySessionExerciseId($sessionExercise->getId()));
    }

    public function testDeleteCascadesToExercisesAndSets(): void
    {
        $session = DomainTestHelper::createSession();
        $this->repository->save($session);
        $sessionExercise = DomainTestHelper::createSessionExercise(sessionId: $session->getId());
        $this->repository->saveExercise($sessionExercise);
        $this->repository->replaceSets($sessionExercise->getId(), new ArrayCollection([
            DomainTestHelper::createSetLog(sessionExerciseId: $sessionExercise->getId()),
        ]));

        $this->repository->delete($session);

        $this->assertNull($this->repository->findById($session->getId()));
        $this->assertSame(0, $this->repository->countExercisesBySessionId($session->getId()));
        $this->assertCount(0, $this->repository->findSetsBySessionExerciseId($sessionExercise->getId()));
    }

    public function testFindLatestFinishedExercisePicksMostRecentFinishedSessionExcludingGiven(): void
    {
        $exerciseId = ExerciseId::generate();

        $olderFinished = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-01 10:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-01 11:00:00'),
        );
        $latestFinished = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-02 10:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-02 11:00:00'),
        );
        $unfinished = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-03 10:00:00'),
        );
        $current = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-04 10:00:00'),
        );

        foreach ([$olderFinished, $latestFinished, $unfinished, $current] as $session) {
            $this->repository->save($session);
        }

        $latestFinishedExercise = DomainTestHelper::createSessionExercise(
            sessionId: $latestFinished->getId(),
            exerciseId: $exerciseId,
        );
        $this->repository->addExercises(new ArrayCollection([
            DomainTestHelper::createSessionExercise(sessionId: $olderFinished->getId(), exerciseId: $exerciseId),
            $latestFinishedExercise,
            DomainTestHelper::createSessionExercise(sessionId: $unfinished->getId(), exerciseId: $exerciseId),
            DomainTestHelper::createSessionExercise(sessionId: $current->getId(), exerciseId: $exerciseId),
        ]));

        $result = $this->repository->findLatestFinishedExercise($exerciseId, $current->getId());

        $this->assertNotNull($result);
        $this->assertTrue($latestFinishedExercise->getId()->equals($result->getId()));
    }

    public function testFindLatestFinishedExerciseExcludesTheGivenSessionItself(): void
    {
        $exerciseId = ExerciseId::generate();
        $onlyFinished = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-01 10:00:00'),
            finishedAt: new \DateTimeImmutable('2026-08-01 11:00:00'),
        );
        $this->repository->save($onlyFinished);
        $this->repository->saveExercise(DomainTestHelper::createSessionExercise(
            sessionId: $onlyFinished->getId(),
            exerciseId: $exerciseId,
        ));

        $this->assertNull(
            $this->repository->findLatestFinishedExercise($exerciseId, $onlyFinished->getId())
        );
    }

    public function testFindLatestFinishedExerciseIgnoresUnfinishedSessions(): void
    {
        $exerciseId = ExerciseId::generate();
        $unfinished = DomainTestHelper::createSession(
            startedAt: new \DateTimeImmutable('2026-08-03 10:00:00'),
        );
        $this->repository->save($unfinished);
        $this->repository->saveExercise(DomainTestHelper::createSessionExercise(
            sessionId: $unfinished->getId(),
            exerciseId: $exerciseId,
        ));

        $this->assertNull(
            $this->repository->findLatestFinishedExercise($exerciseId, SessionId::generate())
        );
    }
}
