import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { MuscleDiagram } from '../../components/muscle-diagram/muscle-diagram';
import { muscleHighlights } from '../../components/muscle-diagram/muscle-map';
import { SessionsApi } from '../../services/sessions-api';
import { StatsApi } from '../../services/stats-api';
import { TemplatesApi } from '../../services/templates-api';

@Component({
  selector: 'app-dashboard-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MuscleDiagram, RouterLink, DatePipe],
  template: `
    <header class="page-header">
      <h1>ProgLog</h1>
    </header>

    <section class="start">
      <h2>Start a workout</h2>
      @if (templates.value(); as list) {
        @if (list.length === 0) {
          <p class="count">
            No splits yet — <a routerLink="/workouts/new">create one</a>.
          </p>
        }
        <div class="start-buttons">
          @for (t of list; track t.id) {
            <button class="button" (click)="start(t.id)">{{ t.name }}</button>
          }
        </div>
      }
    </section>

    <div class="dash-grid">
      <section>
        <h2>Trained this week</h2>
        @if (weekly.value(); as w) {
          <p class="count">
            {{ w.sessionCount }} session{{ w.sessionCount === 1 ? '' : 's' }} in
            the last 7 days
          </p>
          <app-muscle-diagram [highlights]="highlights()" />
        }
      </section>

      <section>
        <h2>Recent sessions</h2>
        @if (sessions.value(); as list) {
          @if (list.length === 0) {
            <p class="count">Nothing logged yet.</p>
          }
          <ul class="recent">
            @for (s of list.slice(0, 5); track s.id) {
              <li>
                <a [routerLink]="['/log', s.id]">
                  <span>
                    {{ s.templateName ?? 'Workout' }}
                    @if (!s.finishedAt) {
                      <span class="chip custom">in progress</span>
                    }
                  </span>
                  <span class="chip">{{ s.startedAt | date: 'EEE d MMM' }}</span>
                </a>
              </li>
            }
          </ul>
        }
      </section>
    </div>
  `,
  styles: `
    h2 { font-size: 1rem; margin: 1.25rem 0 0.5rem; }
    .start-buttons {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .start-buttons .button { font-size: 1rem; padding: 0.65rem 1.2rem; }
    .dash-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
      margin-top: 0.5rem;
    }
    @media (max-width: 44rem) {
      .dash-grid { grid-template-columns: 1fr; }
    }
    .recent { list-style: none; padding: 0; margin: 0; }
    .recent a {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 0.7rem;
      border-radius: 6px;
      background: var(--surface);
      color: inherit;
      text-decoration: none;
      margin-bottom: 3px;
    }
    .recent a:hover { background: var(--surface-hover); }
  `,
})
export class DashboardPage {
  private readonly statsApi = inject(StatsApi);
  private readonly templatesApi = inject(TemplatesApi);
  private readonly sessionsApi = inject(SessionsApi);
  private readonly router = inject(Router);

  readonly weekly = rxResource({ stream: () => this.statsApi.weeklyMuscles() });
  readonly templates = rxResource({ stream: () => this.templatesApi.list() });
  readonly sessions = rxResource({ stream: () => this.sessionsApi.list() });

  readonly highlights = computed(() => {
    const w = this.weekly.value();
    return w ? muscleHighlights(w.primary, w.secondary) : {};
  });

  start(templateId: string) {
    this.sessionsApi.start(templateId).subscribe((session) => {
      void this.router.navigate(['/log', session.id]);
    });
  }
}
