import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Profile } from '../../shared/utils/measurement.model';
import { SettingsPage } from './settings.page';

function profileEnvelope(profile: Profile) {
  return { success: true, data: { profile } };
}

describe('SettingsPage', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
  });

  async function create(
    profile: Profile = {
      sex: null,
      birth_date: null,
      default_rest_seconds: 120,
      height_cm: null,
    },
  ) {
    const fixture = TestBed.createComponent(SettingsPage);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/profile').flush(profileEnvelope(profile));
    await fixture.whenStable();
    fixture.detectChanges();
    return { fixture, page: fixture.componentInstance, http };
  }

  it('populates the form from the profile', async () => {
    const { page } = await create({
      sex: 'male',
      birth_date: '1995-04-03',
      default_rest_seconds: 90,
      height_cm: null,
    });
    expect(page.sex()).toBe('male');
    expect(page.birthDate()).toBe('1995-04-03');
    expect(page.restSeconds()).toBe('90');
  });

  it('commits a picked date on change and saves it', async () => {
    const { fixture, page, http } = await create();

    const dateInput = (fixture.nativeElement as HTMLElement).querySelector(
      'input[type="date"]',
    ) as HTMLInputElement;
    dateInput.value = '1995-04-03';
    dateInput.dispatchEvent(new Event('change'));
    fixture.detectChanges();

    expect(page.birthDate()).toBe('1995-04-03');

    page.save(new Event('submit'));
    const req = http.expectOne('/api/profile');
    expect(req.request.method).toBe('PATCH');
    expect(req.request.body.birth_date).toBe('1995-04-03');
    req.flush(
      profileEnvelope({
        sex: null,
        birth_date: '1995-04-03',
        default_rest_seconds: 120,
        height_cm: null,
      }),
    );
  });
});
