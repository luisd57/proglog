import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { ChartPoint, LineChart } from '../../components/line-chart/line-chart';
import {
  MEASUREMENT_LABELS,
  MeasurementsApi,
} from '../../services/measurements-api';

@Component({
  selector: 'app-measurements-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [LineChart, DatePipe],
  template: `
    <header class="page-header">
      <h1>Measurements</h1>
    </header>

    <div class="filters">
      <select [value]="type()" (change)="type.set($any($event.target).value)">
        @for (t of types; track t) {
          <option [value]="t">{{ labels[t] }}</option>
        }
      </select>
    </div>

    <form class="entry" (submit)="add($event)">
      <input
        type="number" step="0.1" min="0" [placeholder]="labels[type()]"
        [value]="value()"
        (input)="value.set($any($event.target).value)"
      />
      <input
        type="date"
        [value]="date()"
        (input)="date.set($any($event.target).value)"
      />
      <button class="button" type="submit" [disabled]="!value()">Add</button>
    </form>

    @if (series.value(); as list) {
      @if (list.length > 1) {
        <app-line-chart [title]="labels[type()]" [points]="chartPoints()" />
      }
      @if (list.length === 0) {
        <p class="count">No entries yet.</p>
      }
      <ul class="entries">
        @for (m of list.slice().reverse(); track m.id) {
          <li>
            <span>{{ m.measuredAt | date: 'EEE d MMM y' }}</span>
            <span class="val">{{ m.value }}</span>
            <button class="remove" (click)="remove(m.id)">✕</button>
          </li>
        }
      </ul>
    }
  `,
  styles: `
    .entry {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.25rem;
    }
    .entry input[type='number'] { flex: 1; }
    .entries { list-style: none; padding: 0; margin: 0; }
    .entries li {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.45rem 0.6rem;
      border-bottom: 1px solid var(--border);
      font-size: 0.92rem;
    }
    .entries span:first-child { color: var(--text-dim); flex: 1; }
    .val { font-weight: 600; }
    .remove {
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
    }
  `,
})
export class MeasurementsPage {
  private readonly api = inject(MeasurementsApi);

  readonly labels = MEASUREMENT_LABELS;
  readonly types = Object.keys(MEASUREMENT_LABELS);

  readonly type = signal('weight');
  readonly value = signal('');
  readonly date = signal(new Date().toISOString().slice(0, 10));

  readonly series = rxResource({
    params: () => this.type(),
    stream: ({ params }) => this.api.series(params),
  });

  readonly chartPoints = computed<ChartPoint[]>(() =>
    (this.series.value() ?? []).map((m) => ({
      label: new Date(m.measuredAt).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
      }),
      value: m.value,
    })),
  );

  add(event: Event) {
    event.preventDefault();
    const value = Number(this.value());
    if (!value) return;
    this.api
      .add({ type: this.type(), value, measuredAt: this.date() })
      .subscribe(() => {
        this.value.set('');
        this.series.reload();
      });
  }

  remove(id: string) {
    this.api.remove(id).subscribe(() => this.series.reload());
  }
}
