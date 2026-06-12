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

  describe('list — tokenized search', () => {
    // adds rows on top of the two created by the global beforeEach
    async function seedExtra() {
      await prisma.exercise.createMany({
        data: [
          {
            id: 'seed-chin',
            name: 'Chin-Up',
            primaryMuscles: JSON.stringify(['lats']),
            secondaryMuscles: JSON.stringify(['biceps']),
            equipment: 'body only',
            category: 'strength',
            isCustom: false,
          },
          {
            id: 'seed-front-cable',
            name: 'Front Cable Raise',
            primaryMuscles: JSON.stringify(['shoulders']),
            secondaryMuscles: JSON.stringify([]),
            equipment: 'cable',
            category: 'strength',
            isCustom: false,
          },
        ],
      });
    }

    it('matches non-adjacent words in any order', async () => {
      await seedExtra();
      const result = await service.list({ search: 'front raise' });
      expect(result.map((e) => e.name)).toEqual(['Front Cable Raise']);

      const reordered = await service.list({ search: 'cable front' });
      expect(reordered.map((e) => e.name)).toEqual(['Front Cable Raise']);
    });

    it('is tolerant of plurals and hyphens', async () => {
      await seedExtra();
      const result = await service.list({ search: 'chin ups' });
      expect(result.map((e) => e.name)).toEqual(['Chin-Up']);
    });

    it('still matches a single word substring', async () => {
      const result = await service.list({ search: 'bench' });
      expect(result.map((e) => e.name)).toEqual(['Barbell Bench Press']);
    });

    it('combines tokenized search with the muscle filter', async () => {
      await seedExtra();
      const result = await service.list({
        search: 'cable raise',
        muscle: 'shoulders',
      });
      expect(result.map((e) => e.name)).toEqual(['Front Cable Raise']);
    });
  });

  describe('list — search ranking', () => {
    function mk(id: string, name: string, primary: string[]) {
      return {
        id,
        name,
        primaryMuscles: JSON.stringify(primary),
        secondaryMuscles: JSON.stringify([]),
        equipment: 'body only',
        category: 'strength',
        isCustom: false,
      };
    }

    it('ranks the plainest match ahead of wordier variants', async () => {
      await prisma.exercise.createMany({
        data: [
          mk('r-incline', 'Incline Push-Up Reverse Grip', ['chest']),
          mk('r-decline', 'Decline Push-Up', ['chest']),
          mk('r-plain', 'Pushups', ['chest']),
        ],
      });
      const result = await service.list({ search: 'push up' });
      expect(result[0].name).toBe('Pushups');
    });

    it('ranks an exact name match first', async () => {
      await prisma.exercise.createMany({
        data: [
          mk('r-onearm', 'One Arm Chin-Up', ['lats']),
          mk('r-chin', 'Chin-Up', ['lats']),
          mk('r-wide', 'Wide-Grip Chin-Up', ['lats']),
        ],
      });
      const result = await service.list({ search: 'chin up' });
      expect(result[0].name).toBe('Chin-Up');
    });

    it('prefers whole-word matches over mid-word coincidences', async () => {
      await prisma.exercise.createMany({
        data: [
          // "chin" is a substring of "Machine", so both match the filter
          mk('r-machine', 'Machine Row', ['middle back']),
          mk('r-chinraise', 'Chin Raise', ['neck']),
        ],
      });
      const result = await service.list({ search: 'chin' });
      expect(result.map((e) => e.name)).toEqual(['Chin Raise', 'Machine Row']);
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
