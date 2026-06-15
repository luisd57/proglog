export interface Delta {
  abs: number;
  pct: number | null; // null when there is no comparable baseline (previous is 0/absent)
}

export function delta(current: number, previous: number | null | undefined): Delta {
  const prev = previous ?? 0;
  const abs = current - prev;
  return { abs, pct: prev > 0 ? (abs / prev) * 100 : null };
}

// Seconds → "H:MM" (e.g. 5430 → "1:30"). Hours are not zero-padded.
export function formatDuration(seconds: number): string {
  const total = Math.max(0, Math.round(seconds));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  return `${hours}:${String(minutes).padStart(2, '0')}`;
}
