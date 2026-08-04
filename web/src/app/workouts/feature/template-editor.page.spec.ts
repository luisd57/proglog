import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Exercise } from '../../shared/utils/exercise.model';
import { TemplateEditorPage } from './template-editor.page';

const BENCH: Exercise = {
  id: 'ex-bench',
  name: 'Bench Press',
  primary_muscles: ['chest'],
  secondary_muscles: ['triceps'],
  equipment: 'barbell',
  category: 'strength',
  instructions: null,
  is_custom: false,
};

const ROW: Exercise = {
  id: 'ex-row',
  name: 'Barbell Row',
  primary_muscles: ['middle back'],
  secondary_muscles: ['biceps'],
  equipment: 'barbell',
  category: 'strength',
  instructions: null,
  is_custom: false,
};

describe('TemplateEditorPage', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: 'workouts', children: [] }]),
      ],
    });
  });

  function create() {
    const fixture = TestBed.createComponent(TemplateEditorPage);
    fixture.detectChanges();
    return { fixture, page: fixture.componentInstance };
  }

  it('adds picked exercises as rows and computes aggregate highlights', () => {
    const { page } = create();
    page.add(BENCH);
    page.add(ROW);

    expect(page.rows().map((r) => r.exercise.name)).toEqual([
      'Bench Press',
      'Barbell Row',
    ]);
    const highlights = page.highlights();
    expect(highlights['chest']).toBe('primary');
    expect(highlights['upper-back']).toBe('primary');
    expect(highlights['biceps']).toBe('secondary');
    expect(highlights['triceps']).toBe('secondary');
  });

  it('moves and removes rows', () => {
    const { page } = create();
    page.add(BENCH);
    page.add(ROW);

    page.move(1, -1);
    expect(page.rows().map((r) => r.exercise.id)).toEqual(['ex-row', 'ex-bench']);

    page.removeRow(0);
    expect(page.rows().map((r) => r.exercise.id)).toEqual(['ex-bench']);
  });

  it('saves a new template with ordered exercises and targets', () => {
    const { fixture, page } = create();
    const http = TestBed.inject(HttpTestingController);

    page.name.set('Push Day');
    page.add(BENCH);
    page.setTarget(0, 'targetSets', '3');
    page.setTarget(0, 'restSeconds', '120');
    page.save();
    fixture.detectChanges();

    const req = http.expectOne('/api/templates');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      name: 'Push Day',
      exercises: [
        { exercise_id: 'ex-bench', target_sets: 3, target_reps: undefined, rest_seconds: 120 },
      ],
    });
    req.flush({
      success: true,
      data: { template: { id: 't1', name: 'Push Day', sort_order: 0, exercises: [] } },
    });
  });
});
