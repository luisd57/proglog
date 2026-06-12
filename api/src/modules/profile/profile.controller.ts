import { Body, Controller, Get, Patch } from '@nestjs/common';
import { PrismaService } from '../../prisma/prisma.service';

interface ProfileInput {
  sex?: 'male' | 'female' | null;
  birthDate?: string | null;
  defaultRestSeconds?: number;
  heightCm?: number | null;
}

@Controller('profile')
export class ProfileController {
  constructor(private readonly prisma: PrismaService) {}

  @Get()
  get() {
    return this.prisma.profile.upsert({
      where: { id: 1 },
      update: {},
      create: { id: 1 },
    });
  }

  @Patch()
  async update(@Body() input: ProfileInput) {
    return this.prisma.profile.upsert({
      where: { id: 1 },
      update: {
        ...(input.sex !== undefined && { sex: input.sex }),
        ...(input.birthDate !== undefined && {
          birthDate: input.birthDate ? new Date(input.birthDate) : null,
        }),
        ...(input.defaultRestSeconds !== undefined && {
          defaultRestSeconds: input.defaultRestSeconds,
        }),
        ...(input.heightCm !== undefined && { heightCm: input.heightCm }),
      },
      create: {
        id: 1,
        sex: input.sex ?? null,
        birthDate: input.birthDate ? new Date(input.birthDate) : null,
        defaultRestSeconds: input.defaultRestSeconds ?? 120,
        heightCm: input.heightCm ?? null,
      },
    });
  }
}
