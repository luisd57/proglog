import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';
import { Exercise } from '../utils/exercise.model';

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

@Injectable({ providedIn: 'root' })
export class TemplatesService {
  private readonly http = inject(HttpClient);

  list(): Observable<TemplateSummary[]> {
    return this.http
      .get<ApiResponse<{ templates: TemplateSummary[] }>>('/api/templates')
      .pipe(map((response) => this.unwrap(response).templates));
  }

  get(id: string): Observable<Template> {
    return this.http
      .get<ApiResponse<{ template: Template }>>(`/api/templates/${id}`)
      .pipe(map((response) => this.unwrap(response).template));
  }

  muscles(id: string): Observable<{ primary: string[]; secondary: string[] }> {
    return this.http
      .get<ApiResponse<{ primary: string[]; secondary: string[] }>>(
        `/api/templates/${id}/muscles`,
      )
      .pipe(map((response) => this.unwrap(response)));
  }

  create(input: TemplateInput): Observable<Template> {
    return this.http
      .post<ApiResponse<{ template: Template }>>('/api/templates', input)
      .pipe(map((response) => this.unwrap(response).template));
  }

  update(id: string, input: TemplateInput): Observable<Template> {
    return this.http
      .put<ApiResponse<{ template: Template }>>(`/api/templates/${id}`, input)
      .pipe(map((response) => this.unwrap(response).template));
  }

  remove(id: string): Observable<void> {
    // 204 No Content — no envelope
    return this.http.delete<void>(`/api/templates/${id}`);
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data as T;
  }
}
