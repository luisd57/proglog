import { BadRequestException, NotFoundException } from '@nestjs/common';
import { execSync } from 'child_process';
import { PrismaService } from '../../prisma/prisma.service';
import { TemplatesService } from './templates.service';

describe('TemplatesService', () => {
  let prisma: PrismaService;
  let service: TemplatesService;

  beforeAll(() => {
    process.env.DATABASE_URL = 'file:/tmp/templates-test.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });
    prisma = new PrismaService();
    service = new TemplatesService(prisma);
  });

  beforeEach(async () => {
    await prisma.workoutTemplate.deleteMany();
    await prisma.exercise.deleteMany();
    await prisma.exercise.createMany({
      data: [
        {
          id: 'ex-bench',
          name: 'Bench Press',
          primaryMuscles: JSON.stringify(['chest']),
          secondaryMuscles: JSON.stringify(['shoulders', 'triceps']),
          isCustom: false,
        },
        {
          id: 'ex-row',
          name: 'Barbell Row',
          primaryMuscles: JSON.stringify(['middle back']),
          secondaryMuscles: JSON.stringify(['biceps', 'lats']),
          isCustom: false,
        },
        {
          id: 'ex-ohp',
          name: 'Overhead Press',
          primaryMuscles: JSON.stringify(['shoulders']),
          secondaryMuscles: JSON.stringify(['triceps']),
          isCustom: false,
        },
      ],
    });
  });

  afterAll(async () => {
    await prisma.$disconnect();
  });

  async function createPushDay() {
    return service.create({
      name: 'Push Day',
      exercises: [
        { exerciseId: 'ex-bench', targetSets: 3, targetReps: 8, restSeconds: 180 },
        { exerciseId: 'ex-ohp' },
      ],
    });
  }

  describe('create + get', () => {
    it('creates a template with ordered exercises', async () => {
      const created = await createPushDay();
      const fetched = await service.get(created.id);
      expect(fetched.name).toBe('Push Day');
      expect(fetched.exercises.map((e) => e.exercise.name)).toEqual([
        'Bench Press',
        'Overhead Press',
      ]);
      expect(fetched.exercises[0].targetSets).toBe(3);
      expect(fetched.exercises[0].restSeconds).toBe(180);
    });

    it('rejects an empty name', async () => {
      await expect(
        service.create({ name: ' ', exercises: [] }),
      ).rejects.toThrow(BadRequestException);
    });

    it('throws NotFoundException for unknown template', async () => {
      await expect(service.get('nope')).rejects.toThrow(NotFoundException);
    });
  });

  describe('list', () => {
    it('lists templates with exercise counts, ordered by sortOrder', async () => {
      await service.create({ name: 'B Split', exercises: [{ exerciseId: 'ex-row' }] });
      await createPushDay();
      const list = await service.list();
      expect(list).toHaveLength(2);
      expect(list[0].name).toBe('B Split');
      expect(list[0].exerciseCount).toBe(1);
      expect(list[1].exerciseCount).toBe(2);
    });
  });

  describe('update', () => {
    it('replaces name and exercise list, preserving new order', async () => {
      const created = await createPushDay();
      const updated = await service.update(created.id, {
        name: 'Push Day A',
        exercises: [
          { exerciseId: 'ex-ohp', targetSets: 4 },
          { exerciseId: 'ex-bench' },
        ],
      });
      expect(updated.name).toBe('Push Day A');
      expect(updated.exercises.map((e) => e.exercise.id)).toEqual([
        'ex-ohp',
        'ex-bench',
      ]);
      expect(updated.exercises[0].targetSets).toBe(4);
    });
  });

  describe('remove', () => {
    it('deletes a template', async () => {
      const created = await createPushDay();
      await service.remove(created.id);
      await expect(service.get(created.id)).rejects.toThrow(NotFoundException);
    });
  });

  describe('muscles', () => {
    it('aggregates primary and secondary muscles across exercises', async () => {
      const created = await createPushDay();
      const muscles = await service.muscles(created.id);
      expect(muscles.primary.sort()).toEqual(['chest', 'shoulders']);
      // shoulders is primary somewhere, so it must not appear as secondary
      expect(muscles.secondary.sort()).toEqual(['triceps']);
    });
  });
});
