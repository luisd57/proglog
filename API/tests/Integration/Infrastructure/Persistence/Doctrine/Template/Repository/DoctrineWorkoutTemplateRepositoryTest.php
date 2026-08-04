<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Template\Repository;

use App\Domain\Template\Entity\TemplateExercise;
use App\Domain\Template\Id\WorkoutTemplateId;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use Doctrine\Common\Collections\ArrayCollection;

final class DoctrineWorkoutTemplateRepositoryTest extends IntegrationTestCase
{
    private WorkoutTemplateRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(WorkoutTemplateRepositoryInterface::class);
    }

    public function testSaveAndFindByIdRoundTrips(): void
    {
        $id = WorkoutTemplateId::generate();
        $this->repository->save(DomainTestHelper::createWorkoutTemplate(
            id: $id,
            name: 'Push Day',
            sortOrder: 3,
        ));
        $this->entityManager->clear();

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertTrue($id->equals($found->getId()));
        $this->assertSame('Push Day', $found->getName());
        $this->assertSame(3, $found->getSortOrder());
        $this->assertNull($found->getArchivedAt());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $this->assertNull($this->repository->findById(WorkoutTemplateId::generate()));
    }

    public function testFindAllActiveExcludesArchivedAndOrdersBySortOrder(): void
    {
        $second = DomainTestHelper::createWorkoutTemplate(name: 'Second', sortOrder: 1);
        $first = DomainTestHelper::createWorkoutTemplate(name: 'First', sortOrder: 0);
        $archived = DomainTestHelper::createWorkoutTemplate(name: 'Archived', sortOrder: 2);
        $archived->archive(new \DateTimeImmutable('2026-08-01 10:00:00'));

        $this->repository->save($second);
        $this->repository->save($first);
        $this->repository->save($archived);

        $result = $this->repository->findAllActive();

        $this->assertSame(
            ['First', 'Second'],
            $result->map(fn ($workoutTemplate) => $workoutTemplate->getName())->toArray(),
        );
    }

    public function testFindHighestSortOrderReturnsNullWhenNoTemplatesExist(): void
    {
        $this->assertNull($this->repository->findHighestSortOrder());
    }

    public function testFindHighestSortOrderIncludesArchivedTemplates(): void
    {
        $this->repository->save(DomainTestHelper::createWorkoutTemplate(name: 'Active', sortOrder: 1));
        $archived = DomainTestHelper::createWorkoutTemplate(name: 'Archived', sortOrder: 5);
        $archived->archive(new \DateTimeImmutable('2026-08-01 10:00:00'));
        $this->repository->save($archived);

        $this->assertSame(5, $this->repository->findHighestSortOrder());
    }

    public function testAddExercisesAndFindThemOrderedBySortOrder(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();
        $this->repository->save($workoutTemplate);

        $this->repository->addExercises(new ArrayCollection([
            DomainTestHelper::createTemplateExercise(
                workoutTemplateId: $workoutTemplate->getId(),
                sortOrder: 1,
            ),
            DomainTestHelper::createTemplateExercise(
                workoutTemplateId: $workoutTemplate->getId(),
                sortOrder: 0,
                targetSets: 3,
                targetReps: 8,
                restSeconds: 180,
            ),
        ]));
        $this->entityManager->clear();

        $result = $this->repository->findExercisesByTemplateId($workoutTemplate->getId());

        $this->assertSame(
            [0, 1],
            $result->map(fn (TemplateExercise $templateExercise) => $templateExercise->getSortOrder())->toArray(),
        );
        $this->assertSame(3, $result->first()->getTargetSets());
        $this->assertSame(8, $result->first()->getTargetReps());
        $this->assertSame(180, $result->first()->getRestSeconds());
        $this->assertSame(2, $this->repository->countExercisesByTemplateId($workoutTemplate->getId()));
    }

    public function testFindExercisesReturnsOnlyLinesOfGivenTemplate(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Mine');
        $otherTemplate = DomainTestHelper::createWorkoutTemplate(name: 'Other');
        $this->repository->save($workoutTemplate);
        $this->repository->save($otherTemplate);
        $this->repository->addExercises(new ArrayCollection([
            DomainTestHelper::createTemplateExercise(workoutTemplateId: $workoutTemplate->getId()),
            DomainTestHelper::createTemplateExercise(workoutTemplateId: $otherTemplate->getId()),
        ]));

        $this->assertCount(1, $this->repository->findExercisesByTemplateId($workoutTemplate->getId()));
    }

    public function testDeleteExercisesByTemplateIdRemovesAllLines(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();
        $this->repository->save($workoutTemplate);
        $this->repository->addExercises(new ArrayCollection([
            DomainTestHelper::createTemplateExercise(workoutTemplateId: $workoutTemplate->getId(), sortOrder: 0),
            DomainTestHelper::createTemplateExercise(workoutTemplateId: $workoutTemplate->getId(), sortOrder: 1),
        ]));

        $this->repository->deleteExercisesByTemplateId($workoutTemplate->getId());

        $this->assertSame(0, $this->repository->countExercisesByTemplateId($workoutTemplate->getId()));
    }

    public function testDeleteRemovesTemplateAndItsExerciseLines(): void
    {
        $workoutTemplate = DomainTestHelper::createWorkoutTemplate();
        $this->repository->save($workoutTemplate);
        $this->repository->addExercises(new ArrayCollection([
            DomainTestHelper::createTemplateExercise(workoutTemplateId: $workoutTemplate->getId()),
        ]));

        $this->repository->delete($workoutTemplate);

        $this->assertNull($this->repository->findById($workoutTemplate->getId()));
        $this->assertSame(0, $this->repository->countExercisesByTemplateId($workoutTemplate->getId()));
    }
}
