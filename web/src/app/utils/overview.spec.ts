import { describe, expect, it } from 'vitest';
import { delta, formatDuration } from './overview';

describe('delta', () => {
  it('computes absolute and percentage change', () => {
    expect(delta(120, 100)).toEqual({ abs: 20, pct: 20 });
    expect(delta(80, 100)).toEqual({ abs: -20, pct: -20 });
  });

  it('has no percentage when the baseline is zero or missing', () => {
    expect(delta(50, 0)).toEqual({ abs: 50, pct: null });
    expect(delta(50, null)).toEqual({ abs: 50, pct: null });
    expect(delta(50, undefined)).toEqual({ abs: 50, pct: null });
  });
});

describe('formatDuration', () => {
  it('formats seconds as H:MM', () => {
    expect(formatDuration(0)).toBe('0:00');
    expect(formatDuration(90 * 60)).toBe('1:30');
    expect(formatDuration(45 * 60)).toBe('0:45');
    expect(formatDuration(2 * 3600 + 5 * 60)).toBe('2:05');
  });

  it('clamps negatives to zero', () => {
    expect(formatDuration(-100)).toBe('0:00');
  });
});
