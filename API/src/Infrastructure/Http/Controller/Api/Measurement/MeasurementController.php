<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Measurement;

use App\Application\Measurement\DTO\Input\CreateMeasurementInputDTO;
use App\Application\Measurement\DTO\Output\MeasurementOutputDTO;
use App\Application\Measurement\Handler\CreateMeasurementHandler;
use App\Application\Measurement\Handler\DeleteMeasurementHandler;
use App\Application\Measurement\Handler\GetLatestMeasurementsHandler;
use App\Application\Measurement\Handler\ListMeasurementsHandler;
use App\Domain\Measurement\Exception\MeasurementNotFoundException;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/measurements')]
final class MeasurementController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('', name: 'api_measurements_series', methods: ['GET'])]
    public function series(Request $request, ListMeasurementsHandler $handler): JsonResponse
    {
        $type = $request->query->get('type');

        if (!is_string($type) || $type === '') {
            return $this->validationError(['type' => 'Type is required']);
        }

        try {
            $measurements = $handler->__invoke($type);

            return $this->success([
                'measurements' => $measurements->map(fn (MeasurementOutputDTO $dto) => $dto->toArray())->toArray(),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['type' => $exception->getMessage()]);
        }
    }

    #[Route('/latest', name: 'api_measurements_latest', methods: ['GET'])]
    public function latest(GetLatestMeasurementsHandler $handler): JsonResponse
    {
        // Cast to object so an empty map encodes as {} instead of [].
        return $this->success([
            'latest' => (object) $handler->__invoke()->toArray(),
        ]);
    }

    #[Route('', name: 'api_measurements_create', methods: ['POST'])]
    public function create(Request $request, CreateMeasurementHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = self::validateMeasurementPayload($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $measurement = $handler->__invoke(new CreateMeasurementInputDTO(
                type: $data['type'],
                value: (float) $data['value'],
                measuredAt: $data['measured_at'] ?? null,
            ));

            return $this->created([
                'measurement' => $measurement->toArray(),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'api_measurements_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteMeasurementHandler $handler): JsonResponse
    {
        try {
            $handler->__invoke($id);

            return $this->noContent();
        } catch (MeasurementNotFoundException $exception) {
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
    private static function validateMeasurementPayload(array $data): array
    {
        $errors = [];

        $type = $data['type'] ?? null;

        if (!is_string($type) || $type === '') {
            $errors['type'] = 'Type is required';
        }

        $value = $data['value'] ?? null;

        if (!is_int($value) && !is_float($value)) {
            $errors['value'] = 'Value must be a number';
        }

        if (array_key_exists('measured_at', $data)
            && $data['measured_at'] !== null
            && !is_string($data['measured_at'])
        ) {
            $errors['measured_at'] = 'measured_at must be a date string';
        }

        return $errors;
    }
}
