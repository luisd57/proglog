import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export interface Measurement {
  id: string;
  measuredAt: string;
  type: string;
  value: number;
}

export interface Profile {
  id: number;
  sex: 'male' | 'female' | null;
  birthDate: string | null;
  defaultRestSeconds: number;
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
export class MeasurementsApi {
  private readonly http = inject(HttpClient);

  series(type: string): Observable<Measurement[]> {
    const params = new HttpParams().set('type', type);
    return this.http.get<Measurement[]>('/api/measurements', { params });
  }

  add(input: { type: string; value: number; measuredAt?: string }): Observable<Measurement> {
    return this.http.post<Measurement>('/api/measurements', input);
  }

  remove(id: string): Observable<void> {
    return this.http.delete<void>(`/api/measurements/${id}`);
  }

  profile(): Observable<Profile> {
    return this.http.get<Profile>('/api/profile');
  }

  updateProfile(input: Partial<Profile>): Observable<Profile> {
    return this.http.patch<Profile>('/api/profile', input);
  }
}
