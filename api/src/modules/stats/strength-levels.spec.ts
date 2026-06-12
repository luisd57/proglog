import { levelFor, STRENGTH_STANDARDS } from './strength-standards';

describe('levelFor', () => {
  // simplified table for tests: bodyweight rows 60..100
  const table = [
    { bodyweightKg: 60, thresholds: [40, 60, 80, 100, 120] },
    { bodyweightKg: 100, thresholds: [80, 100, 120, 140, 160] },
  ];

  it('classifies below beginner as untrained with progress toward beginner', () => {
    const result = levelFor(table, 60, 20);
    expect(result.level).toBe('untrained');
    expect(result.nextLevel).toBe('beginner');
    expect(result.progress).toBeCloseTo(0.5, 5);
  });

  it('classifies exact thresholds and interpolates progress to the next level', () => {
    const result = levelFor(table, 60, 70);
    expect(result.level).toBe('novice');
    expect(result.nextLevel).toBe('intermediate');
    expect(result.progress).toBeCloseTo(0.5, 5); // 70 between 60 and 80
  });

  it('caps at elite', () => {
    const result = levelFor(table, 60, 150);
    expect(result.level).toBe('elite');
    expect(result.nextLevel).toBeNull();
    expect(result.progress).toBe(1);
  });

  it('interpolates thresholds between bodyweight rows', () => {
    // bw 80 → thresholds midway: [60, 80, 100, 120, 140]
    const result = levelFor(table, 80, 100);
    expect(result.level).toBe('intermediate');
    expect(result.progress).toBeCloseTo(0, 5);
    expect(result.thresholds).toEqual([60, 80, 100, 120, 140]);
  });

  it('clamps bodyweight outside the table range', () => {
    expect(levelFor(table, 40, 50).thresholds).toEqual([40, 60, 80, 100, 120]);
    expect(levelFor(table, 150, 50).thresholds).toEqual([80, 100, 120, 140, 160]);
  });
});

describe('STRENGTH_STANDARDS', () => {
  it('covers the five main lifts for both sexes with ascending thresholds', () => {
    const lifts = ['squat', 'bench', 'deadlift', 'ohp', 'row'];
    for (const lift of lifts) {
      const standard = STRENGTH_STANDARDS.find((s) => s.lift === lift);
      expect(standard).toBeDefined();
      for (const sex of ['male', 'female'] as const) {
        const rows = standard![sex];
        expect(rows.length).toBeGreaterThanOrEqual(5);
        for (const row of rows) {
          const sorted = [...row.thresholds].sort((a, b) => a - b);
          expect(row.thresholds).toEqual(sorted);
          expect(row.thresholds).toHaveLength(5);
        }
      }
    }
  });
});
