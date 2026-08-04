<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api\Profile;

use App\Application\Profile\DTO\Input\UpdateProfileInputDTO;
use App\Application\Profile\Handler\GetProfileHandler;
use App\Application\Profile\Handler\UpdateProfileHandler;
use App\Domain\Profile\Entity\Profile;
use App\Infrastructure\Http\Controller\ApiResponseTrait;
use App\Infrastructure\Http\Controller\ValidatesRequestTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile')]
final class ProfileController extends AbstractController
{
    use ApiResponseTrait;
    use ValidatesRequestTrait;

    #[Route('', name: 'api_profile_show', methods: ['GET'])]
    public function show(GetProfileHandler $handler): JsonResponse
    {
        return $this->success([
            'profile' => $handler->__invoke()->toArray(),
        ]);
    }

    #[Route('', name: 'api_profile_update', methods: ['PATCH'])]
    public function update(Request $request, UpdateProfileHandler $handler): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        $errors = self::validateProfilePayload($data);
        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        try {
            $profile = $handler->__invoke(new UpdateProfileInputDTO(
                sexProvided: array_key_exists('sex', $data),
                sex: $data['sex'] ?? null,
                birthDateProvided: array_key_exists('birth_date', $data),
                birthDate: $data['birth_date'] ?? null,
                defaultRestSecondsProvided: array_key_exists('default_rest_seconds', $data),
                defaultRestSeconds: $data['default_rest_seconds'] ?? null,
                heightCmProvided: array_key_exists('height_cm', $data),
                heightCm: isset($data['height_cm']) ? (float) $data['height_cm'] : null,
            ));

            return $this->success([
                'profile' => $profile->toArray(),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->validationError(['general' => $exception->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private static function validateProfilePayload(array $data): array
    {
        $errors = [];

        if (array_key_exists('sex', $data)
            && $data['sex'] !== null
            && !in_array($data['sex'], Profile::SEXES, true)
        ) {
            $errors['sex'] = 'Sex must be male, female or null';
        }

        if (array_key_exists('birth_date', $data)
            && $data['birth_date'] !== null
            && !is_string($data['birth_date'])
        ) {
            $errors['birth_date'] = 'birth_date must be a date string or null';
        }

        if (array_key_exists('default_rest_seconds', $data)
            && (!is_int($data['default_rest_seconds']) || $data['default_rest_seconds'] <= 0)
        ) {
            $errors['default_rest_seconds'] = 'default_rest_seconds must be a positive integer';
        }

        if (array_key_exists('height_cm', $data)
            && $data['height_cm'] !== null
            && !is_int($data['height_cm'])
            && !is_float($data['height_cm'])
        ) {
            $errors['height_cm'] = 'height_cm must be a number or null';
        }

        return $errors;
    }
}
