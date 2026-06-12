import { NotFoundException } from '@nestjs/common';
import { execSync } from 'child_process';
import { PrismaService } from '../../prisma/prisma.service';
import { TemplatesService } from '../templates/templates.service';
import { SessionsService } from './sessions.service';

describe('SessionsService', () => {
  let prisma: PrismaService;
  let templates: TemplatesService;
  let service: SessionsService;
  let templateId: string;

  beforeAll(() => {
    process.env.DATABASE_URL = 'file:/tmp/sessions-test.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });
    prisma = new PrismaService();
    templates = new TemplatesService(prisma);
    service = new SessionsService(prisma);
  });

  beforeEach(async () => {
    await prisma.session.deleteMany();
    await prisma.workoutTemplate.deleteMany();
    await prisma.exercise.deleteMany();
    await prisma.profile.upsert({
      where: { id: 1 },
      update: { defaultRestSeconds: 90 },
      create: { id: 1, defaultRestSeconds: 90 },
    });
    await prisma.exercise.createMany({
      data: [
        {
          id: 'ex-bench',
          name: 'Bench Press',
          primaryMuscles: JSON.stringify(['chest']),
          secondaryMuscles: JSON.stringify(['triceps']),
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
    const template = await templates.create({
      name: 'Push Day',
      exercises: [
        { exerciseId: 'ex-bench', targetSets: 3, targetReps: 8, restSeconds: 150 },
        { exerciseId: 'ex-ohp' },
      ],
    });
    templateId = template.id;
  });

  afterAll(async () => {
    await prisma.$disconnect();
  });

  describe('start', () => {
    it('creates a session from a template with exercises in order', async () => {
      const session = await service.start(templateId);
      expect(session.templateId).toBe(templateId);
      expect(session.finishedAt).toBeNull();
      expect(session.exercises.map((e) => e.exercise.name)).toEqual([
        'Bench Press',
        'Overhead Press',
      ]);
      expect(session.exercises[0].targetSets).toBe(3);
      expect(session.exercises[0].restSeconds).toBe(150);
      // no template rest → profile default
      expect(session.exercises[1].restSeconds).toBe(90);
      expect(session.exercises[0].previousSets).toEqual([]);
    });

    it('returns previous working sets from the latest finished session', async () => {
      const first = await service.start(templateId);
      await service.replaceSets(first.id, first.exercises[0].id, [
        { weightKg: 60, reps: 10, isWarmup: true },
        { weightKg: 80, reps: 8, isWarmup: false },
        { weightKg: 80, reps: 7, isWarmup: false },
      ]);
      await service.finish(first.id);

      const second = await service.start(templateId);
      expect(second.exercises[0].previousSets).toEqual([
        expect.objectContaining({ weightKg: 60, reps: 10, isWarmup: true }),
        expect.objectContaining({ weightKg: 80, reps: 8, isWarmup: false }),
        expect.objectContaining({ weightKg: 80, reps: 7, isWarmup: false }),
      ]);
    });

    it('ignores unfinished sessions for previous sets', async () => {
      const abandoned = await service.start(templateId);
      await service.replaceSets(abandoned.id, abandoned.exercises[0].id, [
        { weightKg: 100, reps: 1, isWarmup: false },
      ]);

      const next = await service.start(templateId);
      expect(next.exercises[0].previousSets).toEqual([]);
    });

    it('starts a blank session without template', async () => {
      const session = await service.start();
      expect(session.templateId).toBeNull();
      expect(session.exercises).toEqual([]);
    });
  });

  describe('sets', () => {
    it('replaces sets and returns them ordered by set number', async () => {
      const session = await service.start(templateId);
      await service.replaceSets(session.id, session.exercises[0].id, [
        { weightKg: 80, reps: 8, isWarmup: false, notes: 'felt heavy' },
        { weightKg: 82.5, reps: 6, isWarmup: false },
      ]);
      const fetched = await service.get(session.id);
      expect(fetched.exercises[0].sets).toEqual([
        expect.objectContaining({ setNumber: 1, weightKg: 80, notes: 'felt heavy' }),
        expect.objectContaining({ setNumber: 2, weightKg: 82.5, reps: 6 }),
      ]);
    });

    it('rejects sets for an exercise of another session', async () => {
      const a = await service.start(templateId);
      const b = await service.start(templateId);
      await expect(
        service.replaceSets(a.id, b.exercises[0].id, []),
      ).rejects.toThrow(NotFoundException);
    });
  });

  describe('exercises', () => {
    it('adds and removes an exercise in a running session', async () => {
      const session = await service.start();
      const updated = await service.addExercise(session.id, 'ex-ohp');
      expect(updated.exercises.map((e) => e.exercise.id)).toEqual(['ex-ohp']);

      const removed = await service.removeExercise(
        session.id,
        updated.exercises[0].id,
      );
      expect(removed.exercises).toEqual([]);
    });
  });

  describe('finish + list', () => {
    it('marks the session finished and lists sessions newest first', async () => {
      const a = await service.start(templateId);
      await service.finish(a.id);
      const b = await service.start(templateId);

      const list = await service.list();
      expect(list[0].id).toBe(b.id);
      expect(list[0].finishedAt).toBeNull();
      expect(list[1].id).toBe(a.id);
      expect(list[1].finishedAt).not.toBeNull();
      expect(list[1].templateName).toBe('Push Day');
    });
  });

  describe('remove', () => {
    it('deletes a session', async () => {
      const session = await service.start(templateId);
      await service.remove(session.id);
      await expect(service.get(session.id)).rejects.toThrow(NotFoundException);
    });
  });
});
