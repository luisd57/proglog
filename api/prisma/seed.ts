import { PrismaClient } from '@prisma/client';
import { readFileSync } from 'fs';
import { join } from 'path';

interface SourceExercise {
  id?: string;
  name: string;
  primaryMuscles: string[];
  secondaryMuscles: string[];
  equipment: string | null;
  category: string | null;
  instructions: string[];
}

const prisma = new PrismaClient();

async function main() {
  await prisma.profile.upsert({
    where: { id: 1 },
    update: {},
    create: { id: 1 },
  });

  const seeded = await prisma.exercise.count({ where: { isCustom: false } });
  if (seeded > 0) {
    console.log(`Exercises already seeded (${seeded}), skipping.`);
    return;
  }

  const raw = readFileSync(
    join(__dirname, 'seed-data', 'exercises.json'),
    'utf-8',
  );
  const source = JSON.parse(raw) as SourceExercise[];

  // the dataset has a handful of duplicate names; keep the first of each
  const byName = new Map<string, SourceExercise>();
  for (const exercise of source) {
    if (!byName.has(exercise.name)) byName.set(exercise.name, exercise);
  }

  const result = await prisma.exercise.createMany({
    data: [...byName.values()].map((e) => ({
      name: e.name,
      primaryMuscles: JSON.stringify(e.primaryMuscles ?? []),
      secondaryMuscles: JSON.stringify(e.secondaryMuscles ?? []),
      equipment: e.equipment || null,
      category: e.category || null,
      instructions: e.instructions?.length ? e.instructions.join('\n') : null,
      isCustom: false,
    })),
  });
  console.log(`Seeded ${result.count} exercises.`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
