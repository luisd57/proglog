import { BadRequestException, Injectable, NotFoundException } from '@nestjs/common';
import { Measurement } from '@prisma/client';
import { PrismaService } from '../../prisma/prisma.service';

export const MEASUREMENT_TYPES = [
  'weight', 'bodyfat', 'neck', 'shoulders', 'chest', 'waist', 'hips',
  'bicepL', 'bicepR', 'forearmL', 'forearmR', 'thighL', 'thighR',
  'calfL', 'calfR',
] as const;

export type MeasurementType = (typeof MEASUREMENT_TYPES)[number];

export interface MeasurementInput {
  type: string;
  value: number;
  measuredAt?: string;
}

@Injectable()
export class MeasurementsService {
  constructor(private readonly prisma: PrismaService) {}

  async add(input: MeasurementInput): Promise<Measurement> {
    if (!MEASUREMENT_TYPES.includes(input.type as MeasurementType)) {
      throw new BadRequestException(`Unknown measurement type: ${input.type}`);
    }
    if (!(input.value > 0)) {
      throw new BadRequestException('Value must be positive');
    }
    return this.prisma.measurement.create({
      data: {
        type: input.type,
        value: input.value,
        measuredAt: input.measuredAt ? new Date(input.measuredAt) : new Date(),
      },
    });
  }

  async series(type: string): Promise<Measurement[]> {
    return this.prisma.measurement.findMany({
      where: { type },
      orderBy: { measuredAt: 'asc' },
    });
  }

  async latest(type: string): Promise<number | null> {
    const measurement = await this.prisma.measurement.findFirst({
      where: { type },
      orderBy: { measuredAt: 'desc' },
    });
    return measurement?.value ?? null;
  }

  async remove(id: string): Promise<void> {
    const found = await this.prisma.measurement.findUnique({ where: { id } });
    if (!found) {
      throw new NotFoundException(`Measurement ${id} not found`);
    }
    await this.prisma.measurement.delete({ where: { id } });
  }
}
