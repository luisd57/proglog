import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { SessionsService } from '../../shared/data-access/sessions.service';
import { TemplatesService } from '../../shared/data-access/templates.service';

@Component({
  selector: 'app-workouts-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink],
  template: `
    <header class="page-header">
      <h1>Workouts</h1>
      <a routerLink="/workouts/new" class="button">+ New workout</a>
    </header>

    @if (templates.value(); as list) {
      @if (list.length === 0) {
        <p class="count">No workouts yet. Create your first split.</p>
      }
      <ul class="template-list">
        @for (t of list; track t.id) {
          <li>
            <a [routerLink]="['/workouts', t.id]">
              <span class="name">{{ t.name }}</span>
              <span class="chip">{{ t.exercise_count }} exercises</span>
            </a>
            <button class="button" (click)="start(t.id)">Start</button>
          </li>
        }
      </ul>
    } @else if (templates.error()) {
      <p class="error">Could not load workouts.</p>
    }
  `,
  styles: `
    .template-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    .template-list li {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .template-list a {
      flex: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.9rem 1rem;
      border-radius: 8px;
      background: var(--surface);
      color: inherit;
      text-decoration: none;
      font-weight: 600;
    }
    .template-list a:hover {
      background: var(--surface-hover);
    }
  `,
})
export class WorkoutsPage {
  private readonly api = inject(TemplatesService);
  private readonly sessionsService = inject(SessionsService);
  private readonly router = inject(Router);

  readonly templates = rxResource({
    stream: () => this.api.list(),
  });

  start(templateId: string) {
    this.sessionsService.start(templateId).subscribe((session) => {
      void this.router.navigate(['/log', session.id]);
    });
  }
}
