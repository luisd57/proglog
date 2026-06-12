export interface BodyFatInputs {
  sex: 'male' | 'female' | null;
  heightCm: number | null;
  neckCm: number | null;
  waistCm: number | null;
  hipsCm: number | null; // required for women
}

// US Navy body-fat estimate from tape measurements (all in cm).
// Returns a percentage rounded to one decimal, or null when the required
// inputs are missing or out of the formula's valid domain.
export function estimateNavyBodyFat(inputs: BodyFatInputs): number | null {
  const { sex, heightCm, neckCm, waistCm, hipsCm } = inputs;
  if (!sex || !heightCm || !neckCm || !waistCm) return null;
  if (sex === 'female' && !hipsCm) return null;

  let bodyFat: number;
  if (sex === 'male') {
    const girth = waistCm - neckCm;
    if (girth <= 0) return null;
    bodyFat =
      495 /
        (1.0324 -
          0.19077 * Math.log10(girth) +
          0.15456 * Math.log10(heightCm)) -
      450;
  } else {
    const girth = waistCm + (hipsCm as number) - neckCm;
    if (girth <= 0) return null;
    bodyFat =
      495 /
        (1.29579 -
          0.35004 * Math.log10(girth) +
          0.221 * Math.log10(heightCm)) -
      450;
  }

  if (!Number.isFinite(bodyFat) || bodyFat <= 0) return null;
  return Math.round(bodyFat * 10) / 10;
}
