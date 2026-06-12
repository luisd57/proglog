import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Exercise } from '../models/exercise';

export interface ExerciseFilters {
  search?: string;
  muscle?: string;
  equipment?: string;
}

export interface ExerciseInput {
  name: string;
  primaryMuscles: string[];
  secondaryMuscles?: string[];
  equipment?: string;
  category?: string;
  instructions?: string;
}

@Injectable({ providedIn: 'root' })
export class ExercisesApi {
  private readonly http = inject(HttpClient);

  list(filters: ExerciseFilters = {}): Observable<Exercise[]> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.muscle) params = params.set('muscle', filters.muscle);
    if (filters.equipment) params = params.set('equipment', filters.equipment);
    return this.http.get<Exercise[]>('/api/exercises', { params });
  }

  get(id: string): Observable<Exercise> {
    return this.http.get<Exercise>(`/api/exercises/${id}`);
  }

  create(input: ExerciseInput): Observable<Exercise> {
    return this.http.post<Exercise>('/api/exercises', input);
  }

  update(id: string, input: Partial<ExerciseInput>): Observable<Exercise> {
    return this.http.patch<Exercise>(`/api/exercises/${id}`, input);
  }

  remove(id: string): Observable<void> {
    return this.http.delete<void>(`/api/exercises/${id}`);
  }
}
