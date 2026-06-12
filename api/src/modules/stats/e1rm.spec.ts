import { epley1Rm } from './e1rm';

describe('epley1Rm', () => {
  it('returns the weight itself for a single rep', () => {
    expect(epley1Rm(100, 1)).toBeCloseTo(103.33, 2);
  });

  it('estimates 1RM with the Epley formula', () => {
    // 100kg × (1 + 10/30) = 133.33
    expect(epley1Rm(100, 10)).toBeCloseTo(133.33, 2);
  });

  it('returns 0 for zero reps', () => {
    expect(epley1Rm(100, 0)).toBe(0);
  });
});
