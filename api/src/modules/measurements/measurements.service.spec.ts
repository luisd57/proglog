import { BadRequestException } from '@nestjs/common';
import { execSync } from 'child_process';
import { PrismaService } from '../../prisma/prisma.service';
import { MeasurementsService } from './measurements.service';

describe('MeasurementsService', () => {
  let prisma: PrismaService;
  let service: MeasurementsService;

  beforeAll(() => {
    process.env.DATABASE_URL = 'file:/tmp/measurements-test.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });
    prisma = new PrismaService();
    service = new MeasurementsService(prisma);
  });

  beforeEach(async () => {
    await prisma.measurement.deleteMany();
  });

  afterAll(async () => {
    await prisma.$disconnect();
  });

  it('adds measurements and lists a type chronologically', async () => {
    await service.add({ type: 'weight', value: 82.0, measuredAt: '2026-06-01' });
    await service.add({ type: 'weight', value: 81.4, measuredAt: '2026-06-08' });
    await service.add({ type: 'waist', value: 84, measuredAt: '2026-06-08' });

    const weights = await service.series('weight');
    expect(weights.map((m) => m.value)).toEqual([82.0, 81.4]);
  });

  it('returns the latest value of a type', async () => {
    await service.add({ type: 'weight', value: 82.0, measuredAt: '2026-06-01' });
    await service.add({ type: 'weight', value: 81.4, measuredAt: '2026-06-08' });
    expect(await service.latest('weight')).toBe(81.4);
    expect(await service.latest('bodyfat')).toBeNull();
  });

  it('rejects unknown types and non-positive values', async () => {
    await expect(
      service.add({ type: 'mood', value: 5 }),
    ).rejects.toThrow(BadRequestException);
    await expect(
      service.add({ type: 'weight', value: 0 }),
    ).rejects.toThrow(BadRequestException);
  });

  it('deletes a measurement', async () => {
    const created = await service.add({ type: 'weight', value: 82 });
    await service.remove(created.id);
    expect(await service.series('weight')).toEqual([]);
  });
});
