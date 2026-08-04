<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http\Controller\Api\Session;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use App\Domain\Template\Entity\WorkoutTemplate;
use App\Domain\Template\Repository\WorkoutTemplateRepositoryInterface;
use App\Tests\Helper\ApiTestCase;
use App\Tests\Helper\DomainTestHelper;
use Doctrine\Common\Collections\ArrayCollection;

final class SessionControllerTest extends ApiTestCase
{
    private Exercise $bench;
    private Exercise $ohp;
    private WorkoutTemplate $pushDay;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ExerciseRepositoryInterface $exerciseRepository */
        $exerciseRepository = self::getContainer()->get(ExerciseRepositoryInterface::class);
        /** @var WorkoutTemplateRepositoryInterface $workoutTemplateRepository */
        $workoutTemplateRepository = self::getContainer()->get(WorkoutTemplateRepositoryInterface::class);

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
        $exerciseRepository->save($this->bench);
        $exerciseRepository->save($this->ohp);

        $this->pushDay = DomainTestHelper::createWorkoutTemplate(name: 'Push Day');
        $workoutTemplateRepository->save($this->pushDay);
        $workoutTemplateRepository->addExercises(new ArrayCollection([
            DomainTestHelper::createTemplateExercise(
                workoutTemplateId: $this->pushDay->getId(),
                exerciseId: $this->bench->getId(),
                sortOrder: 0,
                targetSets: 3,
                targetReps: 8,
                restSeconds: 150,
            ),
            DomainTestHelper::createTemplateExercise(
                workoutTemplateId: $this->pushDay->getId(),
                exerciseId: $this->ohp->getId(),
                sortOrder: 1,
            ),
        ]));
    }

    private function startSession(?string $templateId): array
    {
        $this->jsonRequest('POST', '/api/sessions', $templateId !== null ? ['template_id' => $templateId] : []);
        $this->assertResponseStatusCodeSame(201);

        return $this->getResponseData()['data']['session'];
    }

    public function testStartFromTemplatePrefillsExercisesWithTargets(): void
    {
        $this->freezeClock('2026-08-04T10:00:00+00:00');

        $session = $this->startSession($this->pushDay->getId()->getValue());

        $this->assertSame($this->pushDay->getId()->getValue(), $session['template_id']);
        $this->assertSame('Push Day', $session['template_name']);
        $this->assertSame('2026-08-04T10:00:00+00:00', $session['started_at']);
        $this->assertNull($session['finished_at']);
        $this->assertNull($session['notes']);

        $this->assertSame(
            ['Bench Press', 'Overhead Press'],
            array_map(fn (array $sessionExercise) => $sessionExercise['exercise']['name'], $session['exercises']),
        );
        $this->assertSame([0, 1], array_column($session['exercises'], 'sort_order'));
        $this->assertSame(3, $session['exercises'][0]['target_sets']);
        $this->assertSame(8, $session['exercises'][0]['target_reps']);
        $this->assertSame(150, $session['exercises'][0]['rest_seconds']);
        // no template rest, no profile row -> ultimate default 120
        $this->assertSame(120, $session['exercises'][1]['rest_seconds']);
        $this->assertSame([], $session['exercises'][0]['sets']);
        $this->assertSame([], $session['exercises'][0]['previous_sets']);
    }

    public function testStartBlankSessionHasNoTemplateAndNoExercises(): void
    {
        $session = $this->startSession(null);

        $this->assertNull($session['template_id']);
        $this->assertNull($session['template_name']);
        $this->assertSame([], $session['exercises']);
    }

    public function testStartWithUnknownTemplateReturns404(): void
    {
        $this->jsonRequest('POST', '/api/sessions', [
            'template_id' => '0198c5b6-0000-7000-8000-000000000000',
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('TEMPLATE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testStartWithMalformedTemplateIdReturns422(): void
    {
        $this->jsonRequest('POST', '/api/sessions', ['template_id' => 'nope']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('VALIDATION_ERROR', $this->getResponseData()['error']['code']);
    }

    public function testFullLifecycleLogsSetsFinishesAndPrefillsNextSession(): void
    {
        $clock = $this->freezeClock('2026-08-04T10:00:00+00:00');

        $first = $this->startSession($this->pushDay->getId()->getValue());
        $benchExerciseId = $first['exercises'][0]['id'];

        $this->jsonRequest('PUT', "/api/sessions/{$first['id']}/exercises/{$benchExerciseId}/sets", [
            'sets' => [
                ['weight_kg' => 60, 'reps' => 10, 'is_warmup' => true],
                ['weight_kg' => 80, 'reps' => 8],
                ['weight_kg' => 80, 'reps' => 7, 'notes' => 'felt heavy'],
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $replaceResponse = $this->getResponseData();
        $this->assertTrue($replaceResponse['success']);
        $this->assertNull($replaceResponse['data']);

        $this->jsonRequest('GET', '/api/sessions/' . $first['id']);
        $fetched = $this->getResponseData()['data']['session'];
        $this->assertSame(
            [[1, 60.0, 10, true], [2, 80.0, 8, false], [3, 80.0, 7, false]],
            array_map(
                fn (array $setLine) => [
                    $setLine['set_number'],
                    $setLine['weight_kg'],
                    $setLine['reps'],
                    $setLine['is_warmup'],
                ],
                $fetched['exercises'][0]['sets'],
            ),
        );
        $this->assertSame('felt heavy', $fetched['exercises'][0]['sets'][2]['notes']);

        $clock->modify('+1 hour');
        $this->jsonRequest('POST', "/api/sessions/{$first['id']}/finish");
        $this->assertResponseStatusCodeSame(200);
        $finished = $this->getResponseData()['data']['session'];
        $this->assertSame('2026-08-04T11:00:00+00:00', $finished['finished_at']);

        // the next session prefills previous sets from the finished one
        $second = $this->startSession($this->pushDay->getId()->getValue());
        $this->assertSame(
            [[60.0, 10, true], [80.0, 8, false], [80.0, 7, false]],
            array_map(
                fn (array $setLine) => [$setLine['weight_kg'], $setLine['reps'], $setLine['is_warmup']],
                $second['exercises'][0]['previous_sets'],
            ),
        );
        $this->assertSame([], $second['exercises'][1]['previous_sets']);
    }

    public function testReplaceSetsForExerciseOfAnotherSessionReturns404(): void
    {
        $sessionA = $this->startSession($this->pushDay->getId()->getValue());
        $sessionB = $this->startSession($this->pushDay->getId()->getValue());
        $foreignExerciseId = $sessionB['exercises'][0]['id'];

        $this->jsonRequest('PUT', "/api/sessions/{$sessionA['id']}/exercises/{$foreignExerciseId}/sets", [
            'sets' => [],
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('SESSION_EXERCISE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testReplaceSetsRejectsNegativeWeight(): void
    {
        $session = $this->startSession($this->pushDay->getId()->getValue());
        $benchExerciseId = $session['exercises'][0]['id'];

        $this->jsonRequest('PUT', "/api/sessions/{$session['id']}/exercises/{$benchExerciseId}/sets", [
            'sets' => [
                ['weight_kg' => -1, 'reps' => 5],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('sets', $this->getResponseData()['error']['details']);
    }

    public function testReplaceSetsRejectsMissingReps(): void
    {
        $session = $this->startSession($this->pushDay->getId()->getValue());
        $benchExerciseId = $session['exercises'][0]['id'];

        $this->jsonRequest('PUT', "/api/sessions/{$session['id']}/exercises/{$benchExerciseId}/sets", [
            'sets' => [
                ['weight_kg' => 80],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testAddAndRemoveExerciseInRunningSession(): void
    {
        $session = $this->startSession(null);

        $this->jsonRequest('POST', "/api/sessions/{$session['id']}/exercises", [
            'exercise_id' => $this->ohp->getId()->getValue(),
        ]);
        $this->assertResponseStatusCodeSame(200);
        $updated = $this->getResponseData()['data']['session'];
        $this->assertSame(
            [$this->ohp->getId()->getValue()],
            array_map(fn (array $sessionExercise) => $sessionExercise['exercise']['id'], $updated['exercises']),
        );
        $this->assertSame(0, $updated['exercises'][0]['sort_order']);

        $sessionExerciseId = $updated['exercises'][0]['id'];
        $this->jsonRequest('DELETE', "/api/sessions/{$session['id']}/exercises/{$sessionExerciseId}");
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([], $this->getResponseData()['data']['session']['exercises']);
    }

    public function testAddUnknownExerciseReturns404ExerciseNotFound(): void
    {
        $session = $this->startSession(null);

        $this->jsonRequest('POST', "/api/sessions/{$session['id']}/exercises", [
            'exercise_id' => '0198c5b6-0000-7000-8000-000000000000',
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('EXERCISE_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testPatchSessionNotesStoresNotes(): void
    {
        $session = $this->startSession(null);

        $this->jsonRequest('PATCH', '/api/sessions/' . $session['id'], ['notes' => 'good day']);
        $this->assertResponseStatusCodeSame(200);
        $patchResponse = $this->getResponseData();
        $this->assertTrue($patchResponse['success']);
        $this->assertNull($patchResponse['data']);

        $this->jsonRequest('GET', '/api/sessions/' . $session['id']);
        $this->assertSame('good day', $this->getResponseData()['data']['session']['notes']);
    }

    public function testPatchSessionNotesRejectsMissingNotes(): void
    {
        $session = $this->startSession(null);

        $this->jsonRequest('PATCH', '/api/sessions/' . $session['id'], []);

        $this->assertResponseStatusCodeSame(422);
        $this->assertArrayHasKey('notes', $this->getResponseData()['error']['details']);
    }

    public function testPatchSessionExerciseNotesStoresNotes(): void
    {
        $session = $this->startSession($this->pushDay->getId()->getValue());
        $benchExerciseId = $session['exercises'][0]['id'];

        $this->jsonRequest(
            'PATCH',
            "/api/sessions/{$session['id']}/exercises/{$benchExerciseId}",
            ['notes' => 'slow eccentric'],
        );
        $this->assertResponseStatusCodeSame(200);

        $this->jsonRequest('GET', '/api/sessions/' . $session['id']);
        $this->assertSame(
            'slow eccentric',
            $this->getResponseData()['data']['session']['exercises'][0]['notes'],
        );
    }

    public function testListOrdersSessionsNewestFirstWithCounts(): void
    {
        $clock = $this->freezeClock('2026-08-04T10:00:00+00:00');

        $first = $this->startSession($this->pushDay->getId()->getValue());
        $this->jsonRequest('PUT', "/api/sessions/{$first['id']}/exercises/{$first['exercises'][0]['id']}/sets", [
            'sets' => [
                ['weight_kg' => 80, 'reps' => 8],
                ['weight_kg' => 80, 'reps' => 7],
            ],
        ]);
        $this->jsonRequest('POST', "/api/sessions/{$first['id']}/finish");

        $clock->modify('+1 day');
        $second = $this->startSession(null);

        $this->jsonRequest('GET', '/api/sessions');
        $this->assertResponseStatusCodeSame(200);
        $list = $this->getResponseData()['data']['sessions'];

        $this->assertCount(2, $list);
        $this->assertSame($second['id'], $list[0]['id']);
        $this->assertNull($list[0]['finished_at']);
        $this->assertNull($list[0]['template_name']);
        $this->assertSame(0, $list[0]['exercise_count']);

        $this->assertSame($first['id'], $list[1]['id']);
        $this->assertNotNull($list[1]['finished_at']);
        $this->assertSame('Push Day', $list[1]['template_name']);
        $this->assertSame(2, $list[1]['exercise_count']);
        $this->assertSame(2, $list[1]['set_count']);
    }

    public function testDeleteSessionReturns204AndCascades(): void
    {
        $session = $this->startSession($this->pushDay->getId()->getValue());
        $benchExerciseId = $session['exercises'][0]['id'];
        $this->jsonRequest('PUT', "/api/sessions/{$session['id']}/exercises/{$benchExerciseId}/sets", [
            'sets' => [['weight_kg' => 80, 'reps' => 8]],
        ]);

        $this->jsonRequest('DELETE', '/api/sessions/' . $session['id']);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/sessions/' . $session['id']);
        $this->assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', '/api/sessions');
        $this->assertCount(0, $this->getResponseData()['data']['sessions']);
    }

    public function testDeleteTemplateDetachesRunningSession(): void
    {
        $session = $this->startSession($this->pushDay->getId()->getValue());

        $this->jsonRequest('DELETE', '/api/templates/' . $this->pushDay->getId()->getValue());
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/sessions/' . $session['id']);
        $this->assertResponseStatusCodeSame(200);
        $detached = $this->getResponseData()['data']['session'];
        $this->assertNull($detached['template_id']);
        $this->assertNull($detached['template_name']);
        // targets are gone with the template; rest falls back to the default
        $this->assertNull($detached['exercises'][0]['target_sets']);
        $this->assertSame(120, $detached['exercises'][0]['rest_seconds']);
    }

    public function testShowUnknownSessionReturns404(): void
    {
        $this->jsonRequest('GET', '/api/sessions/0198c5b6-0000-7000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('SESSION_NOT_FOUND', $this->getResponseData()['error']['code']);
    }

    public function testShowMalformedIdReturns422(): void
    {
        $this->jsonRequest('GET', '/api/sessions/nope');

        $this->assertResponseStatusCodeSame(422);
    }
}
