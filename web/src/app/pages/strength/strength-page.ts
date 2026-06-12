import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { StatsApi } from '../../services/stats-api';

@Component({
  selector: 'app-strength-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, DecimalPipe],
  template: `
    <header class="page-header">
      <h1>Strength levels</h1>
      @if (data.value()?.bodyweightKg; as bw) {
        <span class="chip">@ {{ bw }} kg bodyweight</span>
      }
    </header>

    @if (data.value(); as result) {
      @if (!result.ready) {
        <p class="count">
          @if (result.reason === 'no-bodyweight') {
            Log your <a routerLink="/measurements">body weight</a> first — levels
            are relative to bodyweight.
          } @else {
            Set your sex in <a routerLink="/settings">settings</a> — standards
            differ by sex.
          }
        </p>
      } @else {
        <ul class="levels">
          @for (entry of result.levels; track entry.lift) {
            <li class="card">
              <div class="row-head">
                <span class="name">
                  @if (entry.exerciseId) {
                    <a [routerLink]="['/exercises', entry.exerciseId]">{{ entry.label }}</a>
                  } @else {
                    {{ entry.label }}
                  }
                </span>
                @if (entry.level) {
                  <span class="chip level" [attr.data-level]="entry.level">{{ entry.level }}</span>
                } @else {
                  <span class="chip">no data yet</span>
                }
              </div>

              @if (entry.e1rm !== null) {
                <p class="count">
                  e1RM {{ entry.e1rm | number: '1.0-1' }} kg
                  @if (entry.nextLevel) {
                    · {{ entry.progress! * 100 | number: '1.0-0' }}% to {{ entry.nextLevel }}
                    ({{ nextThreshold(entry) | number: '1.0-0' }} kg)
                  }
                </p>
                <div class="bar">
                  <div class="fill" [style.width.%]="(entry.progress ?? 0) * 100"></div>
                </div>
              } @else {
                <p class="count">
                  Log this lift to see your level. Beginner starts at
                  {{ entry.thresholds[0] | number: '1.0-0' }} kg.
                </p>
              }
            </li>
          }
        </ul>
      }
    }
  `,
  styles: `
    .levels { list-style: none; padding: 0; margin: 0; }
    .card {
      background: var(--surface);
      border-radius: 10px;
      padding: 0.85rem 1rem;
      margin-bottom: 0.75rem;
    }
    .row-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .name { font-weight: 600; }
    .name a { color: inherit; text-decoration: none; }
    .name a:hover { color: var(--accent); }
    .level { text-transform: capitalize; }
    .level[data-level='beginner'] { background: #374151; color: #d1d5db; }
    .level[data-level='novice'] { background: #1e3a5f; color: #93c5fd; }
    .level[data-level='intermediate'] { background: #14532d; color: #4ade80; }
    .level[data-level='advanced'] { background: #713f12; color: #fbbf24; }
    .level[data-level='elite'] { background: #581c87; color: #d8b4fe; }
    .level[data-level='untrained'] { background: var(--surface-hover); color: var(--text-dim); }
    .bar {
      height: 0.45rem;
      border-radius: 999px;
      background: var(--surface-hover);
      overflow: hidden;
      margin-top: 0.4rem;
    }
    .fill {
      height: 100%;
      background: var(--accent);
      border-radius: 999px;
    }
  `,
})
export class StrengthPage {
  private readonly api = inject(StatsApi);

  readonly data = rxResource({
    stream: () => this.api.strengthLevels(),
  });

  nextThreshold(entry: { thresholds: number[]; nextLevel: string | null }): number {
    const levels = ['beginner', 'novice', 'intermediate', 'advanced', 'elite'];
    const index = levels.indexOf(entry.nextLevel ?? '');
    return index >= 0 ? entry.thresholds[index] : 0;
  }
}
