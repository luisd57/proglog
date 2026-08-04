import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { MuscleDiagramComponent } from '../../shared/ui/muscle-diagram/muscle-diagram.component';
import { muscleHighlights } from '../../shared/utils/muscle-map';
import { Exercise } from '../../shared/utils/exercise.model';
import { ExercisesService } from '../../shared/data-access/exercises.service';
import { TemplatesService } from '../../shared/data-access/templates.service';

interface EditorRow {
  exercise: Exercise;
  targetSets?: number;
  targetReps?: number;
  restSeconds?: number;
}

@Component({
  selector: 'app-template-editor-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MuscleDiagramComponent, RouterLink],
  template: `
    <a routerLink="/workouts" class="back">← Workouts</a>
    <header class="page-header">
      <h1>{{ id() ? 'Edit workout' : 'New workout' }}</h1>
      <button class="button" (click)="save()" [disabled]="!valid()">Save</button>
    </header>

    <div class="editor-grid">
      <section>
        <label>
          Workout name
          <input
            type="text"
            placeholder="e.g. Push Day A"
            [value]="name()"
            (input)="name.set($any($event.target).value)"
          />
        </label>

        <h2>Exercises</h2>
        @if (rows().length === 0) {
          <p class="count">No exercises yet — add some below.</p>
        }
        <ul class="rows">
          @for (row of rows(); track $index) {
            <li>
              <div class="row-head">
                <span class="name">{{ row.exercise.name }}</span>
                <span class="row-actions">
                  <button (click)="move($index, -1)" [disabled]="$index === 0">↑</button>
                  <button (click)="move($index, 1)" [disabled]="$index === rows().length - 1">↓</button>
                  <button (click)="removeRow($index)">✕</button>
                </span>
              </div>
              <div class="targets">
                <label>
                  Sets
                  <input type="number" min="1" [value]="row.targetSets ?? ''"
                    (input)="setTarget($index, 'targetSets', $any($event.target).value)" />
                </label>
                <label>
                  Reps
                  <input type="number" min="1" [value]="row.targetReps ?? ''"
                    (input)="setTarget($index, 'targetReps', $any($event.target).value)" />
                </label>
                <label>
                  Rest (s)
                  <input type="number" min="0" step="15" [value]="row.restSeconds ?? ''"
                    (input)="setTarget($index, 'restSeconds', $any($event.target).value)" />
                </label>
              </div>
            </li>
          }
        </ul>

        <h2>Add exercise</h2>
        <input
          type="search"
          placeholder="Search exercises…"
          [value]="search()"
          (input)="search.set($any($event.target).value)"
        />
        @if (search().length > 1 && searchResults.value(); as found) {
          <ul class="picker">
            @for (ex of found.slice(0, 12); track ex.id) {
              <li>
                <button (click)="add(ex)">
                  + {{ ex.name }}
                  <span class="chip">{{ ex.primary_muscles.join(', ') }}</span>
                </button>
              </li>
            }
          </ul>
        }
      </section>

      <aside>
        <h2>Muscle coverage</h2>
        <app-muscle-diagram [highlights]="highlights()" />
        <p class="legend">
          <span class="chip primary">primary</span>
          <span class="chip secondary">secondary</span>
        </p>
      </aside>
    </div>
  `,
  styles: `
    .editor-grid {
      display: grid;
      grid-template-columns: 1fr minmax(14rem, 18rem);
      gap: 2rem;
    }
    @media (max-width: 44rem) {
      .editor-grid { grid-template-columns: 1fr; }
    }
    h2 { font-size: 1rem; margin: 1.25rem 0 0.5rem; }
    .rows, .picker { list-style: none; margin: 0; padding: 0; }
    .rows li {
      background: var(--surface);
      border-radius: 8px;
      padding: 0.6rem 0.75rem;
      margin-bottom: 0.5rem;
    }
    .row-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .row-actions button {
      background: var(--surface-hover);
      color: var(--text);
      border: none;
      border-radius: 4px;
      padding: 0.2rem 0.5rem;
      margin-left: 0.25rem;
      cursor: pointer;
    }
    .targets {
      display: flex;
      gap: 0.75rem;
      margin-top: 0.5rem;
    }
    .targets label { flex: 1; }
    .targets input { width: 100%; }
    .picker li button {
      width: 100%;
      text-align: left;
      background: var(--surface);
      color: var(--text);
      border: none;
      border-radius: 6px;
      padding: 0.45rem 0.6rem;
      margin-top: 2px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: 0.5rem;
    }
    .picker li button:hover { background: var(--surface-hover); }
    .legend { display: flex; gap: 0.5rem; justify-content: center; }
    .back { display: inline-block; margin-bottom: 0.5rem; }
  `,
})
export class TemplateEditorPage {
  private readonly api = inject(TemplatesService);
  private readonly exercisesService = inject(ExercisesService);
  private readonly router = inject(Router);

  readonly id = input<string>();

  readonly name = signal('');
  readonly rows = signal<EditorRow[]>([]);
  readonly search = signal('');

  readonly valid = computed(
    () => this.name().trim().length > 0 && this.rows().length > 0,
  );

  readonly highlights = computed(() => {
    const primary = this.rows().flatMap((r) => r.exercise.primary_muscles);
    const secondary = this.rows().flatMap((r) => r.exercise.secondary_muscles);
    return muscleHighlights(primary, secondary);
  });

  readonly searchResults = rxResource({
    params: () => this.search(),
    stream: ({ params }) =>
      params.length > 1 ? this.exercisesService.list({ search: params }) : of([]),
  });

  // populate the editor when editing an existing template
  readonly existing = rxResource({
    params: () => this.id(),
    stream: ({ params }) => {
      if (!params) return of(null);
      return this.api.get(params);
    },
  });

  private populated = false;

  constructor() {
    // one-shot: copy the loaded template into the editable signals
    effect(() => {
      const template = this.existing.value();
      if (template && !this.populated) {
        this.populated = true;
        this.name.set(template.name);
        this.rows.set(
          template.exercises.map((te) => ({
            exercise: te.exercise,
            targetSets: te.target_sets ?? undefined,
            targetReps: te.target_reps ?? undefined,
            restSeconds: te.rest_seconds ?? undefined,
          })),
        );
      }
    });
  }

  add(exercise: Exercise) {
    this.rows.update((rows) => [...rows, { exercise }]);
    this.search.set('');
  }

  removeRow(index: number) {
    this.rows.update((rows) => rows.filter((_, i) => i !== index));
  }

  move(index: number, delta: -1 | 1) {
    this.rows.update((rows) => {
      const target = index + delta;
      if (target < 0 || target >= rows.length) return rows;
      const next = [...rows];
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  setTarget(
    index: number,
    field: 'targetSets' | 'targetReps' | 'restSeconds',
    value: string,
  ) {
    this.rows.update((rows) =>
      rows.map((row, i) =>
        i === index
          ? { ...row, [field]: value === '' ? undefined : Number(value) }
          : row,
      ),
    );
  }

  save() {
    if (!this.valid()) return;
    const input = {
      name: this.name().trim(),
      exercises: this.rows().map((row) => ({
        exercise_id: row.exercise.id,
        target_sets: row.targetSets,
        target_reps: row.targetReps,
        rest_seconds: row.restSeconds,
      })),
    };
    const request = this.id()
      ? this.api.update(this.id()!, input)
      : this.api.create(input);
    request.subscribe(() => void this.router.navigate(['/workouts']));
  }
}
