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
});
