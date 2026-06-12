import {
  BadRequestException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { Exercise } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';

export interface ExerciseDto {
  id: string;
  name: string;
  primaryMuscles: string[];
  secondaryMuscles: string[];
  equipment: string | null;
  category: string | null;
  instructions: string | null;
  isCustom: boolean;
}

export interface ExerciseFilters {
  search?: string;
  muscle?: string;
  equipment?: string;
}

export interface CreateExerciseInput {
  name: string;
  primaryMuscles: string[];
  secondaryMuscles?: string[];
  equipment?: string;
  category?: string;
  instructions?: string;
}

function toDto(exercise: Exercise): ExerciseDto {
  return {
    ...exercise,
    primaryMuscles: JSON.parse(exercise.primaryMuscles) as string[],
    secondaryMuscles: JSON.parse(exercise.secondaryMuscles) as string[],
  };
}

@Injectable()
export class ExercisesService {
  constructor(private readonly prisma: PrismaService) {}

  async list(filters?: ExerciseFilters): Promise<ExerciseDto[]> {
    const where: Record<string, unknown>[] = [];
    if (filters?.search) {
      where.push({ name: { contains: filters.search } });
    }
    if (filters?.muscle) {
      // muscles are stored as JSON string arrays, so match the quoted value
      const quoted = `"${filters.muscle}"`;
      where.push({
        OR: [
          { primaryMuscles: { contains: quoted } },
          { secondaryMuscles: { contains: quoted } },
        ],
      });
    }
    if (filters?.equipment) {
      where.push({ equipment: filters.equipment });
    }
    const exercises = await this.prisma.exercise.findMany({
      where: { AND: where },
      orderBy: { name: 'asc' },
    });
    return exercises.map(toDto);
  }

  async get(id: string): Promise<ExerciseDto> {
    const exercise = await this.prisma.exercise.findUnique({ where: { id } });
    if (!exercise) {
      throw new NotFoundException(`Exercise ${id} not found`);
    }
    return toDto(exercise);
  }

  async createCustom(input: CreateExerciseInput): Promise<ExerciseDto> {
    if (!input.name?.trim()) {
      throw new BadRequestException('Name is required');
    }
    if (!input.primaryMuscles?.length) {
      throw new BadRequestException('At least one primary muscle is required');
    }
    const created = await this.prisma.exercise.create({
      data: {
        name: input.name.trim(),
        primaryMuscles: JSON.stringify(input.primaryMuscles),
        secondaryMuscles: JSON.stringify(input.secondaryMuscles ?? []),
        equipment: input.equipment ?? null,
        category: input.category ?? null,
        instructions: input.instructions ?? null,
        isCustom: true,
      },
    });
    return toDto(created);
  }

  async updateCustom(
    id: string,
    input: Partial<CreateExerciseInput>,
  ): Promise<ExerciseDto> {
    await this.assertCustom(id);
    if (input.name !== undefined && !input.name.trim()) {
      throw new BadRequestException('Name is required');
    }
    if (input.primaryMuscles !== undefined && !input.primaryMuscles.length) {
      throw new BadRequestException('At least one primary muscle is required');
    }
    const updated = await this.prisma.exercise.update({
      where: { id },
      data: {
        ...(input.name !== undefined && { name: input.name.trim() }),
        ...(input.primaryMuscles !== undefined && {
          primaryMuscles: JSON.stringify(input.primaryMuscles),
        }),
        ...(input.secondaryMuscles !== undefined && {
          secondaryMuscles: JSON.stringify(input.secondaryMuscles),
        }),
        ...(input.equipment !== undefined && { equipment: input.equipment }),
        ...(input.category !== undefined && { category: input.category }),
        ...(input.instructions !== undefined && {
          instructions: input.instructions,
        }),
      },
    });
    return toDto(updated);
  }

  async removeCustom(id: string): Promise<void> {
    await this.assertCustom(id);
    await this.prisma.exercise.delete({ where: { id } });
  }

  private async assertCustom(id: string): Promise<void> {
    const exercise = await this.prisma.exercise.findUnique({ where: { id } });
    if (!exercise) {
      throw new NotFoundException(`Exercise ${id} not found`);
    }
    if (!exercise.isCustom) {
      throw new BadRequestException(
        'Built-in exercises cannot be modified or deleted',
      );
    }
  }
}
