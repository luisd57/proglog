import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Exercise } from '../models/exercise';

export interface SetLog {
  id: string;
  setNumber: number;
  weightKg: number;
  reps: number;
  isWarmup: boolean;
  notes: string | null;
}

export interface SetInput {
  weightKg: number;
  reps: number;
  isWarmup?: boolean;
  notes?: string;
}

export interface SessionExercise {
  id: string;
  sortOrder: number;
  notes: string | null;
  exercise: Exercise;
  sets: SetLog[];
  targetSets: number | null;
  targetReps: number | null;
  restSeconds: number;
  previousSets: SetLog[];
}

export interface Session {
  id: string;
  templateId: string | null;
  templateName: string | null;
  startedAt: string;
  finishedAt: string | null;
  notes: string | null;
  exercises: SessionExercise[];
}

export interface SessionSummary {
  id: string;
  templateName: string | null;
  startedAt: string;
  finishedAt: string | null;
  exerciseCount: number;
  setCount: number;
}

@Injectable({ providedIn: 'root' })
export class SessionsApi {
  private readonly http = inject(HttpClient);

  start(templateId?: string): Observable<Session> {
    return this.http.post<Session>('/api/sessions', { templateId });
  }

  get(id: string): Observable<Session> {
    return this.http.get<Session>(`/api/sessions/${id}`);
  }

  list(): Observable<SessionSummary[]> {
    return this.http.get<SessionSummary[]>('/api/sessions');
  }

  replaceSets(
    sessionId: string,
    sessionExerciseId: string,
    sets: SetInput[],
  ): Observable<unknown> {
    return this.http.put(
      `/api/sessions/${sessionId}/exercises/${sessionExerciseId}/sets`,
      sets,
    );
  }

  addExercise(sessionId: string, exerciseId: string): Observable<Session> {
    return this.http.post<Session>(`/api/sessions/${sessionId}/exercises`, {
      exerciseId,
    });
  }

  removeExercise(sessionId: string, sessionExerciseId: string): Observable<Session> {
    return this.http.delete<Session>(
      `/api/sessions/${sessionId}/exercises/${sessionExerciseId}`,
    );
  }

  updateExerciseNotes(
    sessionId: string,
    sessionExerciseId: string,
    notes: string,
  ): Observable<unknown> {
    return this.http.patch(
      `/api/sessions/${sessionId}/exercises/${sessionExerciseId}`,
      { notes },
    );
  }

  finish(id: string): Observable<Session> {
    return this.http.post<Session>(`/api/sessions/${id}/finish`, {});
  }

  remove(id: string): Observable<void> {
    return this.http.delete<void>(`/api/sessions/${id}`);
  }
}
