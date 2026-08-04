import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Exercise } from '../../shared/utils/exercise.model';
import { ExercisesPage } from './exercises.page';

const EXERCISES: Exercise[] = [
  {
    id: 'e1',
    name: 'Barbell Bench Press',
    primary_muscles: ['chest'],
    secondary_muscles: ['triceps'],
    equipment: 'barbell',
    category: 'strength',
    instructions: null,
    is_custom: false,
  },
  {
    id: 'e2',
    name: 'Dumbbell Curl',
    primary_muscles: ['biceps'],
    secondary_muscles: [],
    equipment: 'dumbbell',
    category: 'strength',
    instructions: null,
    is_custom: true,
  },
];

function envelope(exercises: Exercise[]) {
  return { success: true, data: { exercises } };
}

describe('ExercisesPage', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
  });

  it('loads and renders the exercise list', async () => {
    const fixture = TestBed.createComponent(ExercisesPage);
    fixture.detectChanges();

    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/exercises').flush(envelope(EXERCISES));
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const el = fixture.nativeElement as HTMLElement;
    expect(el.textContent).toContain('Barbell Bench Press');
    expect(el.textContent).toContain('Dumbbell Curl');
    http.verify();
  });

  it('refetches with the muscle filter applied', async () => {
    const fixture = TestBed.createComponent(ExercisesPage);
    fixture.detectChanges();

    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/exercises').flush(envelope(EXERCISES));
    fixture.detectChanges();

    fixture.componentInstance.muscle.set('biceps');
    fixture.detectChanges();

    http.expectOne('/api/exercises?muscle=biceps').flush(envelope([EXERCISES[1]]));
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const el = fixture.nativeElement as HTMLElement;
    expect(el.textContent).not.toContain('Barbell Bench Press');
    expect(el.textContent).toContain('Dumbbell Curl');
    http.verify();
  });
});
