import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { MuscleDiagram } from '../../components/muscle-diagram/muscle-diagram';
import { muscleHighlights } from '../../components/muscle-diagram/muscle-map';
import { ExercisesApi } from '../../services/exercises-api';

@Component({
  selector: 'app-exercise-detail-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MuscleDiagram, RouterLink],
  template: `
    <a routerLink="/exercises" class="back">← Exercises</a>

    @if (exercise.value(); as ex) {
      <header class="page-header">
        <h1>{{ ex.name }}</h1>
        @if (ex.isCustom) {
          <button class="button danger" (click)="remove()">Delete</button>
        }
      </header>

      <div class="detail-grid">
        <section>
          <p class="chips">
            @for (m of ex.primaryMuscles; track m) {
              <span class="chip primary">{{ m }}</span>
            }
            @for (m of ex.secondaryMuscles; track m) {
              <span class="chip secondary">{{ m }}</span>
            }
            @if (ex.equipment) {
              <span class="chip">{{ ex.equipment }}</span>
            }
            @if (ex.category) {
              <span class="chip">{{ ex.category }}</span>
            }
          </p>
          @if (ex.instructions) {
            <ol class="instructions">
              @for (step of ex.instructions.split('\n'); track $index) {
                <li>{{ step }}</li>
              }
            </ol>
          }
        </section>
        <aside>
          <app-muscle-diagram [highlights]="highlights()" />
          <p class="legend">
            <span class="chip primary">primary</span>
            <span class="chip secondary">secondary</span>
          </p>
        </aside>
      </div>
    } @else if (exercise.error()) {
      <p class="error">Exercise not found.</p>
    }
  `,
  styles: `
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr minmax(14rem, 20rem);
      gap: 2rem;
    }
    @media (max-width: 40rem) {
      .detail-grid {
        grid-template-columns: 1fr;
      }
    }
    .instructions li {
      margin-bottom: 0.5rem;
      line-height: 1.5;
    }
    .legend {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
    }
    .back {
      display: inline-block;
      margin-bottom: 0.5rem;
    }
  `,
})
export class ExerciseDetailPage {
  private readonly api = inject(ExercisesApi);
  private readonly router = inject(Router);

  readonly id = input.required<string>();

  readonly exercise = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.get(params),
  });

  readonly highlights = computed(() => {
    const ex = this.exercise.value();
    return ex ? muscleHighlights(ex.primaryMuscles, ex.secondaryMuscles) : {};
  });

  remove() {
    if (!confirm('Delete this custom exercise?')) return;
    this.api.remove(this.id()).subscribe(() => {
      void this.router.navigate(['/exercises']);
    });
  }
}
