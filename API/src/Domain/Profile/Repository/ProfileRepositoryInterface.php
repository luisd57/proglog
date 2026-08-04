<?php

declare(strict_types=1);

namespace App\Domain\Profile\Repository;

use App\Domain\Profile\Entity\Profile;

interface ProfileRepositoryInterface
{
    /**
     * The singleton profile row, null until first created.
     */
    public function find(): ?Profile;

    public function save(Profile $profile): void;
}
