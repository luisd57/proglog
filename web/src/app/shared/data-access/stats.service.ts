import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';
import { ExerciseBest } from '../utils/e1rm';
import { ExerciseSeries, OverviewResult, StrengthLevelsResult, WeeklyMuscles } from '../utils/stats.model';

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
