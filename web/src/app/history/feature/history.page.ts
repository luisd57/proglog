import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { SessionsService } from '../../shared/data-access/sessions.service';

@Component({
  selector: 'app-history-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, DatePipe],
  template: `
    <header class="page-header">
      <h1>History</h1>
    </header>

    @if (sessions.value(); as list) {
      @if (list.length === 0) {
        <p class="count">No sessions yet — start one from Workouts.</p>
      }
      <ul class="session-list">
        @for (s of list; track s.id) {
          <li>
            <a [routerLink]="['/log', s.id]">
              <span class="name">
                {{ s.template_name ?? 'Workout' }}
                @if (!s.finished_at) {
                  <span class="chip custom">in progress</span>
                }
              </span>
              <span class="meta">
                <span class="chip">{{ s.started_at | date: 'EEE d MMM' }}</span>
                <span class="chip">{{ s.exercise_count }} exercises</span>
                <span class="chip">{{ s.set_count }} sets</span>
              </span>
            </a>
            <button class="remove" (click)="remove(s.id)" title="Delete session">✕</button>
          </li>
        }
      </ul>
    }
  `,
  styles: `
    .session-list { list-style: none; padding: 0; margin: 0; }
    .session-list li {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.4rem;
    }
    .session-list a {
      flex: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 0.7rem 0.9rem;
      border-radius: 8px;
      background: var(--surface);
      color: inherit;
      text-decoration: none;
    }
    .session-list a:hover { background: var(--surface-hover); }
    .name { font-weight: 600; }
    .meta { display: flex; gap: 0.3rem; flex-wrap: wrap; }
    .remove {
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
      padding: 0.5rem;
    }
  `,
})
export class HistoryPage {
  private readonly api = inject(SessionsService);

  readonly sessions = rxResource({
    stream: () => this.api.list(),
  });

  remove(id: string) {
    if (!confirm('Delete this session?')) return;
    this.api.remove(id).subscribe(() => this.sessions.reload());
  }
}
