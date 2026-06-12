import { INestApplication } from '@nestjs/common';
import { Test, TestingModule } from '@nestjs/testing';
import { execSync } from 'child_process';
import request from 'supertest';
import { App } from 'supertest/types';
import { AppModule } from './../src/app.module';
import { PrismaService } from './../src/prisma/prisma.service';

describe('Exercises (e2e)', () => {
  let app: INestApplication<App>;

  beforeAll(async () => {
    process.env.DATABASE_URL = 'file:/tmp/exercises-e2e.db';
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
        name: 'Barbell Squat',
        primaryMuscles: JSON.stringify(['quadriceps']),
        secondaryMuscles: JSON.stringify(['glutes', 'lower back']),
        equipment: 'barbell',
        isCustom: false,
      },
    });
  });

  afterAll(async () => {
    await app.close();
  });

  it('GET /api/exercises lists exercises with parsed muscles', async () => {
    const res = await request(app.getHttpServer())
      .get('/api/exercises')
      .expect(200);
    expect(res.body).toHaveLength(1);
    expect(res.body[0].name).toBe('Barbell Squat');
    expect(res.body[0].primaryMuscles).toEqual(['quadriceps']);
  });

  it('GET /api/exercises?muscle=glutes filters by muscle', async () => {
    const hit = await request(app.getHttpServer())
      .get('/api/exercises?muscle=glutes')
      .expect(200);
    expect(hit.body).toHaveLength(1);

    const miss = await request(app.getHttpServer())
      .get('/api/exercises?muscle=biceps')
      .expect(200);
    expect(miss.body).toHaveLength(0);
  });

  it('POST /api/exercises creates a custom exercise, DELETE removes it', async () => {
    const created = await request(app.getHttpServer())
      .post('/api/exercises')
      .send({ name: 'My Custom Press', primaryMuscles: ['shoulders'] })
      .expect(201);
    expect(created.body.isCustom).toBe(true);

    await request(app.getHttpServer())
      .delete(`/api/exercises/${created.body.id}`)
      .expect(200);

    await request(app.getHttpServer())
      .get(`/api/exercises/${created.body.id}`)
      .expect(404);
  });

  it('POST /api/exercises rejects a missing name', async () => {
    await request(app.getHttpServer())
      .post('/api/exercises')
      .send({ primaryMuscles: ['chest'] })
      .expect(400);
  });
});
