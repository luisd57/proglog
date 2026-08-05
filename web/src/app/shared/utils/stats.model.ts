export interface SeriesPoint {
  session_id: string;
  date: string;
  top_set: { weight_kg: number; reps: number };
  volume: number;
  e1rm: number;
}

export interface PrEvent {
  date: string;
  weight_kg: number;
  reps: number;
  e1rm: number;
}

export interface ExerciseSeries {
  points: SeriesPoint[];
  prs: PrEvent[];
}

export interface OverviewTotals {
  workouts: number;
  volume_kg: number;
  reps: number;
  sets: number;
  heaviest_kg: number;
  time_seconds: number;
}

export interface OverviewResult {
  period: string;
  current: OverviewTotals;
  previous: OverviewTotals | null;
  cumulative_volume: { date: string; value: number }[];
}

export interface WeeklyMuscles {
  primary: string[];
  secondary: string[];
  session_count: number;
}

export interface StrengthLevelEntry {
  lift: string;
  label: string;
  exercise_id: string | null;
  e1rm: number | null;
  level: string | null;
  next_level: string | null;
  progress: number | null;
  thresholds: number[];
}

export interface StrengthLevelsResult {
  ready: boolean;
  reason?: 'no-profile' | 'no-bodyweight';
  bodyweight_kg?: number;
  levels: StrengthLevelEntry[];
}
