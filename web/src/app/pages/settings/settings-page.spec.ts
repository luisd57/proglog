import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { SettingsPage } from './settings-page';

describe('SettingsPage', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
  });

  async function create(
    profile: {
      id: number;
      sex: string | null;
      birthDate: string | null;
      defaultRestSeconds: number;
    } = { id: 1, sex: null, birthDate: null, defaultRestSeconds: 120 },
  ) {
    const fixture = TestBed.createComponent(SettingsPage);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/profile').flush(profile);
    await fixture.whenStable();
    fixture.detectChanges();
    return { fixture, page: fixture.componentInstance, http };
  }

  it('populates the form from the profile', async () => {
    const { page } = await create({
      id: 1,
      sex: 'male',
      birthDate: '1995-04-03T00:00:00.000Z',
      defaultRestSeconds: 90,
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
    expect(req.request.body.birthDate).toBe('1995-04-03');
    req.flush({ id: 1, sex: null, birthDate: '1995-04-03', defaultRestSeconds: 120 });
  });
});
