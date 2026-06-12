import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Exercise } from '../../models/exercise';
import { ExercisesPage } from './exercises-page';

const EXERCISES: Exercise[] = [
  {
    id: 'e1',
    name: 'Barbell Bench Press',
    primaryMuscles: ['chest'],
    secondaryMuscles: ['triceps'],
    equipment: 'barbell',
    category: 'strength',
    instructions: null,
    isCustom: false,
  },
  {
    id: 'e2',
    name: 'Dumbbell Curl',
    primaryMuscles: ['biceps'],
    secondaryMuscles: [],
    equipment: 'dumbbell',
    category: 'strength',
    instructions: null,
    isCustom: true,
  },
];

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
    http.expectOne('/api/exercises').flush(EXERCISES);
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
    http.expectOne('/api/exercises').flush(EXERCISES);
    fixture.detectChanges();

    fixture.componentInstance.muscle.set('biceps');
    fixture.detectChanges();

    http.expectOne('/api/exercises?muscle=biceps').flush([EXERCISES[1]]);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const el = fixture.nativeElement as HTMLElement;
    expect(el.textContent).not.toContain('Barbell Bench Press');
    expect(el.textContent).toContain('Dumbbell Curl');
    http.verify();
  });
});
