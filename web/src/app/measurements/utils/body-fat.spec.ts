import { estimateNavyBodyFat } from './body-fat';

describe('estimateNavyBodyFat', () => {
  it('estimates male body fat from neck, waist and height', () => {
    // height 180, neck 40, waist 85 → ~14.5%
    const result = estimateNavyBodyFat({
      sex: 'male',
      heightCm: 180,
      neckCm: 40,
      waistCm: 85,
      hipsCm: null,
    });
    expect(result).toBeCloseTo(14.5, 1);
  });

  it('estimates female body fat using hips', () => {
    // height 165, neck 32, waist 70, hips 95 → ~24.9%
    const result = estimateNavyBodyFat({
      sex: 'female',
      heightCm: 165,
      neckCm: 32,
      waistCm: 70,
      hipsCm: 95,
    });
    expect(result).toBeCloseTo(24.9, 1);
  });

  it('rounds to one decimal', () => {
    const result = estimateNavyBodyFat({
      sex: 'male',
      heightCm: 180,
      neckCm: 40,
      waistCm: 85,
      hipsCm: null,
    });
    expect(result).toBe(Math.round((result as number) * 10) / 10);
  });

  it('returns null without sex', () => {
    expect(
      estimateNavyBodyFat({ sex: null, heightCm: 180, neckCm: 40, waistCm: 85, hipsCm: null }),
    ).toBeNull();
  });

  it('returns null without height', () => {
    expect(
      estimateNavyBodyFat({ sex: 'male', heightCm: null, neckCm: 40, waistCm: 85, hipsCm: null }),
    ).toBeNull();
  });

  it('returns null when neck or waist is missing', () => {
    expect(
      estimateNavyBodyFat({ sex: 'male', heightCm: 180, neckCm: null, waistCm: 85, hipsCm: null }),
    ).toBeNull();
    expect(
      estimateNavyBodyFat({ sex: 'male', heightCm: 180, neckCm: 40, waistCm: null, hipsCm: null }),
    ).toBeNull();
  });

  it('returns null for women without hips', () => {
    expect(
      estimateNavyBodyFat({ sex: 'female', heightCm: 165, neckCm: 32, waistCm: 70, hipsCm: null }),
    ).toBeNull();
  });

  it('returns null when the girth difference is non-positive (log domain)', () => {
    // waist <= neck makes log10(waist - neck) undefined
    expect(
      estimateNavyBodyFat({ sex: 'male', heightCm: 180, neckCm: 90, waistCm: 85, hipsCm: null }),
    ).toBeNull();
  });
});
