import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Measurement, Profile } from '../../shared/data-access/measurements.service';
import { MeasurementsPage } from './measurements.page';

function latestEnvelope(latest: Record<string, number>) {
  return { success: true, data: { latest } };
}

function profileEnvelope(profile: Profile) {
  return { success: true, data: { profile } };
}

function seriesEnvelope(measurements: Measurement[]) {
  return { success: true, data: { measurements } };
}

function measurementEnvelope() {
  return {
    success: true,
    data: {
      measurement: { id: 'm1', type: 'weight', value: 1, measured_at: '2026-06-12T00:00:00+00:00' },
    },
  };
}

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

  const NO_PROFILE: Profile = {
    sex: null,
    birth_date: null,
    default_rest_seconds: 120,
    height_cm: null,
  };

  async function create(opts: {
    latest?: Record<string, number>;
    profile?: Profile;
  } = {}) {
    const fixture = TestBed.createComponent(MeasurementsPage);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http
      .expectOne('/api/measurements/latest')
      .flush(latestEnvelope(opts.latest ?? { weight: 81.4, waist: 84 }));
    http.expectOne('/api/profile').flush(profileEnvelope(opts.profile ?? NO_PROFILE));
    http.expectOne('/api/measurements?type=weight').flush(seriesEnvelope([]));
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
      measured_at: '2026-06-12T00:00:00.000Z',
    });
    expect(bodies).toContainEqual({
      type: 'waist',
      value: 83,
      measured_at: '2026-06-12T00:00:00.000Z',
    });
    posts.forEach((p) => p.flush(measurementEnvelope()));
    fixture.detectChanges();

    // form clears and data reloads after save
    expect(page.fields()['weight']).toBeUndefined();
    http.expectOne('/api/measurements/latest').flush(latestEnvelope({ weight: 80.6 }));
    http.expectOne('/api/measurements?type=weight').flush(seriesEnvelope([]));
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
        sex: 'male',
        birth_date: null,
        default_rest_seconds: 120,
        height_cm: 180,
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
        sex: 'male',
        birth_date: null,
        default_rest_seconds: 120,
        height_cm: 180,
      },
    });
    page.date.set('2026-06-12');
    page.logEstimate();

    const req = http.expectOne('/api/measurements');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      type: 'bodyfat',
      value: page.estimatedBodyFat(),
      measured_at: '2026-06-12T00:00:00.000Z',
    });
    req.flush(measurementEnvelope());
  });
});
