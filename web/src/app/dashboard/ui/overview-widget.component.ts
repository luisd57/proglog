import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { ChartPoint, LineChartComponent } from '../../shared/ui/line-chart/line-chart.component';
import { OverviewResult } from '../../shared/utils/stats.model';
import { delta, formatDuration } from '../utils/overview';

interface StatCell {
  label: string;
  value: string;
  deltaText: string | null; // null when there is no previous period (all-time)
  positive: boolean;
}

const PERIODS: { value: string; label: string }[] = [
  { value: '7d', label: 'The last 7 days' },
  { value: '30d', label: 'The last 30 days' },
  { value: '90d', label: 'The last 90 days' },
  { value: '365d', label: 'The last year' },
  { value: 'all', label: 'All time' },
];

@Component({
  selector: 'app-overview-widget',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [LineChartComponent],
  template: `
    <header class="ov-head">
      <h2>Overview</h2>
      <select [value]="period()" (change)="periodChange.emit($any($event.target).value)">
        @for (p of periods; track p.value) {
          <option [value]="p.value">{{ p.label }}</option>
        }
      </select>
    </header>

    @if (result(); as o) {
      <div class="ov-body" [class.loading]="loading()">
      <app-line-chart title="Cumulative volume" unit="kg" [points]="chartPoints()" />
      <div class="grid">
        @for (cell of cells(); track cell.label) {
          <div class="cell">
            <span class="cell-label">{{ cell.label }}</span>
            <span class="cell-value">{{ cell.value }}</span>
            @if (cell.deltaText) {
              <span class="cell-delta" [class.up]="cell.positive" [class.down]="!cell.positive">
                {{ cell.deltaText }}
              </span>
            }
          </div>
        }
      </div>
      </div>
    }
  `,
  styles: `
    .ov-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin: 1.25rem 0 0.5rem;
    }
    .ov-head h2 { font-size: 1rem; margin: 0; }
    .ov-head select {
      background: var(--surface);
      color: inherit;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.4rem 0.6rem;
      font-size: 0.9rem;
    }
    .ov-body { transition: opacity 0.15s ease; }
    .ov-body.loading { opacity: 0.5; }
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.6rem;
    }
    .cell {
      background: var(--surface);
      border-radius: 10px;
      padding: 0.6rem 0.8rem;
      display: flex;
      flex-direction: column;
      gap: 0.1rem;
    }
    .cell-label { font-size: 0.8rem; color: var(--text-dim); }
    .cell-value { font-size: 1.25rem; font-weight: 700; }
    .cell-delta { font-size: 0.78rem; }
    .cell-delta.up { color: #4ade80; }
    .cell-delta.down { color: #f87171; }
  `,
})
export class OverviewWidgetComponent {
  readonly periods = PERIODS;

  readonly result = input<OverviewResult | undefined>(undefined);
  readonly period = input.required<string>();
  readonly loading = input(false);

  readonly periodChange = output<string>();

  readonly chartPoints = computed<ChartPoint[]>(() => {
    const o = this.result();
    if (!o) return [];
    return o.cumulative_volume.map((p) => ({ label: p.date.slice(5), value: p.value }));
  });

  readonly cells = computed<StatCell[]>(() => {
    const o = this.result();
    if (!o) return [];
    return [
      this.cell(o, 'Workouts', 'workouts', (n) => `${n}`),
      this.cell(o, 'Lifted', 'volume_kg', (n) => `${Math.round(n).toLocaleString()} kg`),
      this.cell(o, 'Reps', 'reps', (n) => `${n}`),
      this.cell(o, 'Sets', 'sets', (n) => `${n}`),
      this.cell(o, 'Heaviest', 'heaviest_kg', (n) => `${n} kg`),
      this.cell(o, 'Time', 'time_seconds', (n) => `${formatDuration(n)} h`),
    ];
  });

  private cell(
    o: OverviewResult,
    label: string,
    key: keyof OverviewResult['current'],
    fmt: (n: number) => string,
  ): StatCell {
    const current = o.current[key];
    const value = fmt(current);
    if (o.previous === null) {
      return { label, value, deltaText: null, positive: true };
    }
    const d = delta(current, o.previous[key]);
    const sign = d.abs >= 0 ? '+' : '−';
    const absText =
      key === 'time_seconds'
        ? `${sign}${formatDuration(Math.abs(d.abs))}`
        : `${sign}${Math.abs(Math.round(d.abs)).toLocaleString()}`;
    const pctText = d.pct === null ? '–%' : `${d.pct >= 0 ? '+' : '−'}${Math.abs(Math.round(d.pct))}%`;
    return {
      label,
      value,
      deltaText: `${absText} / ${pctText}`,
      positive: d.abs >= 0,
    };
  }
}
