import { Injectable } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { epley1Rm } from './e1rm';
import {
  Level,
  levelFor,
  STRENGTH_STANDARDS,
} from './strength-standards';

export interface ExerciseBest {
  bestWeightKg: number | null;
  bestE1rm: number | null;
}

export interface SeriesPoint {
  sessionId: string;
  date: Date;
  topSet: { weightKg: number; reps: number };
  volume: number;
  e1rm: number;
}

export interface PrEvent {
  date: Date;
  weightKg: number;
  reps: number;
  e1rm: number;
}

export interface ExerciseSeries {
  points: SeriesPoint[];
  prs: PrEvent[];
}

export interface StrengthLevelEntry {
  lift: string;
  label: string;
  exerciseId: string | null;
  e1rm: number | null;
  level: Level | null;
  nextLevel: Level | null;
  progress: number | null;
  thresholds: number[];
}

export interface StrengthLevelsResult {
  ready: boolean;
  reason?: 'no-profile' | 'no-bodyweight';
  bodyweightKg?: number;
  levels: StrengthLevelEntry[];
}

export interface OverviewTotals {
  workouts: number;
  volumeKg: number;
  reps: number;
  sets: number;
  heaviestKg: number;
  timeSeconds: number;
}

export interface OverviewResult {
  period: string;
  current: OverviewTotals;
  previous: OverviewTotals | null;
  cumulativeVolume: { date: string; value: number }[];
}

const PERIOD_DAYS: Record<string, number | null> = {
  '7d': 7,
  '30d': 30,
  '90d': 90,
  '365d': 365,
  all: null,
};

@Injectable()
export class StatsService {
  constructor(private readonly prisma: PrismaService) {}

  async exerciseBest(
    exerciseId: string,
    excludeSessionId?: string,
  ): Promise<ExerciseBest> {
    const sets = await this.prisma.setLog.findMany({
      where: {
        isWarmup: false,
        sessionExercise: {
          exerciseId,
          session: {
            finishedAt: { not: null },
            ...(excludeSessionId && { id: { not: excludeSessionId } }),
          },
        },
      },
    });
    if (sets.length === 0) {
      return { bestWeightKg: null, bestE1rm: null };
    }
    return {
      bestWeightKg: Math.max(...sets.map((s) => s.weightKg)),
      bestE1rm: Math.max(...sets.map((s) => epley1Rm(s.weightKg, s.reps))),
    };
  }

  async exerciseSeries(exerciseId: string): Promise<ExerciseSeries> {
    const sessionExercises = await this.prisma.sessionExercise.findMany({
      where: { exerciseId, session: { finishedAt: { not: null } } },
      orderBy: { session: { startedAt: 'asc' } },
      include: {
        session: true,
        sets: { where: { isWarmup: false }, orderBy: { setNumber: 'asc' } },
      },
    });

    const points: SeriesPoint[] = [];
    const prs: PrEvent[] = [];
    let bestWeight = -Infinity;
    let bestE1rm = -Infinity;

    for (const se of sessionExercises) {
      if (se.sets.length === 0) continue;

      let top = se.sets[0];
      let topE1rm = epley1Rm(top.weightKg, top.reps);
      let volume = 0;
      for (const set of se.sets) {
        volume += set.weightKg * set.reps;
        const e1rm = epley1Rm(set.weightKg, set.reps);
        if (e1rm > topE1rm) {
          top = set;
          topE1rm = e1rm;
        }
      }

      points.push({
        sessionId: se.sessionId,
        date: se.session.startedAt,
        topSet: { weightKg: top.weightKg, reps: top.reps },
        volume,
        e1rm: topE1rm,
      });

      // PR events: best set of the session beats everything before it
      const sessionBestWeight = Math.max(...se.sets.map((s) => s.weightKg));
      if (sessionBestWeight > bestWeight || topE1rm > bestE1rm) {
        prs.push({
          date: se.session.startedAt,
          weightKg: top.weightKg,
          reps: top.reps,
          e1rm: topE1rm,
        });
      }
      bestWeight = Math.max(bestWeight, sessionBestWeight);
      bestE1rm = Math.max(bestE1rm, topE1rm);
    }

    return { points, prs };
  }

  async weeklyMuscles(): Promise<{
    primary: string[];
    secondary: string[];
    sessionCount: number;
  }> {
    const since = new Date(Date.now() - 7 * 24 * 3600 * 1000);
    const sessionExercises = await this.prisma.sessionExercise.findMany({
      where: {
        session: { finishedAt: { not: null }, startedAt: { gte: since } },
        sets: { some: { isWarmup: false } },
      },
      include: { exercise: true },
    });

    const primary = new Set<string>();
    const secondary = new Set<string>();
    const sessionIds = new Set<string>();
    for (const se of sessionExercises) {
      sessionIds.add(se.sessionId);
      (JSON.parse(se.exercise.primaryMuscles) as string[]).forEach((m) =>
        primary.add(m),
      );
      (JSON.parse(se.exercise.secondaryMuscles) as string[]).forEach((m) =>
        secondary.add(m),
      );
    }
    for (const m of primary) secondary.delete(m);
    return {
      primary: [...primary],
      secondary: [...secondary],
      sessionCount: sessionIds.size,
    };
  }

