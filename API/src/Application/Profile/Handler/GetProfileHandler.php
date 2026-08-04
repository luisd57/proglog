<?php

declare(strict_types=1);

namespace App\Application\Profile\Handler;

use App\Application\Profile\DTO\Output\ProfileOutputDTO;
use App\Domain\Profile\Entity\Profile;
use App\Domain\Profile\Repository\ProfileRepositoryInterface;

/**
 * Returns the singleton profile, creating the default row on first access
 * (old API: upsert with empty update).
 */
final readonly class GetProfileHandler
{
    public function __construct(
        private ProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function __invoke(): ProfileOutputDTO
    {
        $profile = $this->profileRepository->find();

        if ($profile === null) {
            $profile = Profile::createDefault();
            $this->profileRepository->save($profile);
        }

        return ProfileOutputDTO::fromEntity($profile);
    }
}
