// Strength standards (1RM in kg by bodyweight, sex) for the main barbell
// lifts. Levels: beginner / novice / intermediate / advanced / elite.
// Values are approximations of widely circulated community standards
// (strengthlevel.com-style) — good enough for personal tracking.

export const LEVELS = [
  'beginner',
  'novice',
  'intermediate',
  'advanced',
  'elite',
] as const;

export type Level = (typeof LEVELS)[number] | 'untrained';

export interface StandardRow {
  bodyweightKg: number;
  // thresholds for [beginner, novice, intermediate, advanced, elite]
  thresholds: [number, number, number, number, number];
}

export interface LiftStandard {
  lift: 'squat' | 'bench' | 'deadlift' | 'ohp' | 'row';
  label: string;
  // seeded free-exercise-db names this standard applies to (first match wins)
  exerciseNames: string[];
  male: StandardRow[];
  female: StandardRow[];
}

export interface LevelResult {
  level: Level;
  nextLevel: Level | null;
  progress: number; // 0..1 toward the next level
  thresholds: number[];
}

export function levelFor(
  rows: StandardRow[],
  bodyweightKg: number,
  e1rm: number,
): LevelResult {
  const thresholds = interpolateRow(rows, bodyweightKg);

  if (e1rm >= thresholds[4]) {
    return { level: 'elite', nextLevel: null, progress: 1, thresholds };
  }

  // walk levels from elite down to find the highest reached
  let levelIndex = -1; // -1 = untrained
  for (let i = thresholds.length - 1; i >= 0; i--) {
    if (e1rm >= thresholds[i]) {
      levelIndex = i;
      break;
    }
  }

  const lower = levelIndex === -1 ? 0 : thresholds[levelIndex];
  const upper = thresholds[levelIndex + 1];
  const progress = Math.max(0, Math.min(1, (e1rm - lower) / (upper - lower)));

  return {
    level: levelIndex === -1 ? 'untrained' : LEVELS[levelIndex],
    nextLevel: LEVELS[levelIndex + 1],
    progress,
    thresholds,
  };
}

function interpolateRow(rows: StandardRow[], bodyweightKg: number): number[] {
  const sorted = [...rows].sort((a, b) => a.bodyweightKg - b.bodyweightKg);
  if (bodyweightKg <= sorted[0].bodyweightKg) return [...sorted[0].thresholds];
  const last = sorted[sorted.length - 1];
  if (bodyweightKg >= last.bodyweightKg) return [...last.thresholds];

  for (let i = 0; i < sorted.length - 1; i++) {
    const a = sorted[i];
    const b = sorted[i + 1];
    if (bodyweightKg >= a.bodyweightKg && bodyweightKg <= b.bodyweightKg) {
      const t = (bodyweightKg - a.bodyweightKg) / (b.bodyweightKg - a.bodyweightKg);
      return a.thresholds.map(
        (lo, j) => Math.round((lo + t * (b.thresholds[j] - lo)) * 10) / 10,
      );
    }
  }
  return [...last.thresholds];
}

const row = (
  bodyweightKg: number,
  ...thresholds: [number, number, number, number, number]
): StandardRow => ({ bodyweightKg, thresholds });

