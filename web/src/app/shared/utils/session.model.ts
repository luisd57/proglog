import { Exercise } from './exercise.model';

export interface SetLog {
  id: string;
  set_number: number;
  weight_kg: number;
  reps: number;
  is_warmup: boolean;
  notes: string | null;
}

export interface SetInput {
  weight_kg: number;
  reps: number;
  is_warmup?: boolean;
  notes?: string;
}

export interface SessionExercise {
  id: string;
  sort_order: number;
  notes: string | null;
  exercise: Exercise;
  sets: SetLog[];
  target_sets: number | null;
  target_reps: number | null;
  rest_seconds: number;
  previous_sets: SetLog[];
}

export interface Session {
  id: string;
  template_id: string | null;
  template_name: string | null;
  started_at: string;
  finished_at: string | null;
  notes: string | null;
  exercises: SessionExercise[];
}

export interface SessionSummary {
  id: string;
  template_name: string | null;
  started_at: string;
  finished_at: string | null;
  exercise_count: number;
  set_count: number;
}
