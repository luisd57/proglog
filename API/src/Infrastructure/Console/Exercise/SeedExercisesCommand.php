<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Exercise;

use App\Domain\Exercise\Entity\Exercise;
use App\Domain\Exercise\Id\ExerciseId;
use App\Domain\Exercise\Repository\ExerciseRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-exercises',
    description: 'Seed the built-in exercise catalog from data/exercises.json (idempotent)',
)]
final class SeedExercisesCommand extends Command
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exerciseRepository,
        private readonly string $exercisesDataFile,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $seeded = $this->exerciseRepository->countBuiltIn();
        if ($seeded > 0) {
            $io->note(sprintf('Exercises already seeded (%d), skipping.', $seeded));

            return Command::SUCCESS;
        }

        if (!is_file($this->exercisesDataFile)) {
            $io->error(sprintf('Seed data file not found: %s', $this->exercisesDataFile));

            return Command::FAILURE;
        }

        $raw = file_get_contents($this->exercisesDataFile);
        $source = json_decode($raw !== false ? $raw : '', true);

        if (!is_array($source)) {
            $io->error('Seed data file does not contain a JSON array.');

            return Command::FAILURE;
        }

        // the dataset has a handful of duplicate names; keep the first of each
        $byName = [];
        foreach ($source as $sourceExercise) {
            $name = $sourceExercise['name'] ?? null;

            if (!is_string($name) || $name === '' || isset($byName[$name])) {
                continue;
            }

            $byName[$name] = $sourceExercise;
        }

        $exercises = new ArrayCollection();
        foreach ($byName as $sourceExercise) {
            $instructions = $sourceExercise['instructions'] ?? [];

            $exercises->add(Exercise::createBuiltIn(
                id: ExerciseId::generate(),
                name: $sourceExercise['name'],
                primaryMuscles: $sourceExercise['primaryMuscles'] ?? [],
                secondaryMuscles: $sourceExercise['secondaryMuscles'] ?? [],
                equipment: ($sourceExercise['equipment'] ?? null) ?: null,
                category: ($sourceExercise['category'] ?? null) ?: null,
                instructions: is_array($instructions) && $instructions !== []
                    ? implode("\n", $instructions)
                    : null,
            ));
        }

        $this->exerciseRepository->saveAll($exercises);

        $io->success(sprintf('Seeded %d exercises.', $exercises->count()));

        return Command::SUCCESS;
    }
}
