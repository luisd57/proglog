import { Injectable } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { epley1Rm } from './e1rm';

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
}
