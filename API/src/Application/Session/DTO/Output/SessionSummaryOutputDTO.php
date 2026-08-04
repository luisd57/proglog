<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Output;

use App\Domain\Session\Entity\Session;

final readonly class SessionSummaryOutputDTO
{
    public function __construct(
        public string $id,
        public ?string $templateName,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public int $exerciseCount,
        public int $setCount,
    ) {
    }

    public static function fromEntity(
        Session $session,
        ?string $templateName,
        int $exerciseCount,
        int $setCount,
    ): self {
        return new self(
            id: $session->getId()->getValue(),
            templateName: $templateName,
            startedAt: $session->getStartedAt(),
            finishedAt: $session->getFinishedAt(),
            exerciseCount: $exerciseCount,
            setCount: $setCount,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'template_name' => $this->templateName,
            'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finished_at' => $this->finishedAt?->format(\DateTimeInterface::ATOM),
            'exercise_count' => $this->exerciseCount,
            'set_count' => $this->setCount,
        ];
    }
}
