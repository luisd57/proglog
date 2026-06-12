import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { EQUIPMENT_NAMES, MUSCLE_NAMES } from '../../models/exercise';
import { ExercisesApi } from '../../services/exercises-api';

@Component({
  selector: 'app-exercises-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink],
  template: `
    <header class="page-header">
      <h1>Exercises</h1>
      <a routerLink="/exercises/new" class="button">+ New exercise</a>
    </header>

    <div class="filters">
      <input
        type="search"
        placeholder="Search…"
        [value]="search()"
        (input)="search.set($any($event.target).value)"
      />
      <select [value]="muscle()" (change)="muscle.set($any($event.target).value)">
        <option value="">Any muscle</option>
        @for (m of muscles; track m) {
          <option [value]="m">{{ m }}</option>
        }
      </select>
      <select
        [value]="equipment()"
        (change)="equipment.set($any($event.target).value)"
      >
        <option value="">Any equipment</option>
        @for (e of equipments; track e) {
          <option [value]="e">{{ e }}</option>
        }
      </select>
    </div>

    @if (exercises.value(); as list) {
      <p class="count">{{ list.length }} exercises</p>
      <ul class="exercise-list">
        @for (exercise of list; track exercise.id) {
          <li>
            <a [routerLink]="['/exercises', exercise.id]">
              <span class="name">
                {{ exercise.name }}
                @if (exercise.isCustom) {
                  <span class="chip custom">custom</span>
                }
              </span>
              <span class="meta">
                @for (m of exercise.primaryMuscles; track m) {
                  <span class="chip primary">{{ m }}</span>
                }
                @for (m of exercise.secondaryMuscles; track m) {
                  <span class="chip secondary">{{ m }}</span>
                }
                @if (exercise.equipment) {
                  <span class="chip">{{ exercise.equipment }}</span>
                }
              </span>
            </a>
          </li>
        }
      </ul>
    } @else if (exercises.error()) {
      <p class="error">Could not load exercises.</p>
    } @else {
      <p class="count">Loading…</p>
    }
  `,
  styles: `
    .exercise-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .exercise-list a {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 0.55rem 0.75rem;
      border-radius: 6px;
      text-decoration: none;
      color: inherit;
      background: var(--surface);
    }
    .exercise-list a:hover {
      background: var(--surface-hover);
    }
    .name {
      font-weight: 500;
    }
    .meta {
      display: flex;
      gap: 0.3rem;
      flex-wrap: wrap;
      justify-content: flex-end;
    }
  `,
})
export class ExercisesPage {
  private readonly api = inject(ExercisesApi);

  readonly search = signal('');
  readonly muscle = signal('');
  readonly equipment = signal('');

  protected readonly muscles = MUSCLE_NAMES;
  protected readonly equipments = EQUIPMENT_NAMES;

  readonly exercises = rxResource({
    params: () => ({
      search: this.search(),
      muscle: this.muscle(),
      equipment: this.equipment(),
    }),
    stream: ({ params }) => this.api.list(params),
  });
}
