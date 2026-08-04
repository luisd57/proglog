import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { SessionExercise } from '../../services/sessions-api';
import { SessionExerciseCard } from './session-exercise-card';

const BASE: SessionExercise = {
  id: 'se-1',
  sortOrder: 0,
  notes: null,
  exercise: {
    id: 'ex-bench',
    name: 'Bench Press',
    primaryMuscles: ['chest'],
    secondaryMuscles: ['triceps'],
    equipment: 'barbell',
    category: 'strength',
    instructions: null,
    isCustom: false,
  },
  sets: [],
  targetSets: 3,
  targetReps: 8,
  restSeconds: 150,
  previousSets: [
    { id: 'p1', setNumber: 1, weightKg: 80, reps: 8, isWarmup: false, notes: null },
    { id: 'p2', setNumber: 2, weightKg: 80, reps: 7, isWarmup: false, notes: null },
  ],
};

describe('SessionExerciseCard', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
  });

  async function create(data: SessionExercise = BASE) {
    const fixture = TestBed.createComponent(SessionExerciseCard);
    fixture.componentRef.setInput('sessionId', 's-1');
    fixture.componentRef.setInput('data', data);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http
      .expectOne('/api/stats/exercise/ex-bench/best?excludeSession=s-1')
      .flush({ bestWeightKg: 80, bestE1rm: 101.33 });
    await fixture.whenStable();
    fixture.detectChanges();
    return { fixture, card: fixture.componentInstance, http };
  }

  it('initializes rows from existing sets', async () => {
    const { card } = await create({
      ...BASE,
      sets: [
        { id: 's1', setNumber: 1, weightKg: 60, reps: 10, isWarmup: true, notes: null },
      ],
    });
    expect(card.rows()).toEqual([
      expect.objectContaining({ weightKg: 60, reps: 10, isWarmup: true }),
    ]);
  });

  it('prefills a new set from the previous session at the same position', async () => {
    const { card } = await create();
    card.addSet();
    card.addSet();
    card.addSet();
    expect(card.rows()[0]).toEqual(
      expect.objectContaining({ weightKg: 80, reps: 8 }),
    );
    expect(card.rows()[1]).toEqual(
      expect.objectContaining({ weightKg: 80, reps: 7 }),
    );
    // beyond previous sets: repeat the last row
    expect(card.rows()[2]).toEqual(
      expect.objectContaining({ weightKg: 80, reps: 7 }),
    );
  });

  it('marking a set done saves all sets and requests a rest', async () => {
    const { card, http } = await create();
    let rest: number | null = null;
    card.rest.subscribe((r) => (rest = r));

    card.addSet();
    card.markDone(0);

    const req = http.expectOne(
      '/api/sessions/s-1/exercises/se-1/sets',
    );
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual([
      expect.objectContaining({ weightKg: 80, reps: 8, isWarmup: false }),
    ]);
    req.flush({ ok: true });
    expect(rest).toBe(150);
  });

  it('flags a PR set against the fetched best', async () => {
    const { card } = await create();
    card.addSet();
    card.updateRow(0, 'weightKg', '85');
    expect(card.isRowPr(card.rows()[0])).toBe(true);

    card.updateRow(0, 'weightKg', '75');
    expect(card.isRowPr(card.rows()[0])).toBe(false);

    // warmups never count as PRs
    card.updateRow(0, 'weightKg', '85');
    card.toggleWarmup(0);
    expect(card.isRowPr(card.rows()[0])).toBe(false);
  });
});