export const STRENGTH_STANDARDS: LiftStandard[] = [
  {
    lift: 'squat',
    label: 'Squat',
    exerciseNames: ['Barbell Squat', 'Barbell Full Squat'],
    male: [
      row(60, 46, 64, 87, 113, 142),
      row(70, 56, 77, 102, 130, 161),
      row(80, 66, 89, 115, 145, 177),
      row(90, 75, 99, 127, 158, 192),
      row(100, 83, 109, 138, 170, 205),
      row(110, 91, 117, 148, 181, 217),
      row(120, 98, 125, 157, 191, 227),
      row(140, 111, 140, 173, 208, 246),
    ],
    female: [
      row(50, 26, 41, 59, 80, 104),
      row(60, 31, 47, 66, 89, 113),
      row(70, 36, 52, 73, 96, 121),
      row(80, 40, 57, 78, 102, 128),
      row(90, 44, 61, 83, 108, 134),
      row(100, 47, 65, 87, 112, 139),
    ],
  },
  {
    lift: 'bench',
    label: 'Bench Press',
    exerciseNames: ['Barbell Bench Press - Medium Grip'],
    male: [
      row(60, 32, 48, 68, 91, 117),
      row(70, 41, 58, 80, 105, 133),
      row(80, 49, 68, 91, 118, 147),
      row(90, 57, 77, 102, 130, 160),
      row(100, 64, 85, 111, 140, 171),
      row(110, 71, 92, 119, 150, 182),
      row(120, 77, 99, 127, 158, 191),
      row(140, 88, 111, 140, 173, 207),
    ],
    female: [
      row(50, 13, 22, 33, 48, 64),
      row(60, 17, 26, 39, 54, 71),
      row(70, 20, 30, 43, 59, 77),
      row(80, 23, 33, 47, 64, 82),
      row(90, 25, 36, 50, 68, 86),
      row(100, 27, 38, 53, 71, 90),
    ],
  },
  {
    lift: 'deadlift',
    label: 'Deadlift',
    exerciseNames: ['Barbell Deadlift'],
    male: [
      row(60, 57, 78, 103, 132, 163),
      row(70, 68, 91, 119, 149, 182),
      row(80, 79, 103, 132, 164, 199),
      row(90, 88, 114, 145, 178, 214),
      row(100, 97, 124, 156, 190, 227),
      row(110, 105, 133, 166, 201, 239),
      row(120, 113, 141, 175, 211, 250),
      row(140, 126, 156, 191, 229, 269),
    ],
    female: [
      row(50, 33, 49, 69, 92, 117),
      row(60, 39, 56, 77, 101, 127),
      row(70, 44, 62, 84, 109, 136),
      row(80, 49, 67, 90, 116, 143),
      row(90, 53, 72, 95, 121, 149),
      row(100, 57, 76, 100, 126, 155),
    ],
  },
  {
    lift: 'ohp',
    label: 'Overhead Press',
    exerciseNames: ['Standing Military Press', 'Barbell Shoulder Press'],
    male: [
      row(60, 20, 31, 44, 60, 77),
      row(70, 25, 36, 51, 67, 86),
      row(80, 29, 41, 56, 74, 93),
      row(90, 33, 45, 61, 80, 100),
      row(100, 37, 49, 66, 85, 105),
      row(110, 40, 53, 70, 89, 110),
      row(120, 43, 56, 73, 93, 114),
      row(140, 48, 61, 79, 100, 122),
    ],
    female: [
      row(50, 9, 15, 23, 33, 44),
      row(60, 11, 17, 26, 36, 48),
      row(70, 13, 19, 28, 39, 51),
      row(80, 14, 21, 30, 41, 54),
      row(90, 16, 23, 32, 44, 56),
      row(100, 17, 24, 34, 45, 58),
    ],
  },
  {
    lift: 'row',
    label: 'Barbell Row',
    exerciseNames: ['Bent Over Barbell Row'],
    male: [
      row(60, 28, 43, 61, 82, 105),
      row(70, 34, 50, 69, 91, 115),
      row(80, 39, 56, 76, 99, 124),
      row(90, 44, 61, 82, 106, 131),
      row(100, 49, 66, 88, 112, 138),
      row(110, 53, 70, 92, 117, 144),
      row(120, 56, 74, 97, 122, 149),
      row(140, 63, 81, 104, 130, 158),
    ],
    female: [
      row(50, 15, 24, 35, 48, 62),
      row(60, 17, 27, 38, 52, 67),
      row(70, 20, 30, 42, 56, 71),
      row(80, 22, 32, 44, 59, 74),
      row(90, 24, 34, 47, 61, 77),
      row(100, 25, 36, 49, 64, 80),
    ],
  },
];
