import { Injectable, NotFoundException } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import { ExerciseDto, toExerciseDto } from '../exercises/exercises.service';

export interface SetInput {
  weightKg: number;
  reps: number;
  isWarmup?: boolean;
  notes?: string;
}

export interface SetDto {
  id: string;
  setNumber: number;
  weightKg: number;
  reps: number;
  isWarmup: boolean;
  notes: string | null;
}

export interface SessionExerciseDto {
  id: string;
  sortOrder: number;
  notes: string | null;
  exercise: ExerciseDto;
  sets: SetDto[];
  targetSets: number | null;
  targetReps: number | null;
  restSeconds: number;
  previousSets: SetDto[];
}

export interface SessionDto {
  id: string;
  templateId: string | null;
  templateName: string | null;
  startedAt: Date;
  finishedAt: Date | null;
  notes: string | null;
  exercises: SessionExerciseDto[];
}

export interface SessionSummaryDto {
  id: string;
  templateName: string | null;
  startedAt: Date;
  finishedAt: Date | null;
  exerciseCount: number;
  setCount: number;
}

const sessionInclude = {
  template: { include: { exercises: true } },
  exercises: {
    orderBy: { sortOrder: 'asc' as const },
    include: {
      exercise: true,
      sets: { orderBy: { setNumber: 'asc' as const } },
    },
  },
} satisfies Prisma.SessionInclude;

type SessionWithDetails = Prisma.SessionGetPayload<{
  include: typeof sessionInclude;
}>;

@Injectable()
export class SessionsService {
  constructor(private readonly prisma: PrismaService) {}

  async start(templateId?: string): Promise<SessionDto> {
    let exercisesCreate: Prisma.SessionExerciseCreateWithoutSessionInput[] = [];
    if (templateId) {
      const template = await this.prisma.workoutTemplate.findUnique({
        where: { id: templateId },
        include: { exercises: { orderBy: { sortOrder: 'asc' } } },
      });
      if (!template) {
        throw new NotFoundException(`Template ${templateId} not found`);
      }
      exercisesCreate = template.exercises.map((te, index) => ({
        exercise: { connect: { id: te.exerciseId } },
        sortOrder: index,
      }));
    }
    const created = await this.prisma.session.create({
      data: {
        templateId: templateId ?? null,
        exercises: { create: exercisesCreate },
      },
    });
    return this.get(created.id);
  }

  async get(id: string): Promise<SessionDto> {
    const session = await this.prisma.session.findUnique({
      where: { id },
      include: sessionInclude,
    });
    if (!session) {
      throw new NotFoundException(`Session ${id} not found`);
    }
    return this.toDto(session);
  }

  async list(): Promise<SessionSummaryDto[]> {
    const sessions = await this.prisma.session.findMany({
      orderBy: { startedAt: 'desc' },
      include: {
        template: true,
        exercises: { include: { _count: { select: { sets: true } } } },
      },
    });
    return sessions.map((s) => ({
      id: s.id,
      templateName: s.template?.name ?? null,
      startedAt: s.startedAt,
      finishedAt: s.finishedAt,
      exerciseCount: s.exercises.length,
      setCount: s.exercises.reduce((sum, e) => sum + e._count.sets, 0),
    }));
  }

  async replaceSets(
    sessionId: string,
    sessionExerciseId: string,
    sets: SetInput[],
  ): Promise<void> {
    await this.assertSessionExercise(sessionId, sessionExerciseId);
    await this.prisma.$transaction([
      this.prisma.setLog.deleteMany({ where: { sessionExerciseId } }),
      this.prisma.setLog.createMany({
        data: sets.map((set, index) => ({
          sessionExerciseId,
          setNumber: index + 1,
          weightKg: set.weightKg,
          reps: set.reps,
          isWarmup: set.isWarmup ?? false,
          notes: set.notes ?? null,
        })),
      }),
    ]);
  }

  async addExercise(sessionId: string, exerciseId: string): Promise<SessionDto> {
    const session = await this.get(sessionId);
    await this.prisma.sessionExercise.create({
      data: {
        sessionId,
        exerciseId,
        sortOrder: session.exercises.length,
      },
    });
    return this.get(sessionId);
  }

  async removeExercise(
    sessionId: string,
    sessionExerciseId: string,
  ): Promise<SessionDto> {
    await this.assertSessionExercise(sessionId, sessionExerciseId);
    await this.prisma.sessionExercise.delete({
      where: { id: sessionExerciseId },
    });
    return this.get(sessionId);
  }

  async updateNotes(
    sessionId: string,
    sessionExerciseId: string | null,
    notes: string,
  ): Promise<void> {
    if (sessionExerciseId) {
      await this.assertSessionExercise(sessionId, sessionExerciseId);
      await this.prisma.sessionExercise.update({
        where: { id: sessionExerciseId },
        data: { notes },
      });
    } else {
      await this.get(sessionId);
      await this.prisma.session.update({
        where: { id: sessionId },
        data: { notes },
      });
    }
  }

  async finish(id: string): Promise<SessionDto> {
    await this.get(id);
    await this.prisma.session.update({
      where: { id },
      data: { finishedAt: new Date() },
    });
    return this.get(id);
  }

  async remove(id: string): Promise<void> {
    await this.get(id);
    await this.prisma.session.delete({ where: { id } });
  }

  private async assertSessionExercise(
    sessionId: string,
    sessionExerciseId: string,
  ): Promise<void> {
    const found = await this.prisma.sessionExercise.findFirst({
      where: { id: sessionExerciseId, sessionId },
    });
    if (!found) {
      throw new NotFoundException(
        `Exercise ${sessionExerciseId} not found in session ${sessionId}`,
      );
    }
  }

  private async toDto(session: SessionWithDetails): Promise<SessionDto> {
    const profile = await this.prisma.profile.findUnique({ where: { id: 1 } });
    const defaultRest = profile?.defaultRestSeconds ?? 120;
    const targets = new Map(
      (session.template?.exercises ?? []).map((te) => [te.exerciseId, te]),
    );

    const exercises = await Promise.all(
      session.exercises.map(async (se) => {
        const previous = await this.prisma.sessionExercise.findFirst({
          where: {
            exerciseId: se.exerciseId,
            sessionId: { not: session.id },
            session: { finishedAt: { not: null } },
          },
          orderBy: { session: { startedAt: 'desc' } },
          include: { sets: { orderBy: { setNumber: 'asc' } } },
        });
        const target = targets.get(se.exerciseId);
        return {
          id: se.id,
          sortOrder: se.sortOrder,
          notes: se.notes,
          exercise: toExerciseDto(se.exercise),
          sets: se.sets,
          targetSets: target?.targetSets ?? null,
          targetReps: target?.targetReps ?? null,
          restSeconds: target?.restSeconds ?? defaultRest,
          previousSets: previous?.sets ?? [],
        };
      }),
    );

    return {
      id: session.id,
      templateId: session.templateId,
      templateName: session.template?.name ?? null,
      startedAt: session.startedAt,
      finishedAt: session.finishedAt,
      notes: session.notes,
      exercises,
    };
  }
}
