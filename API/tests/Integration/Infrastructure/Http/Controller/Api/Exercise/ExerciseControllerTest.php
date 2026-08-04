<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Exercise;

use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class ExerciseControllerTest extends ApiTestCase
{
    private ExerciseRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ExerciseRepositoryInterface::class);

        $this->repository->save(DomainTestHelper::createBuiltInExercise(
            name: 'Barbell Squat',
            primaryMuscles: ['quadriceps'],
            secondaryMuscles: ['glutes', 'lower back'],
            equipment: 'barbell',
        ));
    }

    public function testListReturnsExercisesWithParsedMuscles(): void
    {
        $this->jsonRequest('GET', '/api/exercises');

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['exercises']);
        $this->assertSame('Barbell Squat', $data['data']['exercises'][0]['name']);
        $this->assertSame(['quadriceps'], $data['data']['exercises'][0]['primary_muscles']);
        $this->assertSame(['glutes', 'lower back'], $data['data']['exercises'][0]['secondary_muscles']);
        $this->assertFalse($data['data']['exercises'][0]['is_custom']);
    }

    public function testListFiltersByMuscle(): void
    {
        $this->jsonRequest('GET', '/api/exercises?muscle=glutes');
        $hit = $this->getResponseData();
        $this->assertCount(1, $hit['data']['exercises']);

        $this->jsonRequest('GET', '/api/exercises?muscle=biceps');
        $miss = $this->getResponseData();
        $this->assertCount(0, $miss['data']['exercises']);
    }

    public function testListRanksExactMatchFirst(): void
    {
        $this->repository->save(DomainTestHelper::createBuiltInExercise(
            name: 'One Arm Chin-Up',
            primaryMuscles: ['lats'],
            secondaryMuscles: [],
        ));
        $this->repository->save(DomainTestHelper::createBuiltInExercise(
            name: 'Chin-Up',
            primaryMuscles: ['lats'],
            secondaryMuscles: [],
        ));

        $this->jsonRequest('GET', '/api/exercises?search=chin+ups');

        $data = $this->getResponseData();
        $this->assertSame(
            ['Chin-Up', 'One Arm Chin-Up'],
            array_column($data['data']['exercises'], 'name'),
        );
    }

    public function testShowReturnsSingleExercise(): void
    {
        $exercise = $this->repository->findByName('Barbell Squat');

        $this->jsonRequest('GET', '/api/exercises/' . $exercise->getId()->getValue());

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getResponseData();
        $this->assertSame('Barbell Squat', $data['data']['exercise']['name']);
    }

    public function testShowUnknownIdReturns404(): void
    {
        $this->jsonRequest('GET', '/api/exercises/0198c5b6-0000-7000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('EXERCISE_NOT_FOUND', $data['error']['code']);
    }

    public function testShowMalformedIdReturns422(): void
    {
        $this->jsonRequest('GET', '/api/exercises/nope');

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateThenDeleteCustomExercise(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'My Custom Press',
            'primary_muscles' => ['shoulders'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $created = $this->getResponseData();
        $this->assertTrue($created['data']['exercise']['is_custom']);
        $exerciseId = $created['data']['exercise']['id'];

        $this->jsonRequest('DELETE', '/api/exercises/' . $exerciseId);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/exercises/' . $exerciseId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateRejectsMissingName(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'primary_muscles' => ['chest'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('name', $data['error']['details']);
    }

    public function testCreateRejectsEmptyPrimaryMuscles(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'X',
            'primary_muscles' => [],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('primary_muscles', $data['error']['details']);
    }

    public function testCreateDuplicateNameReturns409(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'Barbell Squat',
            'primary_muscles' => ['quadriceps'],
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('DUPLICATE_EXERCISE_NAME', $data['error']['code']);
    }

    public function testUpdatePatchesOnlyProvidedFields(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'Cable Thing',
            'primary_muscles' => ['lats'],
        ]);
        $exerciseId = $this->getResponseData()['data']['exercise']['id'];

        $this->jsonRequest('PATCH', '/api/exercises/' . $exerciseId, [
            'name' => 'Cable Row Variation',
            'secondary_muscles' => ['biceps'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->getResponseData()['data']['exercise'];
        $this->assertSame('Cable Row Variation', $updated['name']);
        $this->assertSame(['biceps'], $updated['secondary_muscles']);
        $this->assertSame(['lats'], $updated['primary_muscles']);
    }

    public function testUpdateBuiltInExerciseReturns409(): void
    {
        $exercise = $this->repository->findByName('Barbell Squat');

        $this->jsonRequest('PATCH', '/api/exercises/' . $exercise->getId()->getValue(), [
            'name' => 'Hacked',
        ]);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('BUILT_IN_EXERCISE_IMMUTABLE', $data['error']['code']);
    }

    public function testDeleteBuiltInExerciseReturns409(): void
    {
        $exercise = $this->repository->findByName('Barbell Squat');

        $this->jsonRequest('DELETE', '/api/exercises/' . $exercise->getId()->getValue());

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('BUILT_IN_EXERCISE_IMMUTABLE', $data['error']['code']);
    }

    public function testDeleteExerciseUsedByTemplateReturns409(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'Referenced Custom Press',
            'primary_muscles' => ['chest'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $exerciseId = $this->getResponseData()['data']['exercise']['id'];

        $this->jsonRequest('POST', '/api/templates', [
            'name' => 'Uses The Custom Exercise',
            'exercises' => [['exercise_id' => $exerciseId]],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->jsonRequest('DELETE', '/api/exercises/' . $exerciseId);

        $this->assertResponseStatusCodeSame(409);
        $data = $this->getResponseData();
        $this->assertSame('EXERCISE_IN_USE', $data['error']['code']);

        // still there
        $this->jsonRequest('GET', '/api/exercises/' . $exerciseId);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testDeleteExerciseUsedByLoggedSessionReturns409(): void
    {
        $this->jsonRequest('POST', '/api/exercises', [
            'name' => 'Logged Custom Press',
            'primary_muscles' => ['chest'],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $exerciseId = $this->getResponseData()['data']['exercise']['id'];

        $this->jsonRequest('POST', '/api/sessions', []);
        $this->assertResponseStatusCodeSame(201);
        $sessionId = $this->getResponseData()['data']['session']['id'];

        $this->jsonRequest('POST', "/api/sessions/{$sessionId}/exercises", [
            'exercise_id' => $exerciseId,
        ]);
        $this->assertResponseStatusCodeSame(200);

        $this->jsonRequest('DELETE', '/api/exercises/' . $exerciseId);

        $this->assertResponseStatusCodeSame(409);
        $this->assertSame('EXERCISE_IN_USE', $this->getResponseData()['error']['code']);
    }
}
