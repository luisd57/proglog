import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { TemplatesApi } from '../../services/templates-api';

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
              <span class="chip">{{ t.exerciseCount }} exercises</span>
            </a>
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
    .template-list a {
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
  private readonly api = inject(TemplatesApi);

  readonly templates = rxResource({
    stream: () => this.api.list(),
  });
}
