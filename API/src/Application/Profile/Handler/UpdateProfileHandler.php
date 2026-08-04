<?php

declare(strict_types=1);

namespace App\Application\Profile\Handler;

use App\Application\Profile\DTO\Input\UpdateProfileInputDTO;
use App\Application\Profile\DTO\Output\ProfileOutputDTO;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;

/**
 * Patch semantics: only provided keys are applied; sex, birth_date and
 * height_cm accept explicit null to clear. Creates the default row first when
 * absent (old API: upsert).
 */
final readonly class UpdateProfileHandler
{
    public function __construct(
        private ProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function __invoke(UpdateProfileInputDTO $dto): ProfileOutputDTO
    {
        $profile = $this->profileRepository->find() ?? Profile::createDefault();

        if ($dto->sexProvided) {
            $profile->changeSex($dto->sex);
        }

        if ($dto->birthDateProvided) {
            $profile->changeBirthDate(
                $dto->birthDate !== null ? self::parseBirthDate($dto->birthDate) : null,
            );
        }

        if ($dto->defaultRestSecondsProvided && $dto->defaultRestSeconds !== null) {
            $profile->changeDefaultRestSeconds($dto->defaultRestSeconds);
        }

        if ($dto->heightCmProvided) {
            $profile->changeHeightCm($dto->heightCm);
        }

        $this->profileRepository->save($profile);

        return ProfileOutputDTO::fromEntity($profile);
    }

    private static function parseBirthDate(string $birthDate): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($birthDate);
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException("Invalid birth date: {$birthDate}", 0, $exception);
        }
    }
}
