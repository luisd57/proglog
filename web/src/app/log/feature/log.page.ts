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
import { forkJoin, map, of } from 'rxjs';
import { RestTimerComponent } from '../../shared/ui/rest-timer/rest-timer.component';
import { ExercisesService } from '../../shared/data-access/exercises.service';
import { SessionsService } from '../../shared/data-access/sessions.service';
import { ExerciseBest } from '../../shared/utils/e1rm';
import { SetInput } from '../../shared/utils/session.model';
import { StatsService } from '../../shared/data-access/stats.service';
import { SessionExerciseCardComponent } from '../ui/session-exercise-card.component';

@Component({
  selector: 'app-log-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RestTimerComponent, SessionExerciseCardComponent, DatePipe],
  template: `
    @if (session.value(); as s) {
      <header class="page-header">
        <div>
          <h1>{{ s.template_name ?? 'Workout' }}</h1>
          <p class="count">
            {{ s.started_at | date: 'EEE d MMM, HH:mm' }}
            @if (s.finished_at) {
              · finished
            }
          </p>
        </div>
        @if (!s.finished_at) {
          <button class="button" (click)="finish()">Finish</button>
        }
      </header>

      @for (se of s.exercises; track se.id) {
        <app-session-exercise-card
          [data]="se"
          [best]="bests.value()?.[se.id] ?? null"
          (rest)="timer().start($event)"
          (removed)="removeExercise(se.id)"
          (saveSets)="persistSets(se.id, $event)"
          (notesChange)="persistNotes(se.id, $event)"
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
  private readonly api = inject(SessionsService);
  private readonly exercisesService = inject(ExercisesService);
  private readonly statsService = inject(StatsService);
  private readonly router = inject(Router);

  readonly id = input.required<string>();
  readonly search = signal('');
  readonly timer = viewChild.required(RestTimerComponent);

  readonly session = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.get(params),
  });

  // Best lift per session exercise, fetched here so the cards stay dumb.
  readonly bests = rxResource({
    params: () => {
      const s = this.session.value();
      return s ? s.exercises.map((se) => ({ seId: se.id, exId: se.exercise.id })) : [];
    },
    stream: ({ params }) => {
      if (params.length === 0) return of({} as Record<string, ExerciseBest>);
      return forkJoin(
        params.map((p) =>
          this.statsService
            .exerciseBest(p.exId, this.id())
            .pipe(map((best) => [p.seId, best] as const)),
        ),
      ).pipe(map((entries) => Object.fromEntries(entries)));
    },
  });

  readonly searchResults = rxResource({
    params: () => this.search(),
    stream: ({ params }) =>
      params.length > 1 ? this.exercisesService.list({ search: params }) : of([]),
  });

  persistSets(sessionExerciseId: string, sets: SetInput[]) {
    this.api.replaceSets(this.id(), sessionExerciseId, sets).subscribe();
  }

  persistNotes(sessionExerciseId: string, notes: string) {
    this.api.updateExerciseNotes(this.id(), sessionExerciseId, notes).subscribe();
  }

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
