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
