import { INestApplication } from '@nestjs/common';
import { Test, TestingModule } from '@nestjs/testing';
import { execSync } from 'child_process';
import request from 'supertest';
import { App } from 'supertest/types';
import { AppModule } from './../src/app.module';
import { PrismaService } from './../src/prisma/prisma.service';

describe('Stats (e2e)', () => {
  let app: INestApplication<App>;

  beforeAll(async () => {
    process.env.DATABASE_URL = 'file:/tmp/stats-e2e.db';
    execSync('npx prisma db push --force-reset --skip-generate', {
      env: process.env,
      stdio: 'pipe',
    });

    const moduleFixture: TestingModule = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    app.setGlobalPrefix('api');
    await app.init();
  });

  afterAll(async () => {
    await app.close();
  });

  it('returns the overview shape for a period', async () => {
    const res = await request(app.getHttpServer())
      .get('/api/stats/overview?period=7d')
      .expect(200);

    expect(res.body).toEqual(
      expect.objectContaining({
        period: '7d',
        current: expect.objectContaining({
          workouts: expect.any(Number),
          volumeKg: expect.any(Number),
          reps: expect.any(Number),
          sets: expect.any(Number),
          heaviestKg: expect.any(Number),
          timeSeconds: expect.any(Number),
        }),
        cumulativeVolume: expect.any(Array),
      }),
    );
    expect(res.body.previous).not.toBeNull();
  });

  it('omits the previous window for all-time', async () => {
    const res = await request(app.getHttpServer())
      .get('/api/stats/overview?period=all')
      .expect(200);
    expect(res.body.previous).toBeNull();
  });
});
