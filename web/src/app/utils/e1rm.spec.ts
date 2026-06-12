import { epley1Rm, isPr } from './e1rm';

describe('epley1Rm', () => {
  it('estimates 1RM', () => {
    expect(epley1Rm(100, 10)).toBeCloseTo(133.33, 2);
  });

  it('returns 0 for no reps', () => {
    expect(epley1Rm(80, 0)).toBe(0);
  });
});

describe('isPr', () => {
  const best = { bestWeightKg: 100, bestE1rm: 110 };

  it('flags a heavier weight', () => {
    expect(isPr(102.5, 1, best)).toBe(true);
  });

  it('flags a better estimated 1RM at lower weight', () => {
    // 95 × (1 + 6/30) = 114 > 110
    expect(isPr(95, 6, best)).toBe(true);
  });

  it('does not flag a set below both bests', () => {
    expect(isPr(90, 5, best)).toBe(false); // e1rm 105
  });

  it('never flags when there is no history', () => {
    expect(isPr(100, 5, { bestWeightKg: null, bestE1rm: null })).toBe(false);
  });

  it('ignores zero-rep placeholder rows', () => {
    expect(isPr(200, 0, best)).toBe(false);
  });
});
