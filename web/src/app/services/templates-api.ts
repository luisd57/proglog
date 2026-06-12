import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Exercise } from '../models/exercise';

export interface TemplateSummary {
  id: string;
  name: string;
  sortOrder: number;
  exerciseCount: number;
}

export interface TemplateExercise {
  id: string;
  sortOrder: number;
  targetSets: number | null;
  targetReps: number | null;
  restSeconds: number | null;
  exercise: Exercise;
}

export interface Template {
  id: string;
  name: string;
  sortOrder: number;
  exercises: TemplateExercise[];
}

export interface TemplateExerciseInput {
  exerciseId: string;
  targetSets?: number;
  targetReps?: number;
  restSeconds?: number;
}

export interface TemplateInput {
  name: string;
  exercises: TemplateExerciseInput[];
}

@Injectable({ providedIn: 'root' })
export class TemplatesApi {
  private readonly http = inject(HttpClient);

  list(): Observable<TemplateSummary[]> {
    return this.http.get<TemplateSummary[]>('/api/templates');
  }

  get(id: string): Observable<Template> {
    return this.http.get<Template>(`/api/templates/${id}`);
  }

  muscles(id: string): Observable<{ primary: string[]; secondary: string[] }> {
    return this.http.get<{ primary: string[]; secondary: string[] }>(
      `/api/templates/${id}/muscles`,
    );
  }

  create(input: TemplateInput): Observable<Template> {
    return this.http.post<Template>('/api/templates', input);
  }

  update(id: string, input: TemplateInput): Observable<Template> {
    return this.http.put<Template>(`/api/templates/${id}`, input);
  }

  remove(id: string): Observable<void> {
    return this.http.delete<void>(`/api/templates/${id}`);
  }
}
