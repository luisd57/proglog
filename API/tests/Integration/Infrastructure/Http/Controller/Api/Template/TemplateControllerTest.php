<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Template;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;

final class TemplateControllerTest extends ApiTestCase
{
    private ExerciseRepositoryInterface $exerciseRepository;
    private Exercise $bench;
    private Exercise $ohp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exerciseRepository = self::getContainer()->get(ExerciseRepositoryInterface::class);

        $this->bench = DomainTestHelper::createBuiltInExercise(
            name: 'Bench Press',
            primaryMuscles: ['chest'],
            secondaryMuscles: ['triceps'],
        );
        $this->ohp = DomainTestHelper::createBuiltInExercise(
            name: 'Overhead Press',
            primaryMuscles: ['shoulders'],
            secondaryMuscles: ['triceps'],
        );
        $this->exerciseRepository->save($this->bench);
        $this->exerciseRepository->save($this->ohp);
    }

    /**
     * @param array<int, array<string, mixed>> $exercises
     */
    private function createTemplate(string $name, array $exercises): array
    {
        $this->jsonRequest('POST', '/api/templates', [
            'name' => $name,
            'exercises' => $exercises,
        ]);
        $this->assertResponseStatusCodeSame(201);

        return $this->getResponseData()['data']['template'];
    }

    public function testCreateListAndMusclesFlow(): void
    {
        $created = $this->createTemplate('Split A', [
            ['exercise_id' => $this->bench->getId()->getValue(), 'target_sets' => 3],
        ]);

        $this->assertSame('Bench Press', $created['exercises'][0]['exercise']['name']);
        $this->assertSame(3, $created['exercises'][0]['target_sets']);
        $this->assertSame(0, $created['sort_order']);

        $this->jsonRequest('GET', '/api/templates');
        $this->assertResponseStatusCodeSame(200);
        $list = $this->getResponseData()['data']['templates'];
        $this->assertCount(1, $list);
        $this->assertSame('Split A', $list[0]['name']);
        $this->assertSame(1, $list[0]['exercise_count']);

        $this->jsonRequest('GET', '/api/templates/' . $created['id'] . '/muscles');
        $this->assertResponseStatusCodeSame(200);
        $muscles = $this->getResponseData()['data'];
        $this->assertSame(['chest'], $muscles['primary']);
        $this->assertSame(['triceps'], $muscles['secondary']);
    }

    public function testShowReturnsTemplateWithOrderedExercises(): void
    {
        $created = $this->createTemplate('Push Day', [
            [
                'exercise_id' => $this->bench->getId()->getValue(),
                'target_sets' => 3,
                'target_reps' => 8,
                'rest_seconds' => 180,
            ],
            ['exercise_id' => $this->ohp->getId()->getValue()],
        ]);

        $this->jsonRequest('GET', '/api/templates/' . $created['id']);

        $this->assertResponseStatusCodeSame(200);
        $template = $this->getResponseData()['data']['template'];
        $this->assertSame('Push Day', $template['name']);
        $this->assertSame(
            ['Bench Press', 'Overhead Press'],
            array_map(fn (array $line) => $line['exercise']['name'], $template['exercises']),
        );
        $this->assertSame([0, 1], array_column($template['exercises'], 'sort_order'));
        $this->assertSame(3, $template['exercises'][0]['target_sets']);
        $this->assertSame(180, $template['exercises'][0]['rest_seconds']);
        $this->assertNull($template['exercises'][1]['target_sets']);
    }

    public function testSecondTemplateGetsNextSortOrder(): void
    {
        $this->createTemplate('First', []);
        $second = $this->createTemplate('Second', []);

        $this->assertSame(1, $second['sort_order']);
    }

    public function testShowUnknownTemplateReturns404(): void
    {
        $this->jsonRequest('GET', '/api/templates/0198c5b6-0000-7000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getResponseData();
        $this->assertFalse($data['success']);
        $this->assertSame('TEMPLATE_NOT_FOUND', $data['error']['code']);
    }

    public function testShowMalformedIdReturns422(): void
    {
        $this->jsonRequest('GET', '/api/templates/nope');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->jsonRequest('POST', '/api/templates', [
            'name' => '  ',
            'exercises' => [],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getResponseData();
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('name', $data['error']['details']);
    }

    public function testCreateRejectsMissingExerciseList(): void
    {
        $this->jsonRequest('POST', '/api/templates', [
            'name' => 'Push Day',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('exercises', $this->getResponseData()['error']['details']);
    }

    public function testCreateWithUnknownExerciseReturns404ExerciseNotFound(): void
    {
        $this->jsonRequest('POST', '/api/templates', [
            'name' => 'Push Day',
            'exercises' => [
                ['exercise_id' => '0198c5b6-0000-7000-8000-000000000000'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('EXERCISE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testCreateWithMalformedExerciseIdReturns422(): void
    {
        $this->jsonRequest('POST', '/api/templates', [
            'name' => 'Push Day',
            'exercises' => [
                ['exercise_id' => 'nope'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateReplacesNameAndExerciseLines(): void
    {
        $created = $this->createTemplate('Push Day', [
            ['exercise_id' => $this->bench->getId()->getValue(), 'target_sets' => 3],
            ['exercise_id' => $this->ohp->getId()->getValue()],
        ]);
        $originalLineIds = array_column($created['exercises'], 'id');

        $this->jsonRequest('PUT', '/api/templates/' . $created['id'], [
            'name' => 'Push Day A',
            'exercises' => [
                ['exercise_id' => $this->ohp->getId()->getValue(), 'target_sets' => 4],
                ['exercise_id' => $this->bench->getId()->getValue()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $updated = $this->getResponseData()['data']['template'];
        $this->assertSame('Push Day A', $updated['name']);
        $this->assertSame(
            [$this->ohp->getId()->getValue(), $this->bench->getId()->getValue()],
            array_map(fn (array $line) => $line['exercise']['id'], $updated['exercises']),
        );
        $this->assertSame(4, $updated['exercises'][0]['target_sets']);
        // full replace: line ids change
        $this->assertEmpty(array_intersect($originalLineIds, array_column($updated['exercises'], 'id')));
        // template sort_order unchanged
        $this->assertSame($created['sort_order'], $updated['sort_order']);
    }

    public function testUpdateUnknownTemplateReturns404(): void
    {
        $this->jsonRequest('PUT', '/api/templates/0198c5b6-0000-7000-8000-000000000000', [
            'name' => 'X',
            'exercises' => [],
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('TEMPLATE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testDeleteReturns204AndRemovesTemplate(): void
    {
        $created = $this->createTemplate('Doomed', []);

        $this->jsonRequest('DELETE', '/api/templates/' . $created['id']);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/templates/' . $created['id']);
        $this->assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', '/api/templates');
        $this->assertCount(0, $this->getResponseData()['data']['templates']);
    }

    public function testDeleteUnknownTemplateReturns404(): void
    {
        $this->jsonRequest('DELETE', '/api/templates/0198c5b6-0000-7000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('TEMPLATE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }
}
