import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ExerciseBest } from '../utils/e1rm';

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
}