  async overview(period: string): Promise<OverviewResult> {
    const resolved = period in PERIOD_DAYS ? period : '7d';
    const days = PERIOD_DAYS[resolved];
    const DAY_MS = 24 * 3600 * 1000;
    const now = Date.now();

    const currentStart = days === null ? new Date(0) : new Date(now - days * DAY_MS);
    const fetchSince =
      days === null ? new Date(0) : new Date(now - 2 * days * DAY_MS);

    const sessions = await this.prisma.session.findMany({
      where: { finishedAt: { not: null }, startedAt: { gte: fetchSince } },
      include: {
        exercises: { include: { sets: { where: { isWarmup: false } } } },
      },
    });

    const current = sessions.filter((s) => s.startedAt >= currentStart);
    const previous =
      days === null
        ? null
        : sessions.filter((s) => s.startedAt < currentStart);

    return {
      period: resolved,
      current: this.totalsOf(current),
      previous: previous === null ? null : this.totalsOf(previous),
      cumulativeVolume: this.cumulativeVolume(current, currentStart, days),
    };
  }

  private totalsOf(
    sessions: {
      startedAt: Date;
      finishedAt: Date | null;
      exercises: { sets: { weightKg: number; reps: number }[] }[];
    }[],
  ): OverviewTotals {
    let volumeKg = 0;
    let reps = 0;
    let sets = 0;
    let heaviestKg = 0;
    let timeSeconds = 0;
    for (const s of sessions) {
      if (s.finishedAt) {
        timeSeconds += Math.max(
          0,
          Math.round((s.finishedAt.getTime() - s.startedAt.getTime()) / 1000),
        );
      }
      for (const se of s.exercises) {
        for (const set of se.sets) {
          volumeKg += set.weightKg * set.reps;
          reps += set.reps;
          sets += 1;
          if (set.weightKg > heaviestKg) heaviestKg = set.weightKg;
        }
      }
    }
    return { workouts: sessions.length, volumeKg, reps, sets, heaviestKg, timeSeconds };
  }

  // Volume bucketed by server-local calendar day, one running-sum point per day
  // from the window start (or first session for all-time) through today.
  private cumulativeVolume(
    sessions: {
      startedAt: Date;
      exercises: { sets: { weightKg: number; reps: number }[] }[];
    }[],
    currentStart: Date,
    days: number | null,
  ): { date: string; value: number }[] {
    const dayKey = (d: Date) =>
      `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
        d.getDate(),
      ).padStart(2, '0')}`;

    const perDay = new Map<string, number>();
    for (const s of sessions) {
      let v = 0;
      for (const se of s.exercises) {
        for (const set of se.sets) v += set.weightKg * set.reps;
      }
      const key = dayKey(s.startedAt);
      perDay.set(key, (perDay.get(key) ?? 0) + v);
    }

    const earliest =
      days === null
        ? sessions.length
          ? new Date(Math.min(...sessions.map((s) => s.startedAt.getTime())))
          : new Date()
        : currentStart;

    const cursor = new Date(
      earliest.getFullYear(),
      earliest.getMonth(),
      earliest.getDate(),
    );
    const today = new Date();
    const last = new Date(today.getFullYear(), today.getMonth(), today.getDate());

    const points: { date: string; value: number }[] = [];
    let running = 0;
    while (cursor <= last) {
      running += perDay.get(dayKey(cursor)) ?? 0;
      points.push({ date: dayKey(cursor), value: running });
      cursor.setDate(cursor.getDate() + 1);
    }
    return points;
  }

  async strengthLevels(): Promise<StrengthLevelsResult> {
    const weight = await this.prisma.measurement.findFirst({
      where: { type: 'weight' },
      orderBy: { measuredAt: 'desc' },
    });
    if (!weight) {
      return { ready: false, reason: 'no-bodyweight', levels: [] };
    }
    const profile = await this.prisma.profile.findUnique({ where: { id: 1 } });
    const sex = profile?.sex === 'female' ? 'female' : profile?.sex === 'male' ? 'male' : null;
    if (!sex) {
      return { ready: false, reason: 'no-profile', levels: [] };
    }

    const levels: StrengthLevelEntry[] = [];
    for (const standard of STRENGTH_STANDARDS) {
      const exercise = await this.prisma.exercise.findFirst({
        where: { name: { in: standard.exerciseNames } },
      });
      const best = exercise
        ? await this.exerciseBest(exercise.id)
        : { bestWeightKg: null, bestE1rm: null };

      if (exercise && best.bestE1rm !== null) {
        const result = levelFor(standard[sex], weight.value, best.bestE1rm);
        levels.push({
          lift: standard.lift,
          label: standard.label,
          exerciseId: exercise.id,
          e1rm: best.bestE1rm,
          level: result.level,
          nextLevel: result.nextLevel,
          progress: result.progress,
          thresholds: result.thresholds,
        });
      } else {
        const thresholds = levelFor(standard[sex], weight.value, 0).thresholds;
        levels.push({
          lift: standard.lift,
          label: standard.label,
          exerciseId: exercise?.id ?? null,
          e1rm: null,
          level: null,
          nextLevel: null,
          progress: null,
          thresholds,
        });
      }
    }
    return { ready: true, bodyweightKg: weight.value, levels };
  }
}
