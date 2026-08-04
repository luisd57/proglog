import { TestBed } from '@angular/core/testing';
import { SessionExercise, SetInput } from '../../shared/utils/session.model';
import { SessionExerciseCardComponent } from './session-exercise-card.component';

const BASE: SessionExercise = {
  id: 'se-1',
  sort_order: 0,
  notes: null,
  exercise: {
    id: 'ex-bench',
    name: 'Bench Press',
    primary_muscles: ['chest'],
    secondary_muscles: ['triceps'],
    equipment: 'barbell',
    category: 'strength',
    instructions: null,
    is_custom: false,
  },
  sets: [],
  target_sets: 3,
  target_reps: 8,
  rest_seconds: 150,
  previous_sets: [
    { id: 'p1', set_number: 1, weight_kg: 80, reps: 8, is_warmup: false, notes: null },
    { id: 'p2', set_number: 2, weight_kg: 80, reps: 7, is_warmup: false, notes: null },
  ],
};

describe('SessionExerciseCardComponent', () => {
  async function create(data: SessionExercise = BASE) {
    const fixture = TestBed.createComponent(SessionExerciseCardComponent);
    fixture.componentRef.setInput('data', data);
    fixture.componentRef.setInput('best', { best_weight_kg: 80, best_e1rm: 101.33 });
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    return { fixture, card: fixture.componentInstance };
  }

  it('initializes rows from existing sets', async () => {
    const { card } = await create({
      ...BASE,
      sets: [
        { id: 's1', set_number: 1, weight_kg: 60, reps: 10, is_warmup: true, notes: null },
      ],
    });
    expect(card.rows()).toEqual([
      expect.objectContaining({ weight_kg: 60, reps: 10, is_warmup: true }),
    ]);
  });

  it('prefills a new set from the previous session at the same position', async () => {
    const { card } = await create();
    card.addSet();
    card.addSet();
    card.addSet();
    expect(card.rows()[0]).toEqual(
      expect.objectContaining({ weight_kg: 80, reps: 8 }),
    );
    expect(card.rows()[1]).toEqual(
      expect.objectContaining({ weight_kg: 80, reps: 7 }),
    );
    // beyond previous sets: repeat the last row
    expect(card.rows()[2]).toEqual(
      expect.objectContaining({ weight_kg: 80, reps: 7 }),
    );
  });

  it('marking a set done emits all sets for saving and requests a rest', async () => {
    const { card } = await create();
    let rest: number | null = null;
    let saved: SetInput[] | null = null;
    card.rest.subscribe((r) => (rest = r));
    card.saveSets.subscribe((sets) => (saved = sets));

    card.addSet();
    card.markDone(0);

    expect(saved).toEqual([
      expect.objectContaining({ weight_kg: 80, reps: 8, is_warmup: false }),
    ]);
    expect(rest).toBe(150);
  });

  it('flags a PR set against the provided best', async () => {
    const { card } = await create();
    card.addSet();
    card.updateRow(0, 'weight_kg', '85');
    expect(card.isRowPr(card.rows()[0])).toBe(true);

    card.updateRow(0, 'weight_kg', '75');
    expect(card.isRowPr(card.rows()[0])).toBe(false);

    // warmups never count as PRs
    card.updateRow(0, 'weight_kg', '85');
    card.toggleWarmup(0);
    expect(card.isRowPr(card.rows()[0])).toBe(false);
  });
});
