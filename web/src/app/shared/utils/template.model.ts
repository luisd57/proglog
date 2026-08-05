import { Exercise } from './exercise.model';

export interface TemplateSummary {
  id: string;
  name: string;
  sort_order: number;
  exercise_count: number;
}

export interface TemplateExercise {
  id: string;
  sort_order: number;
  target_sets: number | null;
  target_reps: number | null;
  rest_seconds: number | null;
  exercise: Exercise;
}

export interface Template {
  id: string;
  name: string;
  sort_order: number;
  exercises: TemplateExercise[];
}

export interface TemplateExerciseInput {
  exercise_id: string;
  target_sets?: number;
  target_reps?: number;
  rest_seconds?: number;
}

export interface TemplateInput {
  name: string;
  exercises: TemplateExerciseInput[];
}
