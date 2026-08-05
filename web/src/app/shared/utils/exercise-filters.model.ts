export interface ExerciseFilters {
  search?: string;
  muscle?: string;
  equipment?: string;
}

export interface ExerciseInput {
  name: string;
  primary_muscles: string[];
  secondary_muscles?: string[];
  equipment?: string;
  category?: string;
  instructions?: string;
}
