<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Session;

use App\Application\Session\DTO\Input\AddSessionExerciseInputDTO;
use App\Application\Session\DTO\Input\RemoveSessionExerciseInputDTO;
use App\Application\Session\DTO\Input\ReplaceSetsInputDTO;
use App\Application\Session\DTO\Input\SetLineInputDTO;
use App\Application\Session\DTO\Input\StartSessionInputDTO;
use App\Application\Session\DTO\Input\UpdateSessionExerciseNotesInputDTO;
use App\Application\Session\DTO\Input\UpdateSessionNotesInputDTO;
use App\Application\Session\DTO\Output\SessionSummaryOutputDTO;
use App\Application\Session\Handler\AddSessionExerciseHandler;
use App\Application\Session\Handler\DeleteSessionHandler;
use App\Application\Session\Handler\FinishSessionHandler;
use App\Application\Session\Handler\GetSessionHandler;
use App\Application\Session\Handler\ListSessionsHandler;
use App\Application\Session\Handler\RemoveSessionExerciseHandler;
use App\Application\Session\Handler\ReplaceSetsHandler;
use App\Application\Session\Handler\StartSessionHandler;
use App\Application\Session\Handler\UpdateSessionExerciseNotesHandler;
use App\Application\Session\Handler\UpdateSessionNotesHandler;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Session\Exception\SessionExerciseNotFoundException;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sessions')]
final class SessionController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('', name: 'api_sessions_start', methods: ['POST'])]
    public function start(Request $request, StartSessionHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $templateId = $data['template_id'] ?? null;

        if ($templateId !== null && !is_string($templateId)) {
            return $this->validationError(['template_id' => 'Template id must be a string or null']);
        }

        try {
            $session = $handler->__invoke(new StartSessionInputDTO(templateId: $templateId));

            return $this->created([
                'session' => $session->toArray(),
            ]);
        } catch (TemplateNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['template_id' => $exception->getMessage()]);
        }
    }

    #[Route('', name: 'api_sessions_list', methods: ['GET'])]
    public function list(ListSessionsHandler $handler): JsonResponse
    {
        $sessions = $handler->__invoke();

        return $this->success([
            'sessions' => $sessions->map(fn (SessionSummaryOutputDTO $dto) => $dto->toArray())->toArray(),
        ]);
    }

    #[Route('/{id}', name: 'api_sessions_show', methods: ['GET'])]
    public function show(string $id, GetSessionHandler $handler): JsonResponse
    {
        try {
            $session = $handler->__invoke($id);

            return $this->success([
                'session' => $session->toArray(),
            ]);
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/exercises/{sessionExerciseId}/sets', name: 'api_sessions_replace_sets', methods: ['PUT'])]
    public function replaceSets(
        string $id,
        string $sessionExerciseId,
        Request $request,
        ReplaceSetsHandler $handler,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = self::validateSetsPayload($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $handler->__invoke(new ReplaceSetsInputDTO(
                sessionId: $id,
                sessionExerciseId: $sessionExerciseId,
                sets: self::setLines($data['sets']),
            ));

            return $this->success(null);
        } catch (SessionExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/exercises', name: 'api_sessions_add_exercise', methods: ['POST'])]
    public function addExercise(string $id, Request $request, AddSessionExerciseHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $exerciseId = $data['exercise_id'] ?? null;

        if (!is_string($exerciseId) || $exerciseId === '') {
            return $this->validationError(['exercise_id' => 'Exercise id is required']);
        }

        try {
            $session = $handler->__invoke(new AddSessionExerciseInputDTO(
                sessionId: $id,
                exerciseId: $exerciseId,
            ));

            return $this->success([
                'session' => $session->toArray(),
            ]);
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/exercises/{sessionExerciseId}', name: 'api_sessions_remove_exercise', methods: ['DELETE'])]
    public function removeExercise(
        string $id,
        string $sessionExerciseId,
        RemoveSessionExerciseHandler $handler,
    ): JsonResponse {
        try {
            $session = $handler->__invoke(new RemoveSessionExerciseInputDTO(
                sessionId: $id,
                sessionExerciseId: $sessionExerciseId,
            ));

            return $this->success([
                'session' => $session->toArray(),
            ]);
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (SessionExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_sessions_update_notes', methods: ['PATCH'])]
    public function updateNotes(string $id, Request $request, UpdateSessionNotesHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        if (!array_key_exists('notes', $data) || !is_string($data['notes'])) {
            return $this->validationError(['notes' => 'Notes must be a string']);
        }

        try {
            $handler->__invoke(new UpdateSessionNotesInputDTO(
                sessionId: $id,
                notes: $data['notes'],
            ));

            return $this->success(null);
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/exercises/{sessionExerciseId}', name: 'api_sessions_update_exercise_notes', methods: ['PATCH'])]
    public function updateExerciseNotes(
        string $id,
        string $sessionExerciseId,
        Request $request,
        UpdateSessionExerciseNotesHandler $handler,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        if (!array_key_exists('notes', $data) || !is_string($data['notes'])) {
            return $this->validationError(['notes' => 'Notes must be a string']);
        }

        try {
            $handler->__invoke(new UpdateSessionExerciseNotesInputDTO(
                sessionId: $id,
                sessionExerciseId: $sessionExerciseId,
                notes: $data['notes'],
            ));

            return $this->success(null);
        } catch (SessionExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/finish', name: 'api_sessions_finish', methods: ['POST'])]
    public function finish(string $id, FinishSessionHandler $handler): JsonResponse
    {
        try {
            $session = $handler->__invoke($id);

            return $this->success([
                'session' => $session->toArray(),
            ]);
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_sessions_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteSessionHandler $handler): JsonResponse
    {
        try {
            $handler->__invoke($id);

            return $this->noContent();
        } catch (SessionNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private static function validateSetsPayload(array $data): array
    {
        $sets = $data['sets'] ?? null;

        if (!is_array($sets) || !array_is_list($sets)) {
            return ['sets' => 'Sets must be a list'];
        }

        foreach ($sets as $setLine) {
            if (!is_array($setLine)) {
                return ['sets' => 'Each set must be an object'];
            }

            $weightKg = $setLine['weight_kg'] ?? null;

            if (!is_int($weightKg) && !is_float($weightKg)) {
                return ['sets' => 'Each set needs a numeric weight_kg'];
            }

            if ($weightKg < 0) {
                return ['sets' => 'weight_kg must not be negative'];
            }

            $reps = $setLine['reps'] ?? null;

            if (!is_int($reps)) {
                return ['sets' => 'Each set needs an integer reps'];
            }

            if ($reps < 0) {
                return ['sets' => 'reps must not be negative'];
            }

            if (array_key_exists('is_warmup', $setLine) && !is_bool($setLine['is_warmup'])) {
                return ['sets' => 'is_warmup must be a boolean'];
            }

            if (array_key_exists('notes', $setLine)
                && $setLine['notes'] !== null
                && !is_string($setLine['notes'])
            ) {
                return ['sets' => 'notes must be a string or null'];
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $sets
     *
     * @return array<int, SetLineInputDTO>
     */
    private static function setLines(array $sets): array
    {
        return array_map(
            fn (array $setLine) => new SetLineInputDTO(
                weightKg: (float) $setLine['weight_kg'],
                reps: $setLine['reps'],
                isWarmup: $setLine['is_warmup'] ?? false,
                notes: $setLine['notes'] ?? null,
            ),
            $sets,
        );
    }
}
