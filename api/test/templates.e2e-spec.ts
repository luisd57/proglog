import { INestApplication } from '@nestjs/common';
import { Test, TestingModule } from '@nestjs/testing';
import { execSync } from 'child_process';
import request from 'supertest';
import { App } from 'supertest/types';
import { AppModule } from './../src/app.module';
import { PrismaService } from './../src/prisma/prisma.service';

describe('Templates (e2e)', () => {
  let app: INestApplication<App>;

  beforeAll(async () => {
    process.env.DATABASE_URL = 'file:/tmp/templates-e2e.db';
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

    await app.get(PrismaService).exercise.create({
      data: {
        id: 'ex-1',
        name: 'Bench Press',
        primaryMuscles: JSON.stringify(['chest']),
        secondaryMuscles: JSON.stringify(['triceps']),
        isCustom: false,
      },
    });
  });

  afterAll(async () => {
    await app.close();
  });

  it('creates, lists, and reports muscles for a template', async () => {
    const created = await request(app.getHttpServer())
      .post('/api/templates')
      .send({ name: 'Split A', exercises: [{ exerciseId: 'ex-1', targetSets: 3 }] })
      .expect(201);
    expect(created.body.exercises[0].exercise.name).toBe('Bench Press');

    const list = await request(app.getHttpServer())
      .get('/api/templates')
      .expect(200);
    expect(list.body).toEqual([
      expect.objectContaining({ name: 'Split A', exerciseCount: 1 }),
    ]);

    const muscles = await request(app.getHttpServer())
      .get(`/api/templates/${created.body.id}/muscles`)
      .expect(200);
    expect(muscles.body).toEqual({ primary: ['chest'], secondary: ['triceps'] });
  });
});
