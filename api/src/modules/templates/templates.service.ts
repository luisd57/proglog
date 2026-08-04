import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';
import {
  ExerciseDto,
  toExerciseDto,
} from '../exercises/exercises.service';

export interface TemplateExerciseInput {
  exerciseId: string;
  targetSets?: number;
  targetReps?: number;
  restSeconds?: number;
}

export interface TemplateInput {
  name: string;
  exercises: TemplateExerciseInput[];
}

export interface TemplateExerciseDto {
  id: string;
  sortOrder: number;
  targetSets: number | null;
  targetReps: number | null;
  restSeconds: number | null;
  exercise: ExerciseDto;
}

export interface TemplateDto {
  id: string;
  name: string;
  sortOrder: number;
  exercises: TemplateExerciseDto[];
}

export interface TemplateSummaryDto {
  id: string;
  name: string;
  sortOrder: number;
  exerciseCount: number;
}

const templateInclude = {
  exercises: {
    orderBy: { sortOrder: 'asc' as const },
    include: { exercise: true },
  },
} satisfies Prisma.WorkoutTemplateInclude;

type TemplateWithExercises = Prisma.WorkoutTemplateGetPayload<{
  include: typeof templateInclude;
}>;

function toTemplateDto(template: TemplateWithExercises): TemplateDto {
  return {
    id: template.id,
    name: template.name,
    sortOrder: template.sortOrder,
    exercises: template.exercises.map((te) => ({
      id: te.id,
      sortOrder: te.sortOrder,
      targetSets: te.targetSets,
      targetReps: te.targetReps,
      restSeconds: te.restSeconds,
      exercise: toExerciseDto(te.exercise),
    })),
  };
}

@Injectable()
export class TemplatesService {
  constructor(private readonly prisma: PrismaService) {}

  async list(): Promise<TemplateSummaryDto[]> {
    const templates = await this.prisma.workoutTemplate.findMany({
      where: { archivedAt: null },
      orderBy: { sortOrder: 'asc' },
      include: { _count: { select: { exercises: true } } },
    });
    return templates.map((t) => ({
      id: t.id,
      name: t.name,
      sortOrder: t.sortOrder,
      exerciseCount: t._count.exercises,
    }));
  }

  async get(id: string): Promise<TemplateDto> {
    const template = await this.prisma.workoutTemplate.findUnique({
      where: { id },
      include: templateInclude,
    });
    if (!template) {
      throw new NotFoundException(`Template ${id} not found`);
    }
    return toTemplateDto(template);
  }

  async create(input: TemplateInput): Promise<TemplateDto> {
    this.validate(input);
    const last = await this.prisma.workoutTemplate.findFirst({
      orderBy: { sortOrder: 'desc' },
    });
    const created = await this.prisma.workoutTemplate.create({
      data: {
        name: input.name.trim(),
        sortOrder: (last?.sortOrder ?? -1) + 1,
        exercises: {
          create: input.exercises.map((e, index) => ({
            exerciseId: e.exerciseId,
            sortOrder: index,
            targetSets: e.targetSets ?? null,
            targetReps: e.targetReps ?? null,
            restSeconds: e.restSeconds ?? null,
          })),
        },
      },
      include: templateInclude,
    });
    return toTemplateDto(created);
  }

  async update(id: string, input: TemplateInput): Promise<TemplateDto> {
    this.validate(input);
    await this.get(id);
    const updated = await this.prisma.workoutTemplate.update({
      where: { id },
      data: {
        name: input.name.trim(),
        exercises: {
          deleteMany: {},
          create: input.exercises.map((e, index) => ({
            exerciseId: e.exerciseId,
            sortOrder: index,
            targetSets: e.targetSets ?? null,
            targetReps: e.targetReps ?? null,
            restSeconds: e.restSeconds ?? null,
          })),
        },
      },
      include: templateInclude,
    });
    return toTemplateDto(updated);
  }

  async remove(id: string): Promise<void> {
    await this.get(id);
    await this.prisma.workoutTemplate.delete({ where: { id } });
  }

  async muscles(
    id: string,
  ): Promise<{ primary: string[]; secondary: string[] }> {
    const template = await this.get(id);
    const primary = new Set<string>();
    const secondary = new Set<string>();
    for (const te of template.exercises) {
      te.exercise.primaryMuscles.forEach((m) => primary.add(m));
      te.exercise.secondaryMuscles.forEach((m) => secondary.add(m));
    }
    for (const m of primary) secondary.delete(m);
    return { primary: [...primary], secondary: [...secondary] };
  }

  private validate(input: TemplateInput): void {
    if (!input.name?.trim()) {
      throw new BadRequestException('Name is required');
    }
    if (!Array.isArray(input.exercises)) {
      throw new BadRequestException('Exercises must be a list');
    }
  }
}

