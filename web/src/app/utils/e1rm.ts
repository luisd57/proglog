export interface ExerciseBest {
  bestWeightKg: number | null;
  bestE1rm: number | null;
}

// Epley formula: 1RM ≈ weight × (1 + reps/30)
export function epley1Rm(weightKg: number, reps: number): number {
  if (reps <= 0) return 0;
  return weightKg * (1 + reps / 30);
}

export function isPr(weightKg: number, reps: number, best: ExerciseBest): boolean {
  if (reps <= 0) return false;
  if (best.bestWeightKg === null || best.bestE1rm === null) return false;
  return weightKg > best.bestWeightKg || epley1Rm(weightKg, reps) > best.bestE1rm;
}
