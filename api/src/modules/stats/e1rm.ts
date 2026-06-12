// Epley formula: 1RM ≈ weight × (1 + reps/30)
export function epley1Rm(weightKg: number, reps: number): number {
  if (reps <= 0) return 0;
  return weightKg * (1 + reps / 30);
}
