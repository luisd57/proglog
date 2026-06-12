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

export function toExerciseDto(exercise: Exercise): ExerciseDto {
  return {
    ...exercise,
    primaryMuscles: JSON.parse(exercise.primaryMuscles) as string[],
    secondaryMuscles: JSON.parse(exercise.secondaryMuscles) as string[],
  };
}

function searchTokens(search: string): string[] {
  return search
    .toLowerCase()
    .split(/\s+/)
    .map((t) => t.replace(/[^a-z0-9]/g, ''))
    .filter(Boolean)
    .map((t) => (t.length > 2 && t.endsWith('s') ? t.slice(0, -1) : t));
}

function normalizeName(name: string): string {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

// Rank already-filtered matches so the closest one floats to the top:
// exact name match, then fewest words, then genuine whole-word matches over
// mid-word coincidences (e.g. "chin" inside "machine"), then shorter name.
// Relies on a stable sort over an alphabetically-ordered input for ties.
function rankBySearch(
  items: ExerciseDto[],
  tokens: string[],
  normalizedQuery: string,
): ExerciseDto[] {
  return items
    .map((exercise) => {
      const norm = normalizeName(exercise.name);
      const words = norm.split(' ');
      const allWholeWord = tokens.every((t) =>
        words.some((w) => w === t || w.startsWith(t)),
      );
      return {
        exercise,
        exact: norm === normalizedQuery ? 0 : 1,
        wordCount: words.length,
        wholeWord: allWholeWord ? 0 : 1,
        length: exercise.name.length,
      };
    })
    .sort(
      (a, b) =>
        a.exact - b.exact ||
        a.wordCount - b.wordCount ||
        a.wholeWord - b.wholeWord ||
        a.length - b.length,
    )
    .map((ranked) => ranked.exercise);
}

@Injectable()
export class ExercisesService {
  constructor(private readonly prisma: PrismaService) {}

  async list(filters?: ExerciseFilters): Promise<ExerciseDto[]> {
    const where: Record<string, unknown>[] = [];
    // tokenize: each word must appear in the name (any order), tolerant of
    // hyphens/punctuation and simple plurals, so "chin ups" finds "Chin-Up"
    // and "front raise" finds "Front Cable Raise"
    const tokens = filters?.search ? searchTokens(filters.search) : [];
    for (const token of tokens) {
      where.push({ name: { contains: token } });
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
    const dtos = exercises.map(toExerciseDto);
    return tokens.length
      ? rankBySearch(dtos, tokens, normalizeName(filters!.search!))
      : dtos;
  }

  async get(id: string): Promise<ExerciseDto> {
    const exercise = await this.prisma.exercise.findUnique({ where: { id } });
    if (!exercise) {
      throw new NotFoundException(`Exercise ${id} not found`);
    }
    return toExerciseDto(exercise);
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
    return toExerciseDto(created);
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
    return toExerciseDto(updated);
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

