import { Injectable } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';
import { epley1Rm } from './e1rm';

export interface ExerciseBest {
  bestWeightKg: number | null;
  bestE1rm: number | null;
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
}
