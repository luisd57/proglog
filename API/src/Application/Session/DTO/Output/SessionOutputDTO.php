<?php

declare(strict_types=1);

namespace App\Application\Session\DTO\Output;

final readonly class SessionOutputDTO
{
    /**
     * @param array<int, SessionExerciseOutputDTO> $exercises
     */
    public function __construct(
        public string $id,
        public ?string $templateId,
        public ?string $templateName,
        public \DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
        public ?string $notes,
        public array $exercises,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->templateId,
            'template_name' => $this->templateName,
            'started_at' => $this->startedAt->format(\DateTimeInterface::ATOM),
            'finished_at' => $this->finishedAt?->format(\DateTimeInterface::ATOM),
            'notes' => $this->notes,
            'exercises' => array_map(
                fn (SessionExerciseOutputDTO $sessionExerciseOutputDTO) => $sessionExerciseOutputDTO->toArray(),
                $this->exercises,
            ),
        ];
    }
}
