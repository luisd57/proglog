<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Template;

use App\Application\Template\DTO\Input\CreateTemplateInputDTO;
use App\Application\Template\DTO\Input\TemplateExerciseLineInputDTO;
use App\Application\Template\DTO\Input\UpdateTemplateInputDTO;
use App\Application\Template\DTO\Output\TemplateSummaryOutputDTO;
use App\Application\Template\Handler\CreateTemplateHandler;
use App\Application\Template\Handler\DeleteTemplateHandler;
use App\Application\Template\Handler\GetTemplateHandler;
use App\Application\Template\Handler\GetTemplateMusclesHandler;
use App\Application\Template\Handler\ListTemplatesHandler;
use App\Application\Template\Handler\UpdateTemplateHandler;
use App\Domain\Exercise\Exception\ExerciseNotFoundException;
use App\Domain\Template\Exception\TemplateNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/templates')]
final class TemplateController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('', name: 'api_templates_list', methods: ['GET'])]
    public function list(ListTemplatesHandler $handler): JsonResponse
    {
        $templates = $handler->__invoke();

        return $this->success([
            'templates' => $templates->map(fn (TemplateSummaryOutputDTO $dto) => $dto->toArray())->toArray(),
        ]);
    }

    #[Route('/{id}', name: 'api_templates_show', methods: ['GET'])]
    public function show(string $id, GetTemplateHandler $handler): JsonResponse
    {
        try {
            $template = $handler->__invoke($id);

            return $this->success([
                'template' => $template->toArray(),
            ]);
        } catch (TemplateNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}/muscles', name: 'api_templates_muscles', methods: ['GET'])]
    public function muscles(string $id, GetTemplateMusclesHandler $handler): JsonResponse
    {
        try {
            $muscles = $handler->__invoke($id);

            return $this->success($muscles->toArray());
        } catch (TemplateNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['id' => $exception->getMessage()]);
        }
    }

    #[Route('', name: 'api_templates_create', methods: ['POST'])]
    public function create(Request $request, CreateTemplateHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = self::validateTemplatePayload($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $template = $handler->__invoke(new CreateTemplateInputDTO(
                name: $data['name'],
                exercises: self::exerciseLines($data['exercises']),
            ));

            return $this->created([
                'template' => $template->toArray(),
            ]);
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_templates_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateTemplateHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = self::validateTemplatePayload($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $template = $handler->__invoke(new UpdateTemplateInputDTO(
                id: $id,
                name: $data['name'],
                exercises: self::exerciseLines($data['exercises']),
            ));

            return $this->success([
                'template' => $template->toArray(),
            ]);
        } catch (TemplateNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (ExerciseNotFoundException $exception) {
            return $this->error($exception->getMessage(), $exception->getErrorCode(), 404);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_templates_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteTemplateHandler $handler): JsonResponse
    {
        try {
            $handler->__invoke($id);

            return $this->noContent();
        } catch (TemplateNotFoundException $exception) {
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
    private static function validateTemplatePayload(array $data): array
    {
        $errors = [];

        $name = $data['name'] ?? null;

        if (!is_string($name) || trim($name) === '') {
            $errors['name'] = 'Name is required';
        } elseif (mb_strlen(trim($name)) > 255) {
            $errors['name'] = 'Name must be at most 255 characters';
        }

        $exercises = $data['exercises'] ?? null;

        if (!is_array($exercises) || !array_is_list($exercises)) {
            $errors['exercises'] = 'Exercises must be a list';

            return $errors;
        }

        foreach ($exercises as $exerciseLine) {
            if (!is_array($exerciseLine)) {
                $errors['exercises'] = 'Each exercise must be an object';
                break;
            }

            $exerciseId = $exerciseLine['exercise_id'] ?? null;

            if (!is_string($exerciseId) || $exerciseId === '') {
                $errors['exercises'] = 'Each exercise needs an exercise_id';
                break;
            }

            foreach (['target_sets', 'target_reps', 'rest_seconds'] as $optionalField) {
                if (array_key_exists($optionalField, $exerciseLine)
                    && $exerciseLine[$optionalField] !== null
                    && !is_int($exerciseLine[$optionalField])
                ) {
                    $errors['exercises'] = ucfirst(str_replace('_', ' ', $optionalField)) . ' must be an integer or null';
                    break 2;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $exercises
     *
     * @return array<int, TemplateExerciseLineInputDTO>
     */
    private static function exerciseLines(array $exercises): array
    {
        return array_map(
            fn (array $exerciseLine) => new TemplateExerciseLineInputDTO(
                exerciseId: $exerciseLine['exercise_id'],
                targetSets: $exerciseLine['target_sets'] ?? null,
                targetReps: $exerciseLine['target_reps'] ?? null,
                restSeconds: $exerciseLine['rest_seconds'] ?? null,
            ),
            $exercises,
        );
    }
}
