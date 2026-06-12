import { execSync } from 'child_process';
import { PrismaService } from '../../prisma/prisma.service';
import { SessionsService } from '../sessions/sessions.service';
import { StatsService } from './stats.service';

describe('StatsService.exerciseBest', () => {
  let prisma: PrismaService;
  let sessions: SessionsService;
  let service: StatsService;

  beforeAll(() => {
    process.env.DATABASE_URL = 'file:/tmp/stats-test.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });
    prisma = new PrismaService();
    sessions = new SessionsService(prisma);
    service = new StatsService(prisma);
  });

  beforeEach(async () => {
    await prisma.session.deleteMany();
    await prisma.exercise.deleteMany();
    await prisma.exercise.create({
      data: {
        id: 'ex-bench',
        name: 'Bench Press',
        primaryMuscles: JSON.stringify(['chest']),
        secondaryMuscles: JSON.stringify([]),
        isCustom: false,
      },
    });
  });

  afterAll(async () => {
    await prisma.$disconnect();
  });

  async function logFinishedSession(sets: Parameters<SessionsService['replaceSets']>[2]) {
    const session = await sessions.start();
    const withExercise = await sessions.addExercise(session.id, 'ex-bench');
    await sessions.replaceSets(session.id, withExercise.exercises[0].id, sets);
    await sessions.finish(session.id);
    return session.id;
  }

  it('returns best weight and best e1RM over working sets of finished sessions', async () => {
    await logFinishedSession([
      { weightKg: 60, reps: 12, isWarmup: true }, // warmup ignored: e1rm 84
      { weightKg: 80, reps: 8 }, // e1rm 101.33
      { weightKg: 85, reps: 3 }, // e1rm 93.5, best weight
    ]);
    const best = await service.exerciseBest('ex-bench');
    expect(best.bestWeightKg).toBe(85);
    expect(best.bestE1rm).toBeCloseTo(101.33, 2);
  });

  it('ignores unfinished sessions and the excluded session', async () => {
    const finishedId = await logFinishedSession([{ weightKg: 80, reps: 5 }]);

    // unfinished session with a bigger lift must not count
    const running = await sessions.start();
    const withExercise = await sessions.addExercise(running.id, 'ex-bench');
    await sessions.replaceSets(running.id, withExercise.exercises[0].id, [
      { weightKg: 200, reps: 1 },
    ]);

    const best = await service.exerciseBest('ex-bench');
    expect(best.bestWeightKg).toBe(80);

    // excluding the only finished session leaves no history
    const excluded = await service.exerciseBest('ex-bench', finishedId);
    expect(excluded.bestWeightKg).toBeNull();
    expect(excluded.bestE1rm).toBeNull();
  });

  it('returns nulls when there is no history', async () => {
    const best = await service.exerciseBest('ex-bench');
    expect(best).toEqual({ bestWeightKg: null, bestE1rm: null });
  });

  describe('exerciseSeries', () => {
    it('returns one point per finished session with top set, volume and e1RM', async () => {
      await logFinishedSession([
        { weightKg: 60, reps: 10, isWarmup: true },
        { weightKg: 80, reps: 8 },
        { weightKg: 80, reps: 6 },
      ]);
      await logFinishedSession([
        { weightKg: 82.5, reps: 8 },
      ]);

      const series = await service.exerciseSeries('ex-bench');
      expect(series.points).toHaveLength(2);

      const [first, second] = series.points;
      // warmup excluded from everything
      expect(first.volume).toBe(80 * 8 + 80 * 6);
      expect(first.topSet).toEqual({ weightKg: 80, reps: 8 });
      expect(first.e1rm).toBeCloseTo(101.33, 2);
      expect(second.volume).toBe(82.5 * 8);
      expect(second.e1rm).toBeCloseTo(104.5, 2);
      expect(new Date(first.date).getTime()).toBeLessThan(
        new Date(second.date).getTime(),
      );
    });

    it('skips sessions with only warmups and returns PR events chronologically', async () => {
      await logFinishedSession([{ weightKg: 80, reps: 8 }]); // baseline = PR
      await logFinishedSession([{ weightKg: 60, reps: 10, isWarmup: true }]);
      await logFinishedSession([{ weightKg: 70, reps: 5 }]); // no PR
      await logFinishedSession([{ weightKg: 85, reps: 8 }]); // weight + e1rm PR

      const series = await service.exerciseSeries('ex-bench');
      expect(series.points).toHaveLength(3);

      expect(series.prs).toHaveLength(2);
      expect(series.prs[0]).toEqual(
        expect.objectContaining({ weightKg: 80, reps: 8 }),
      );
      expect(series.prs[1]).toEqual(
        expect.objectContaining({ weightKg: 85, reps: 8 }),
      );
    });

    it('returns empty series without history', async () => {
      const series = await service.exerciseSeries('ex-bench');
      expect(series).toEqual({ points: [], prs: [] });
    });
  });

  describe('weeklyMuscles', () => {
    it('aggregates muscles from finished sessions of the last 7 days', async () => {
      await prisma.exercise.create({
        data: {
          id: 'ex-row2',
          name: 'Row',
          primaryMuscles: JSON.stringify(['middle back']),
          secondaryMuscles: JSON.stringify(['biceps']),
          isCustom: false,
        },
      });
      // recent finished session with bench (chest primary)
      await logFinishedSession([{ weightKg: 80, reps: 8 }]);

      // old session with row — must not count
      const old = await sessions.start();
      const withRow = await sessions.addExercise(old.id, 'ex-row2');
      await sessions.replaceSets(old.id, withRow.exercises[0].id, [
        { weightKg: 60, reps: 10 },
      ]);
      await sessions.finish(old.id);
      await prisma.session.update({
        where: { id: old.id },
        data: { startedAt: new Date(Date.now() - 10 * 24 * 3600 * 1000) },
      });

      const result = await service.weeklyMuscles();
      expect(result.primary).toEqual(['chest']);
      expect(result.secondary).toEqual([]);
      expect(result.sessionCount).toBe(1);
    });

    it('does not count exercises without working sets', async () => {
      await logFinishedSession([{ weightKg: 60, reps: 10, isWarmup: true }]);
      const result = await service.weeklyMuscles();
      expect(result.primary).toEqual([]);
      expect(result.sessionCount).toBe(0);
    });
  });

  describe('strengthLevels', () => {
    beforeEach(async () => {
      await prisma.measurement.deleteMany();
      await prisma.profile.upsert({
        where: { id: 1 },
        update: { sex: 'male' },
        create: { id: 1, sex: 'male' },
      });
      // the standards match seeded names; create the bench standard exercise
      await prisma.exercise.create({
        data: {
          id: 'ex-std-bench',
          name: 'Barbell Bench Press - Medium Grip',
          primaryMuscles: JSON.stringify(['chest']),
          secondaryMuscles: JSON.stringify([]),
          isCustom: false,
        },
      });
    });

    it('reports not ready without bodyweight', async () => {
      const result = await service.strengthLevels();
      expect(result.ready).toBe(false);
      expect(result.reason).toBe('no-bodyweight');
    });

    it('reports not ready without sex in profile', async () => {
      await prisma.profile.update({ where: { id: 1 }, data: { sex: null } });
      await prisma.measurement.create({
        data: { type: 'weight', value: 80 },
      });
      const result = await service.strengthLevels();
      expect(result.ready).toBe(false);
      expect(result.reason).toBe('no-profile');
    });

    it('computes the level for lifts with history', async () => {
      await prisma.measurement.create({
        data: { type: 'weight', value: 80 },
      });
      // log a finished bench session: 100kg × 5 → e1RM ≈ 116.7 (advanced @80kg starts 118)
      const session = await sessions.start();
      const withExercise = await sessions.addExercise(session.id, 'ex-std-bench');
      await sessions.replaceSets(session.id, withExercise.exercises[0].id, [
        { weightKg: 100, reps: 5 },
      ]);
      await sessions.finish(session.id);

      const result = await service.strengthLevels();
      expect(result.ready).toBe(true);
      const bench = result.levels.find((l) => l.lift === 'bench');
      expect(bench).toBeDefined();
      expect(bench!.e1rm).toBeCloseTo(116.67, 1);
      expect(bench!.level).toBe('intermediate');
      expect(bench!.nextLevel).toBe('advanced');
      // lifts without history have no level
      const squat = result.levels.find((l) => l.lift === 'squat');
      expect(squat!.e1rm).toBeNull();
    });
  });
});
