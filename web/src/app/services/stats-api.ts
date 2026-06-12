import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ExerciseBest } from '../utils/e1rm';

export interface SeriesPoint {
  sessionId: string;
  date: string;
  topSet: { weightKg: number; reps: number };
  volume: number;
  e1rm: number;
}

export interface PrEvent {
  date: string;
  weightKg: number;
  reps: number;
  e1rm: number;
}

export interface ExerciseSeries {
  points: SeriesPoint[];
  prs: PrEvent[];
}

@Injectable({ providedIn: 'root' })
export class StatsApi {
  private readonly http = inject(HttpClient);

  exerciseBest(
    exerciseId: string,
    excludeSession?: string,
  ): Observable<ExerciseBest> {
    let params = new HttpParams();
    if (excludeSession) params = params.set('excludeSession', excludeSession);
    return this.http.get<ExerciseBest>(`/api/stats/exercise/${exerciseId}/best`, {
      params,
    });
  }

  exerciseSeries(exerciseId: string): Observable<ExerciseSeries> {
    return this.http.get<ExerciseSeries>(
      `/api/stats/exercise/${exerciseId}/series`,
    );
  }

  strengthLevels(): Observable<StrengthLevelsResult> {
    return this.http.get<StrengthLevelsResult>('/api/stats/strength-levels');
  }

  weeklyMuscles(): Observable<WeeklyMuscles> {
    return this.http.get<WeeklyMuscles>('/api/stats/weekly-muscles');
  }
}

export interface WeeklyMuscles {
  primary: string[];
  secondary: string[];
  sessionCount: number;
}

export interface StrengthLevelEntry {
  lift: string;
  label: string;
  exerciseId: string | null;
  e1rm: number | null;
  level: string | null;
  nextLevel: string | null;
  progress: number | null;
  thresholds: number[];
}

export interface StrengthLevelsResult {
  ready: boolean;
  reason?: 'no-profile' | 'no-bodyweight';
  bodyweightKg?: number;
  levels: StrengthLevelEntry[];
}
