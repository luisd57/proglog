<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence\Doctrine\Exercise\Repository;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\DomainTestHelper;
use App\Tests\Helper\IntegrationTestCase;
use Doctrine\Common\Collections\ArrayCollection;

final class DoctrineExerciseRepositoryTest extends IntegrationTestCase
{
    private ExerciseRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ExerciseRepositoryInterface::class);
    }

    private function seedCatalog(): void
    {
        $this->repository->saveAll(new ArrayCollection([
            DomainTestHelper::createBuiltInExercise(
                name: 'Barbell Bench Press',
                primaryMuscles: ['chest'],
                secondaryMuscles: ['shoulders', 'triceps'],
                equipment: 'barbell',
            ),
            DomainTestHelper::createBuiltInExercise(
                name: 'Dumbbell Curl',
                primaryMuscles: ['biceps'],
                secondaryMuscles: ['forearms'],
                equipment: 'dumbbell',
            ),
            DomainTestHelper::createBuiltInExercise(
                name: 'Chin-Up',
                primaryMuscles: ['lats'],
                secondaryMuscles: ['biceps'],
                equipment: 'body only',
            ),
            DomainTestHelper::createBuiltInExercise(
                name: 'Front Cable Raise',
                primaryMuscles: ['shoulders'],
                secondaryMuscles: [],
                equipment: 'cable',
            ),
        ]));
    }

    public function testSaveAndFindByIdRoundTripsJsonMuscleArrays(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(
            id: $id,
            name: 'Machine Rear Delt Fly',
            primaryMuscles: ['shoulders'],
            secondaryMuscles: ['traps'],
        );
        $this->repository->save($exercise);
        $this->entityManager->clear();

        $found = $this->repository->findById($id);

        $this->assertNotNull($found);
        $this->assertTrue($id->equals($found->getId()));
        $this->assertSame('Machine Rear Delt Fly', $found->getName());
        $this->assertSame(['shoulders'], $found->getPrimaryMuscles());
        $this->assertSame(['traps'], $found->getSecondaryMuscles());
        $this->assertSame('machine', $found->getEquipment());
        $this->assertTrue($found->isCustom());
    }

    public function testFindByIdNonExistentReturnsNull(): void
    {
        $this->assertNull($this->repository->findById(ExerciseId::generate()));
    }

    public function testFindByNameReturnsMatchOrNull(): void
    {
        $this->seedCatalog();

        $this->assertNotNull($this->repository->findByName('Chin-Up'));
        $this->assertNull($this->repository->findByName('Nope'));
    }

    public function testSearchWithoutFiltersReturnsAllOrderedByName(): void
    {
        $this->seedCatalog();

        $result = $this->repository->search([], null, null);

        $this->assertSame(
            ['Barbell Bench Press', 'Chin-Up', 'Dumbbell Curl', 'Front Cable Raise'],
            $result->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );
    }

    public function testSearchTokensMatchCaseInsensitiveSubstringsInAnyOrder(): void
    {
        $this->seedCatalog();

        $result = $this->repository->search(['front', 'raise'], null, null);
        $this->assertSame(
            ['Front Cable Raise'],
            $result->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );

        $reordered = $this->repository->search(['cable', 'front'], null, null);
        $this->assertSame(
            ['Front Cable Raise'],
            $reordered->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );
    }

    public function testSearchByMuscleMatchesPrimaryOrSecondary(): void
    {
        $this->seedCatalog();

        $primary = $this->repository->search([], 'chest', null);
        $this->assertSame(
            ['Barbell Bench Press'],
            $primary->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );

        $secondary = $this->repository->search([], 'triceps', null);
        $this->assertSame(
            ['Barbell Bench Press'],
            $secondary->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );

        // "biceps" is primary of Dumbbell Curl and secondary of Chin-Up
        $both = $this->repository->search([], 'biceps', null);
        $this->assertSame(
            ['Chin-Up', 'Dumbbell Curl'],
            $both->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );
    }

    public function testSearchByEquipment(): void
    {
        $this->seedCatalog();

        $result = $this->repository->search([], null, 'dumbbell');

        $this->assertSame(
            ['Dumbbell Curl'],
            $result->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );
    }

    public function testSearchCombinesTokensAndMuscleFilter(): void
    {
        $this->seedCatalog();

        $result = $this->repository->search(['cable', 'raise'], 'shoulders', null);

        $this->assertSame(
            ['Front Cable Raise'],
            $result->map(fn (Exercise $exercise) => $exercise->getName())->toArray(),
        );
    }

    public function testCountBuiltInIgnoresCustomExercises(): void
    {
        $this->seedCatalog();
        $this->repository->save(DomainTestHelper::createCustomExercise(name: 'My Custom Press'));

        $this->assertSame(4, $this->repository->countBuiltIn());
    }

    public function testDeleteRemovesExercise(): void
    {
        $id = ExerciseId::generate();
        $exercise = DomainTestHelper::createCustomExercise(id: $id, name: 'Temp');
        $this->repository->save($exercise);

        $this->repository->delete($exercise);

        $this->assertNull($this->repository->findById($id));
    }

    public function testUniqueNameConstraintIsEnforced(): void
    {
        $this->repository->save(DomainTestHelper::createCustomExercise(name: 'Unique Thing'));

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->repository->save(DomainTestHelper::createCustomExercise(name: 'Unique Thing'));
    }
}
