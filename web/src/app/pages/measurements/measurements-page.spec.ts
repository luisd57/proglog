import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { MeasurementsPage } from './measurements-page';

describe('MeasurementsPage', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
  });

  interface ProfileShape {
    id: number;
    sex: 'male' | 'female' | null;
    birthDate: string | null;
    defaultRestSeconds: number;
    heightCm: number | null;
  }

  const NO_PROFILE: ProfileShape = {
    id: 1,
    sex: null,
    birthDate: null,
    defaultRestSeconds: 120,
    heightCm: null,
  };

  async function create(opts: {
    latest?: Record<string, number>;
    profile?: ProfileShape;
  } = {}) {
    const fixture = TestBed.createComponent(MeasurementsPage);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http
      .expectOne('/api/measurements/latest')
      .flush(opts.latest ?? { weight: 81.4, waist: 84 });
    http.expectOne('/api/profile').flush(opts.profile ?? NO_PROFILE);
    http.expectOne('/api/measurements?type=weight').flush([]);
    await fixture.whenStable();
    fixture.detectChanges();
    return { fixture, page: fixture.componentInstance, http };
  }

  it('renders one input per measurement type with the latest value as placeholder', async () => {
    const { fixture } = await create();
    const el = fixture.nativeElement as HTMLElement;
    const inputs = el.querySelectorAll('input[data-type]');
    expect(inputs.length).toBe(15);

    const weight = el.querySelector('input[data-type="weight"]') as HTMLInputElement;
    expect(weight.placeholder).toBe('81.4');
    const neck = el.querySelector('input[data-type="neck"]') as HTMLInputElement;
    expect(neck.placeholder).toBe('—');
  });

  it('saves one POST per filled field with the shared date', async () => {
    const { fixture, page, http } = await create();

    page.setField('weight', '80.6');
    page.setField('waist', '83');
    page.date.set('2026-06-12');
    page.save(new Event('submit'));

    const posts = http.match('/api/measurements');
    expect(posts.length).toBe(2);
    const bodies = posts.map((p) => p.request.body);
    expect(bodies).toContainEqual({
      type: 'weight',
      value: 80.6,
      measuredAt: '2026-06-12',
    });
    expect(bodies).toContainEqual({
      type: 'waist',
      value: 83,
      measuredAt: '2026-06-12',
    });
    posts.forEach((p) => p.flush({}));
    fixture.detectChanges();

    // form clears and data reloads after save
    expect(page.fields()['weight']).toBeUndefined();
    http.expectOne('/api/measurements/latest').flush({ weight: 80.6 });
    http.expectOne('/api/measurements?type=weight').flush([]);
  });

  it('does nothing when no field is filled', async () => {
    const { page, http } = await create();
    page.save(new Event('submit'));
    expect(http.match('/api/measurements').length).toBe(0);
  });

  it('estimates body fat from profile + latest girths and reacts to typed values', async () => {
    const { page } = await create({
      latest: { neck: 40, waist: 85 },
      profile: {
        id: 1,
        sex: 'male',
        birthDate: null,
        defaultRestSeconds: 120,
        heightCm: 180,
      },
    });

    expect(page.estimatedBodyFat()).toBeCloseTo(14.5, 1);

    // a freshly typed waist overrides the latest saved value
    page.setField('waist', '95');
    expect(page.estimatedBodyFat()!).toBeGreaterThan(14.5);
  });

  it('returns no estimate when height is missing', async () => {
    const { page } = await create({ latest: { neck: 40, waist: 85 } });
    expect(page.estimatedBodyFat()).toBeNull();
  });

  it('logs the estimate as a bodyfat measurement with the shared date', async () => {
    const { page, http } = await create({
      latest: { neck: 40, waist: 85 },
      profile: {
        id: 1,
        sex: 'male',
        birthDate: null,
        defaultRestSeconds: 120,
        heightCm: 180,
      },
    });
    page.date.set('2026-06-12');
    page.logEstimate();

    const req = http.expectOne('/api/measurements');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      type: 'bodyfat',
      value: page.estimatedBodyFat(),
      measuredAt: '2026-06-12',
    });
    req.flush({});
  });
});
