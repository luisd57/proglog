<?php

declare(strict_types=1);

namespace App\Domain\Session\Repository;

use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Session\Entity\Session;
use App\Domain\Session\Entity\SessionExercise;
use App\Domain\Session\Entity\SetLog;
use App\Domain\Session\Id\SessionExerciseId;
use App\Domain\Session\Id\SessionId;
use App\Domain\Template\Id\WorkoutTemplateId;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Repository for the Session aggregate (session + its exercises + their
 * sets). Children have no Doctrine relations to their parents: they are
 * loaded by parent id and composed by the application layer. Cascade deletes
 * (Prisma onDelete: Cascade in the old schema) are enforced here because the
 * schema has no FKs.
 */
interface SessionRepositoryInterface
{
    public function save(Session $session): void;

    public function findById(SessionId $sessionId): ?Session;

    /**
     * All sessions (finished or not) ordered by started_at DESC (id DESC as
     * a deterministic tie-breaker; UUID v7 ids are time-ordered).
     *
     * @return ArrayCollection<int, Session>
     */
    public function findAll(): ArrayCollection;

    /**
     * Sessions referencing a template (used for SET NULL detaching before a
     * template is deleted).
     *
     * @return ArrayCollection<int, Session>
     */
    public function findByTemplateId(WorkoutTemplateId $workoutTemplateId): ArrayCollection;

    /**
     * Deletes the session together with its exercises and their sets.
     */
    public function delete(Session $session): void;

    public function saveExercise(SessionExercise $sessionExercise): void;

    /**
     * Bulk add with a single flush (template prefill on session start).
     *
     * @param ArrayCollection<int, SessionExercise> $sessionExercises
     */
    public function addExercises(ArrayCollection $sessionExercises): void;

    public function findExerciseById(SessionExerciseId $sessionExerciseId): ?SessionExercise;

    /**
     * Exercises of one session ordered by sort_order ASC.
     *
     * @return ArrayCollection<int, SessionExercise>
     */
    public function findExercisesBySessionId(SessionId $sessionId): ArrayCollection;

    public function countExercisesBySessionId(SessionId $sessionId): int;

    public function countSetsBySessionId(SessionId $sessionId): int;

    /**
     * Deletes the session exercise together with its sets.
     */
    public function deleteExercise(SessionExercise $sessionExercise): void;

    /**
     * Sets of one session exercise ordered by set_number ASC.
     *
     * @return ArrayCollection<int, SetLog>
     */
    public function findSetsBySessionExerciseId(SessionExerciseId $sessionExerciseId): ArrayCollection;

    /**
     * Replaces ALL sets of a session exercise in a single flush.
     *
     * @param ArrayCollection<int, SetLog> $setLogs
     */
    public function replaceSets(SessionExerciseId $sessionExerciseId, ArrayCollection $setLogs): void;

    /**
     * The session-exercise entry for $exerciseId in the most recent FINISHED
     * session other than $sessionId (the session to exclude - typically the
     * one currently being viewed). Null when the exercise was never performed
     * in a finished session.
     */
    public function findLatestFinishedExercise(ExerciseId $exerciseId, SessionId $sessionId): ?SessionExercise;

    /**
     * All working (non warmup) sets of one exercise across FINISHED sessions,
     * optionally excluding one session ($sessionId - typically the
     * in-progress session when showing PRs during a workout).
     *
     * @return ArrayCollection<int, SetLog>
     */
    public function findFinishedWorkingSets(ExerciseId $exerciseId, ?SessionId $sessionId): ArrayCollection;

    /**
     * Finished sessions containing the exercise, ordered by started_at ASC
     * (id ASC as a deterministic tie-breaker).
     *
     * @return ArrayCollection<int, Session>
     */
    public function findFinishedSessionsByExerciseId(ExerciseId $exerciseId): ArrayCollection;

    /**
     * Session-exercise entries for the exercise in FINISHED sessions, ordered
     * by session started_at ASC (session id, then entry id as tie-breakers).
     *
     * @return ArrayCollection<int, SessionExercise>
     */
    public function findFinishedExercisesByExerciseId(ExerciseId $exerciseId): ArrayCollection;

    /**
     * Finished sessions with started_at >= $from and < $to (either bound may
     * be null = unbounded), ordered by started_at ASC (stats windows).
     *
     * @return ArrayCollection<int, Session>
     */
    public function findFinishedSessionsBetween(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): ArrayCollection;
}
