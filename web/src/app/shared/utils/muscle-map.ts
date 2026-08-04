import { Muscle } from './body-data';

export type HighlightLevel = 'primary' | 'secondary';
export type RegionHighlights = Partial<Record<Muscle, HighlightLevel>>;

// free-exercise-db muscle names → body diagram regions
const MUSCLE_TO_REGIONS: Record<string, Muscle[]> = {
  abdominals: ['abs', 'obliques'],
  abductors: ['abductors'],
  adductors: ['adductor'],
  biceps: ['biceps'],
  calves: ['calves', 'left-soleus', 'right-soleus'],
  chest: ['chest'],
  forearms: ['forearm'],
  glutes: ['gluteal'],
  hamstrings: ['hamstring'],
  lats: ['upper-back'],
  'lower back': ['lower-back'],
  'middle back': ['upper-back'],
  neck: ['neck'],
  quadriceps: ['quadriceps'],
  shoulders: ['front-deltoids', 'back-deltoids'],
  traps: ['trapezius'],
  triceps: ['triceps'],
};

export function muscleHighlights(
  primaryMuscles: string[],
  secondaryMuscles: string[],
): RegionHighlights {
  const highlights: RegionHighlights = {};
  for (const name of secondaryMuscles) {
    for (const region of MUSCLE_TO_REGIONS[name] ?? []) {
      highlights[region] = 'secondary';
    }
  }
  for (const name of primaryMuscles) {
    for (const region of MUSCLE_TO_REGIONS[name] ?? []) {
      highlights[region] = 'primary';
    }
  }
  return highlights;
}
