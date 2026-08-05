<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Exercise;

use App\Application\Exercise\DTO\Input\CreateExerciseInputDTO;
use App\Application\Exercise\DTO\Input\ListExercisesInputDTO;
use App\Application\Exercise\DTO\Input\UpdateExerciseInputDTO;
use App\Application\Exercise\DTO\Output\ExerciseOutputDTO;
use App\Application\Exercise\Handler\CreateExerciseHandler;
use App\Application\Exercise\Handler\DeleteExerciseHandler;
use App\Application\Exercise\Handler\GetExerciseHandler;
use App\Application\Exercise\Handler\ListExercisesHandler;
use App\Application\Exercise\Handler\UpdateExerciseHandler;
use App\Domain\Exercise\Exception\BuiltInExerciseImmutableException;
use App\Domain\Exercise\Exception\DuplicateExerciseNameException;
use App\Domain\Exercise\Exception\ExerciseInUseException;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/exercises')]
final class ExerciseController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('', name: 'api_exercises_list', methods: ['GET'])]
    public function list(Request $request, ListExercisesHandler $handler): JsonResponse
    {
        $exercises = $handler->__invoke(new ListExercisesInputDTO(
            search: self::queryParam($request, 'search'),
            muscle: self::queryParam($request, 'muscle'),
            equipment: self::queryParam($request, 'equipment'),
        ));

        return $this->success([
            'exercises' => $exercises->map(fn (ExerciseOutputDTO $dto) => $dto->toArray())->toArray(),
        ]);
    }

    #[Route('/{id}', name: 'api_exercises_show', methods: ['GET'])]
    public function show(string $id, GetExerciseHandler $handler): JsonResponse
    {
        try {
            $exercise = $handler->__invoke($id);

            return $this->success([
                'exercise' => $exercise->toArray(),
            ]);
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('', name: 'api_exercises_create', methods: ['POST'])]
    public function create(Request $request, CreateExerciseHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = $this->validateExercisePayload($data, isPatch: false);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $exercise = $handler->__invoke(new CreateExerciseInputDTO(
                name: $data['name'],
                primaryMuscles: $data['primary_muscles'],
                secondaryMuscles: $data['secondary_muscles'] ?? [],
                equipment: $data['equipment'] ?? null,
                category: $data['category'] ?? null,
                instructions: $data['instructions'] ?? null,
            ));

            return $this->created([
                'exercise' => $exercise->toArray(),
            ]);
        } catch (DuplicateExerciseNameException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_exercises_update', methods: ['PATCH'])]
    public function update(string $id, Request $request, UpdateExerciseHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = $this->validateExercisePayload($data, isPatch: true);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $exercise = $handler->__invoke(new UpdateExerciseInputDTO(
                id: $id,
                name: $data['name'] ?? null,
                primaryMuscles: $data['primary_muscles'] ?? null,
                secondaryMuscles: $data['secondary_muscles'] ?? null,
                equipmentProvided: array_key_exists('equipment', $data),
                equipment: $data['equipment'] ?? null,
                categoryProvided: array_key_exists('category', $data),
                category: $data['category'] ?? null,
                instructionsProvided: array_key_exists('instructions', $data),
                instructions: $data['instructions'] ?? null,
            ));

            return $this->success([
                'exercise' => $exercise->toArray(),
            ]);
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (BuiltInExerciseImmutableException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (DuplicateExerciseNameException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_exercises_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteExerciseHandler $handler): JsonResponse
    {
        try {
            $handler->__invoke($id);

            return $this->noContent();
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (BuiltInExerciseImmutableException|ExerciseInUseException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 409);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    private static function queryParam(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        return ($value !== null && $value !== '') ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateExercisePayload(array $data, bool $isPatch): array
    {
        $errors = [];

        if (!$isPatch || array_key_exists('name', $data)) {
            $name = $data['name'] ?? null;

            if (!is_string($name) || trim($name) === '') {
                $errors['name'] = 'Name is required';
            } elseif (mb_strlen($name) > 255) {
                $errors['name'] = 'Name must be at most 255 characters';
            }
        }

        if (!$isPatch || array_key_exists('primary_muscles', $data)) {
            $primaryMuscles = $data['primary_muscles'] ?? null;

            if (!is_array($primaryMuscles) || $primaryMuscles === []) {
                $errors['primary_muscles'] = 'At least one primary muscle is required';
            } elseif (!self::isStringList($primaryMuscles)) {
                $errors['primary_muscles'] = 'Primary muscles must be a list of non-empty strings';
            }
        }

        if (array_key_exists('secondary_muscles', $data)) {
            $secondaryMuscles = $data['secondary_muscles'];

            if (!is_array($secondaryMuscles) || !self::isStringList($secondaryMuscles)) {
                $errors['secondary_muscles'] = 'Secondary muscles must be a list of non-empty strings';
            }
        }

        foreach (['equipment', 'category', 'instructions'] as $optionalField) {
            if (array_key_exists($optionalField, $data)
                && $data[$optionalField] !== null
                && !is_string($data[$optionalField])
            ) {
                $errors[$optionalField] = ucfirst($optionalField) . ' must be a string or null';
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $values
     */
    private static function isStringList(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
