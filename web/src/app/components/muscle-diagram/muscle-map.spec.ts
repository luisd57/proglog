import { muscleHighlights } from './muscle-map';

describe('muscleHighlights', () => {
  it('maps a simple primary muscle to its body region', () => {
    expect(muscleHighlights(['chest'], [])).toEqual({ chest: 'primary' });
  });

  it('maps shoulders to both deltoid regions', () => {
    expect(muscleHighlights(['shoulders'], [])).toEqual({
      'front-deltoids': 'primary',
      'back-deltoids': 'primary',
    });
  });

  it('maps abdominals to abs and obliques', () => {
    expect(muscleHighlights([], ['abdominals'])).toEqual({
      abs: 'secondary',
      obliques: 'secondary',
    });
  });

  it('maps back muscle names to back regions', () => {
    expect(muscleHighlights(['lats', 'lower back'], ['middle back'])).toEqual({
      'upper-back': 'primary',
      'lower-back': 'primary',
    });
  });

  it('lets primary win when a region is both primary and secondary', () => {
    expect(muscleHighlights(['quadriceps'], ['quadriceps'])).toEqual({
      quadriceps: 'primary',
    });
  });

  it('ignores unknown muscle names', () => {
    expect(muscleHighlights(['cardio?'], [])).toEqual({});
  });

  it('maps every free-exercise-db muscle name to at least one region', () => {
    const allNames = [
      'abdominals', 'abductors', 'adductors', 'biceps', 'calves', 'chest',
      'forearms', 'glutes', 'hamstrings', 'lats', 'lower back', 'middle back',
      'neck', 'quadriceps', 'shoulders', 'traps', 'triceps',
    ];
    for (const name of allNames) {
      expect(Object.keys(muscleHighlights([name], [])).length).toBeGreaterThan(0);
    }
  });
});
