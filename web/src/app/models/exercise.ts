export interface Exercise {
  id: string;
  name: string;
  primaryMuscles: string[];
  secondaryMuscles: string[];
  equipment: string | null;
  category: string | null;
  instructions: string | null;
  isCustom: boolean;
}

export const MUSCLE_NAMES = [
  'abdominals', 'abductors', 'adductors', 'biceps', 'calves', 'chest',
  'forearms', 'glutes', 'hamstrings', 'lats', 'lower back', 'middle back',
  'neck', 'quadriceps', 'shoulders', 'traps', 'triceps',
] as const;

export const EQUIPMENT_NAMES = [
  'barbell', 'dumbbell', 'cable', 'machine', 'body only', 'kettlebells',
  'bands', 'medicine ball', 'exercise ball', 'e-z curl bar', 'foam roll',
  'other',
] as const;
