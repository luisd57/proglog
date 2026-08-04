import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';
import { Session, SessionSummary, SetInput } from '../utils/session.model';

@Injectable({ providedIn: 'root' })
export class SessionsService {
  private readonly http = inject(HttpClient);

  start(templateId?: string): Observable<Session> {
    const body = templateId ? { template_id: templateId } : {};
    return this.http
      .post<ApiResponse<{ session: Session }>>('/api/sessions', body)
      .pipe(map((response) => this.unwrap(response).session));
  }

  get(id: string): Observable<Session> {
    return this.http
      .get<ApiResponse<{ session: Session }>>(`/api/sessions/${id}`)
      .pipe(map((response) => this.unwrap(response).session));
  }

  list(): Observable<SessionSummary[]> {
    return this.http
      .get<ApiResponse<{ sessions: SessionSummary[] }>>('/api/sessions')
      .pipe(map((response) => this.unwrap(response).sessions));
  }

  replaceSets(
    sessionId: string,
    sessionExerciseId: string,
    sets: SetInput[],
  ): Observable<void> {
    return this.http
      .put<ApiResponse<null>>(
        `/api/sessions/${sessionId}/exercises/${sessionExerciseId}/sets`,
        { sets },
      )
      .pipe(
        map((response) => {
          this.unwrap(response);
        }),
      );
  }

  addExercise(sessionId: string, exerciseId: string): Observable<Session> {
    return this.http
      .post<ApiResponse<{ session: Session }>>(`/api/sessions/${sessionId}/exercises`, {
        exercise_id: exerciseId,
      })
      .pipe(map((response) => this.unwrap(response).session));
  }

  removeExercise(sessionId: string, sessionExerciseId: string): Observable<Session> {
    return this.http
      .delete<ApiResponse<{ session: Session }>>(
        `/api/sessions/${sessionId}/exercises/${sessionExerciseId}`,
      )
      .pipe(map((response) => this.unwrap(response).session));
  }

  updateExerciseNotes(
    sessionId: string,
    sessionExerciseId: string,
    notes: string,
  ): Observable<void> {
    return this.http
      .patch<ApiResponse<null>>(
        `/api/sessions/${sessionId}/exercises/${sessionExerciseId}`,
        { notes },
      )
      .pipe(
        map((response) => {
          this.unwrap(response);
        }),
      );
  }

  finish(id: string): Observable<Session> {
    return this.http
      .post<ApiResponse<{ session: Session }>>(`/api/sessions/${id}/finish`, {})
      .pipe(map((response) => this.unwrap(response).session));
  }

  remove(id: string): Observable<void> {
    // 204 No Content — no envelope
    return this.http.delete<void>(`/api/sessions/${id}`);
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data as T;
  }
}
