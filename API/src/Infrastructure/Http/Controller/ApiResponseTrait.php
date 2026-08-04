<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

trait ApiResponseTrait
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return self::envelope([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Weights and computed stats are floats in the contract (`"weight_kg": 80.0`);
     * without PRESERVE_ZERO_FRACTION json_encode emits them as ints.
     *
     * @param array<string, mixed> $payload
     */
    private static function envelope(array $payload, int $status): JsonResponse
    {
        // options must be set before setData(): setEncodingOptions() re-encodes from the
        // already-serialised string, which has lost the zero fractions by then.
        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($payload);

        return $response;
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return $this->success($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    protected function error(string $message, string $code, int $status = 400): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => $errors,
            ],
        ], 422);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 'UNAUTHORIZED', 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 'FORBIDDEN', 403);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 'NOT_FOUND', 404);
    }
}
