<?php

declare(strict_types=1);

namespace App\Domain\Measurement\Repository;

use App\Domain\Measurement\Entity\Measurement;
use App\Domain\Measurement\Id\MeasurementId;
use Doctrine\Common\Collections\ArrayCollection;

interface MeasurementRepositoryInterface
{
    public function save(Measurement $measurement): void;

    public function findById(MeasurementId $measurementId): ?Measurement;

    /**
     * Full series of one type ordered by measured_at ASC (id ASC as a
     * deterministic tie-breaker; UUID v7 ids are time-ordered).
     *
     * @return ArrayCollection<int, Measurement>
     */
    public function findByType(string $type): ArrayCollection;

    /**
     * All measurements ordered by measured_at ASC, id ASC (used to compute
     * the latest value per type - last write wins, as in the old API).
     *
     * @return ArrayCollection<int, Measurement>
     */
    public function findAll(): ArrayCollection;

    /**
     * Most recent measurement of one type, null when the type was never
     * measured (bodyweight lookup for strength levels).
     */
    public function findLatestByType(string $type): ?Measurement;

    public function delete(Measurement $measurement): void;
}
