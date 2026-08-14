import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';
import { Exercise } from '../utils/exercise.model';
import { ExerciseFilters, ExerciseInput } from '../utils/exercise-filters.model';

@Injectable({ providedIn: 'root' })
export class ExercisesService {
  private readonly http = inject(HttpClient);

  list(filters: ExerciseFilters = {}): Observable<Exercise[]> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.muscle) params = params.set('muscle', filters.muscle);
    if (filters.equipment) params = params.set('equipment', filters.equipment);
    return this.http
      .get<ApiResponse<{ exercises: Exercise[] }>>('/api/exercises', { params })
      .pipe(map((response) => this.unwrap(response).exercises));
  }

  get(id: string): Observable<Exercise> {
    return this.http
      .get<ApiResponse<{ exercise: Exercise }>>(`/api/exercises/${id}`)
      .pipe(map((response) => this.unwrap(response).exercise));
  }

  create(input: ExerciseInput): Observable<Exercise> {
    return this.http
      .post<ApiResponse<{ exercise: Exercise }>>('/api/exercises', input)
      .pipe(map((response) => this.unwrap(response).exercise));
  }

  update(id: string, input: Partial<ExerciseInput>): Observable<Exercise> {
    return this.http
      .patch<ApiResponse<{ exercise: Exercise }>>(`/api/exercises/${id}`, input)
      .pipe(map((response) => this.unwrap(response).exercise));
  }

  remove(id: string): Observable<void> {
    // 204 No Content - no envelope
    return this.http.delete<void>(`/api/exercises/${id}`);
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data as T;
  }
}
