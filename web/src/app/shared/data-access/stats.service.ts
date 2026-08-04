import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';
import { ExerciseBest } from '../utils/e1rm';

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

@Injectable({ providedIn: 'root' })
export class StatsService {
  private readonly http = inject(HttpClient);

  exerciseBest(
    exerciseId: string,
    excludeSession?: string,
  ): Observable<ExerciseBest> {
    let params = new HttpParams();
    if (excludeSession) params = params.set('exclude_session', excludeSession);
    return this.http
      .get<ApiResponse<ExerciseBest>>(`/api/stats/exercise/${exerciseId}/best`, {
        params,
      })
      .pipe(map((response) => this.unwrap(response)));
  }

  exerciseSeries(exerciseId: string): Observable<ExerciseSeries> {
    return this.http
      .get<ApiResponse<ExerciseSeries>>(`/api/stats/exercise/${exerciseId}/series`)
      .pipe(map((response) => this.unwrap(response)));
  }

  strengthLevels(): Observable<StrengthLevelsResult> {
    return this.http
      .get<ApiResponse<StrengthLevelsResult>>('/api/stats/strength-levels')
      .pipe(map((response) => this.unwrap(response)));
  }

  weeklyMuscles(): Observable<WeeklyMuscles> {
    return this.http
      .get<ApiResponse<WeeklyMuscles>>('/api/stats/weekly-muscles')
      .pipe(map((response) => this.unwrap(response)));
  }

  overview(period: string): Observable<OverviewResult> {
    const params = new HttpParams().set('period', period);
    return this.http
      .get<ApiResponse<OverviewResult>>('/api/stats/overview', { params })
      .pipe(map((response) => this.unwrap(response)));
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data as T;
  }
}
