import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ApiResponse } from '../utils/api-response.model';

export interface Measurement {
  id: string;
  measured_at: string;
  type: string;
  value: number;
}

export interface Profile {
  sex: 'male' | 'female' | null;
  birth_date: string | null;
  default_rest_seconds: number;
  height_cm: number | null;
}

export const MEASUREMENT_LABELS: Record<string, string> = {
  weight: 'Body weight (kg)',
  bodyfat: 'Body fat (%)',
  neck: 'Neck (cm)',
  shoulders: 'Shoulders (cm)',
  chest: 'Chest (cm)',
  waist: 'Waist (cm)',
  hips: 'Hips (cm)',
  bicepL: 'Left bicep (cm)',
  bicepR: 'Right bicep (cm)',
  forearmL: 'Left forearm (cm)',
  forearmR: 'Right forearm (cm)',
  thighL: 'Left thigh (cm)',
  thighR: 'Right thigh (cm)',
  calfL: 'Left calf (cm)',
  calfR: 'Right calf (cm)',
};

@Injectable({ providedIn: 'root' })
export class MeasurementsService {
  private readonly http = inject(HttpClient);

  // `type` is required by the API (422 when absent)
  series(type: string): Observable<Measurement[]> {
    const params = new HttpParams().set('type', type);
    return this.http
      .get<ApiResponse<{ measurements: Measurement[] }>>('/api/measurements', { params })
      .pipe(map((response) => this.unwrap(response).measurements));
  }

  latestAll(): Observable<Record<string, number>> {
    return this.http
      .get<ApiResponse<{ latest: Record<string, number> }>>('/api/measurements/latest')
      .pipe(map((response) => this.unwrap(response).latest));
  }

  add(input: { type: string; value: number; measured_at?: string }): Observable<Measurement> {
    return this.http
      .post<ApiResponse<{ measurement: Measurement }>>('/api/measurements', input)
      .pipe(map((response) => this.unwrap(response).measurement));
  }

  remove(id: string): Observable<void> {
    // 204 No Content — no envelope
    return this.http.delete<void>(`/api/measurements/${id}`);
  }

  profile(): Observable<Profile> {
    return this.http
      .get<ApiResponse<{ profile: Profile }>>('/api/profile')
      .pipe(map((response) => this.unwrap(response).profile));
  }

  updateProfile(input: Partial<Profile>): Observable<Profile> {
    return this.http
      .patch<ApiResponse<{ profile: Profile }>>('/api/profile', input)
      .pipe(map((response) => this.unwrap(response).profile));
  }

  private unwrap<T>(response: ApiResponse<T>): T {
    if (!response.success) {
      throw new Error(response.error?.message ?? 'Request failed');
    }
    return response.data as T;
  }
}
