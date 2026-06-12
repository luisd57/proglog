import { BadRequestException, NotFoundException } from '@nestjs/common';
import { execSync } from 'child_process';
import { PrismaService } from '../../prisma/prisma.service';
import { ExercisesService } from './exercises.service';

describe('ExercisesService', () => {
  let prisma: PrismaService;
  let service: ExercisesService;

  beforeAll(() => {
    process.env.DATABASE_URL = 'file:/tmp/exercises-test.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });
    prisma = new PrismaService();
    service = new ExercisesService(prisma);
  });

  beforeEach(async () => {
    await prisma.exercise.deleteMany();
    await prisma.exercise.createMany({
      data: [
        {
          id: 'seed-bench',
          name: 'Barbell Bench Press',
          primaryMuscles: JSON.stringify(['chest']),
          secondaryMuscles: JSON.stringify(['shoulders', 'triceps']),
          equipment: 'barbell',
          category: 'strength',
          isCustom: false,
        },
        {
          id: 'seed-curl',
          name: 'Dumbbell Curl',
          primaryMuscles: JSON.stringify(['biceps']),
          secondaryMuscles: JSON.stringify(['forearms']),
          equipment: 'dumbbell',
          category: 'strength',
          isCustom: false,
        },
      ],
    });
  });

  afterAll(async () => {
    await prisma.$disconnect();
  });

  describe('list', () => {
    it('returns all exercises ordered by name with muscle arrays parsed', async () => {
      const result = await service.list();
      expect(result.map((e) => e.name)).toEqual([
        'Barbell Bench Press',
        'Dumbbell Curl',
      ]);
      expect(result[0].primaryMuscles).toEqual(['chest']);
      expect(result[0].secondaryMuscles).toEqual(['shoulders', 'triceps']);
    });

    it('filters by case-insensitive name substring', async () => {
      const result = await service.list({ search: 'bench' });
      expect(result.map((e) => e.name)).toEqual(['Barbell Bench Press']);
    });

    it('filters by muscle, matching primary or secondary', async () => {
      const primary = await service.list({ muscle: 'chest' });
      expect(primary.map((e) => e.name)).toEqual(['Barbell Bench Press']);

      const secondary = await service.list({ muscle: 'triceps' });
      expect(secondary.map((e) => e.name)).toEqual(['Barbell Bench Press']);
    });

    it('filters by equipment', async () => {
      const result = await service.list({ equipment: 'dumbbell' });
      expect(result.map((e) => e.name)).toEqual(['Dumbbell Curl']);
    });
  });

  describe('get', () => {
    it('returns a single exercise with muscle arrays parsed', async () => {
      const result = await service.get('seed-bench');
      expect(result.name).toBe('Barbell Bench Press');
      expect(result.primaryMuscles).toEqual(['chest']);
    });

    it('throws NotFoundException for an unknown id', async () => {
      await expect(service.get('nope')).rejects.toThrow(NotFoundException);
    });
  });

  describe('createCustom', () => {
    it('creates a custom exercise and round-trips muscle arrays', async () => {
      const created = await service.createCustom({
        name: 'Machine Rear Delt Fly',
        primaryMuscles: ['shoulders'],
        secondaryMuscles: ['traps'],
        equipment: 'machine',
      });
      expect(created.isCustom).toBe(true);
      expect(created.primaryMuscles).toEqual(['shoulders']);

      const fetched = await service.get(created.id);
      expect(fetched.secondaryMuscles).toEqual(['traps']);
    });

    it('rejects an empty name', async () => {
      await expect(
        service.createCustom({ name: '  ', primaryMuscles: ['chest'] }),
      ).rejects.toThrow(BadRequestException);
    });

    it('rejects empty primary muscles', async () => {
      await expect(
        service.createCustom({ name: 'X', primaryMuscles: [] }),
      ).rejects.toThrow(BadRequestException);
    });
  });

  describe('updateCustom', () => {
    it('updates a custom exercise', async () => {
      const created = await service.createCustom({
        name: 'Cable Thing',
        primaryMuscles: ['lats'],
      });
      const updated = await service.updateCustom(created.id, {
        name: 'Cable Row Variation',
        secondaryMuscles: ['biceps'],
      });
      expect(updated.name).toBe('Cable Row Variation');
      expect(updated.secondaryMuscles).toEqual(['biceps']);
      expect(updated.primaryMuscles).toEqual(['lats']);
    });

    it('refuses to update a seeded exercise', async () => {
      await expect(
        service.updateCustom('seed-bench', { name: 'Hacked' }),
      ).rejects.toThrow(BadRequestException);
    });
  });

  describe('removeCustom', () => {
    it('deletes a custom exercise', async () => {
      const created = await service.createCustom({
        name: 'Temp',
        primaryMuscles: ['chest'],
      });
      await service.removeCustom(created.id);
      await expect(service.get(created.id)).rejects.toThrow(NotFoundException);
    });

    it('refuses to delete a seeded exercise', async () => {
      await expect(service.removeCustom('seed-bench')).rejects.toThrow(
        BadRequestException,
      );
    });
  });
});
