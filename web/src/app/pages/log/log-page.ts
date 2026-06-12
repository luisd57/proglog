import {
  ChangeDetectionStrategy,
  Component,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { RestTimer } from '../../components/rest-timer/rest-timer';
import { ExercisesApi } from '../../services/exercises-api';
import { SessionsApi } from '../../services/sessions-api';
import { SessionExerciseCard } from './session-exercise-card';

@Component({
  selector: 'app-log-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RestTimer, SessionExerciseCard, DatePipe],
  template: `
    @if (session.value(); as s) {
      <header class="page-header">
        <div>
          <h1>{{ s.templateName ?? 'Workout' }}</h1>
          <p class="count">
            {{ s.startedAt | date: 'EEE d MMM, HH:mm' }}
            @if (s.finishedAt) {
              · finished
            }
          </p>
        </div>
        @if (!s.finishedAt) {
          <button class="button" (click)="finish()">Finish</button>
        }
      </header>

      @for (se of s.exercises; track se.id) {
        <app-session-exercise-card
          [sessionId]="s.id"
          [data]="se"
          (rest)="timer().start($event)"
          (removed)="removeExercise(se.id)"
        />
      }

      <h2>Add exercise</h2>
      <input
        type="search"
        placeholder="Search exercises…"
        [value]="search()"
        (input)="search.set($any($event.target).value)"
      />
      @if (search().length > 1 && searchResults.value(); as found) {
        <ul class="picker">
          @for (ex of found.slice(0, 8); track ex.id) {
            <li><button (click)="addExercise(ex.id)">+ {{ ex.name }}</button></li>
          }
        </ul>
      }

      <footer class="session-footer">
        <button class="button danger" (click)="discard()">Discard session</button>
      </footer>
    } @else if (session.error()) {
      <p class="error">Session not found.</p>
    }

    <app-rest-timer />
  `,
  styles: `
    h2 { font-size: 1rem; margin: 1.5rem 0 0.5rem; }
    input[type='search'] { width: 100%; }
    .picker { list-style: none; margin: 0.25rem 0 0; padding: 0; }
    .picker button {
      width: 100%;
      text-align: left;
      background: var(--surface);
      color: var(--text);
      border: none;
      border-radius: 6px;
      padding: 0.45rem 0.6rem;
      margin-top: 2px;
      cursor: pointer;
    }
    .picker button:hover { background: var(--surface-hover); }
    .session-footer {
      margin-top: 2.5rem;
      display: flex;
      justify-content: center;
    }
  `,
})
export class LogPage {
  private readonly api = inject(SessionsApi);
  private readonly exercisesApi = inject(ExercisesApi);
  private readonly router = inject(Router);

  readonly id = input.required<string>();
  readonly search = signal('');
  readonly timer = viewChild.required(RestTimer);

  readonly session = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.get(params),
  });

  readonly searchResults = rxResource({
    params: () => this.search(),
    stream: ({ params }) =>
      params.length > 1 ? this.exercisesApi.list({ search: params }) : of([]),
  });

  addExercise(exerciseId: string) {
    this.api.addExercise(this.id(), exerciseId).subscribe(() => {
      this.search.set('');
      this.session.reload();
    });
  }

  removeExercise(sessionExerciseId: string) {
    if (!confirm('Remove this exercise and its sets?')) return;
    this.api
      .removeExercise(this.id(), sessionExerciseId)
      .subscribe(() => this.session.reload());
  }

  finish() {
    this.api.finish(this.id()).subscribe(() => {
      void this.router.navigate(['/history']);
    });
  }

  discard() {
    if (!confirm('Discard this session and all logged sets?')) return;
    this.api.remove(this.id()).subscribe(() => {
      void this.router.navigate(['/workouts']);
    });
  }
}
