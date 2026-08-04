import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { ChartPoint, LineChartComponent } from '../../shared/ui/line-chart/line-chart.component';
import { MuscleDiagramComponent } from '../../shared/ui/muscle-diagram/muscle-diagram.component';
import { muscleHighlights } from '../../shared/utils/muscle-map';
import { ExercisesService } from '../../shared/data-access/exercises.service';
import { SeriesPoint, StatsService } from '../../shared/data-access/stats.service';

@Component({
  selector: 'app-exercise-detail-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MuscleDiagramComponent, RouterLink, LineChartComponent, DatePipe, DecimalPipe],
  template: `
    <a routerLink="/exercises" class="back">← Exercises</a>

    @if (exercise.value(); as ex) {
      <header class="page-header">
        <h1>{{ ex.name }}</h1>
        @if (ex.is_custom) {
          <button class="button danger" (click)="remove()">Delete</button>
        }
      </header>

      <div class="detail-grid">
        <section>
          <p class="chips">
            @for (m of ex.primary_muscles; track m) {
              <span class="chip primary">{{ m }}</span>
            }
            @for (m of ex.secondary_muscles; track m) {
              <span class="chip secondary">{{ m }}</span>
            }
            @if (ex.equipment) {
              <span class="chip">{{ ex.equipment }}</span>
            }
            @if (ex.category) {
              <span class="chip">{{ ex.category }}</span>
            }
          </p>
          @if (series.value(); as s) {
            @if (s.points.length > 0) {
              <h2>Progress</h2>
              <app-line-chart title="Estimated 1RM" unit="kg" [points]="e1rmPoints()" />
              <app-line-chart title="Top set weight" unit="kg" [points]="topSetPoints()" />
              <app-line-chart title="Volume per session" unit="kg" [points]="volumePoints()" />

              <h2>Personal records</h2>
              <table class="pr-table">
                <tbody>
                  @for (pr of s.prs.slice().reverse(); track pr.date) {
                    <tr>
                      <td>{{ pr.date | date: 'd MMM y' }}</td>
                      <td>{{ pr.weight_kg }} kg × {{ pr.reps }}</td>
                      <td class="dim">e1RM {{ pr.e1rm | number: '1.0-1' }} kg</td>
                    </tr>
                  }
                </tbody>
              </table>
            }
          }

          @if (ex.instructions) {
            <h2>Instructions</h2>
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
    h2 { font-size: 1rem; margin: 1.5rem 0 0.75rem; }
    .instructions li {
      margin-bottom: 0.5rem;
      line-height: 1.5;
    }
    .pr-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }
    .pr-table td {
      padding: 0.4rem 0.5rem;
      border-bottom: 1px solid var(--border);
    }
    .dim { color: var(--text-dim); }
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
  private readonly api = inject(ExercisesService);
  private readonly statsService = inject(StatsService);
  private readonly router = inject(Router);

  readonly id = input.required<string>();

  readonly exercise = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.get(params),
  });

  readonly series = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.statsService.exerciseSeries(params),
  });

  readonly highlights = computed(() => {
    const ex = this.exercise.value();
    return ex ? muscleHighlights(ex.primary_muscles, ex.secondary_muscles) : {};
  });

  readonly e1rmPoints = computed(() => this.toChart((p) => Math.round(p.e1rm * 10) / 10));
  readonly topSetPoints = computed(() => this.toChart((p) => p.top_set.weight_kg));
  readonly volumePoints = computed(() => this.toChart((p) => p.volume));

  private toChart(pick: (p: SeriesPoint) => number): ChartPoint[] {
    const points = this.series.value()?.points ?? [];
    return points.map((p) => ({
      label: new Date(p.date).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
      }),
      value: pick(p),
    }));
  }

  remove() {
    if (!confirm('Delete this custom exercise?')) return;
    this.api.remove(this.id()).subscribe(() => {
      void this.router.navigate(['/exercises']);
    });
  }
}
