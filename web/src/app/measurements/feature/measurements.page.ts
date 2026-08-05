import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ChartPoint, LineChartComponent } from '../../shared/ui/line-chart/line-chart.component';
import { MeasurementsService } from '../../shared/data-access/measurements.service';
import { MEASUREMENT_LABELS } from '../../shared/utils/measurement.model';
import { estimateNavyBodyFat } from '../utils/body-fat';

@Component({
  selector: 'app-measurements-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [LineChartComponent, DatePipe, RouterLink],
  template: `
    <header class="page-header">
      <h1>Measurements</h1>
    </header>

    <form class="entry-form" (submit)="save($event)">
      <label class="date-field">
        Date
        <input
          type="date"
          [value]="date()"
          (change)="date.set($any($event.target).value)"
        />
      </label>

      <div class="field-grid">
        @for (t of types; track t) {
          <label>
            {{ labels[t] }}
            <input
              type="number" step="0.1" min="0" inputmode="decimal"
              [attr.data-type]="t"
              [placeholder]="placeholderFor(t)"
              [value]="fields()[t] ?? ''"
              (input)="setField(t, $any($event.target).value)"
            />
          </label>
        }
      </div>

      <button class="button" type="submit" [disabled]="!hasValues()">
        Save filled fields
      </button>
      @if (saved()) {
        <span class="count">Saved.</span>
      }
    </form>

    <section class="bodyfat">
      <h2>Estimated body fat</h2>
      @if (estimatedBodyFat(); as bf) {
        <div class="bf-readout">
          <span class="bf-value">{{ bf }}%</span>
          <span class="count">US Navy method, from your neck/waist@if (isFemale()){/hips} + height</span>
          <button class="button" type="button" (click)="logEstimate()">Log it</button>
        </div>
      } @else {
        <p class="count">
          Set your <a routerLink="/settings">height and sex</a>, and enter
          neck + waist@if (isFemale()){ + hips} (above or previously logged) to
          estimate body fat.
        </p>
      }
    </section>

    <h2>History</h2>
    <div class="filters">
      <select [value]="type()" (change)="type.set($any($event.target).value)">
        @for (t of types; track t) {
          <option [value]="t">{{ labels[t] }}</option>
        }
      </select>
    </div>

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
            <span>{{ m.measured_at | date: 'EEE d MMM y' }}</span>
            <span class="val">{{ m.value }}</span>
            <button class="remove" (click)="remove(m.id)">✕</button>
          </li>
        }
      </ul>
    }
  `,
  styles: `
    .entry-form {
      background: var(--surface);
      border-radius: 10px;
      padding: 1rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      align-items: flex-start;
    }
    .date-field { width: 11rem; }
    .field-grid {
      width: 100%;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr));
      gap: 0.75rem;
    }
    .field-grid input { width: 100%; }
    h2 { font-size: 1rem; margin: 1.5rem 0 0.5rem; }
    .bf-readout {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .bf-value {
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--accent);
    }
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
  private readonly api = inject(MeasurementsService);

  readonly labels = MEASUREMENT_LABELS;
  readonly types = Object.keys(MEASUREMENT_LABELS);

  readonly date = signal(new Date().toISOString().slice(0, 10));
  readonly fields = signal<Record<string, string>>({});
  readonly saved = signal(false);
  readonly type = signal('weight');

  readonly latest = rxResource({ stream: () => this.api.latestAll() });
  readonly profile = rxResource({ stream: () => this.api.profile() });

  readonly isFemale = computed(() => this.profile.value()?.sex === 'female');

  readonly estimatedBodyFat = computed(() => {
    const p = this.profile.value();
    return estimateNavyBodyFat({
      sex: p?.sex ?? null,
      heightCm: p?.height_cm ?? null,
      neckCm: this.effectiveValue('neck'),
      waistCm: this.effectiveValue('waist'),
      hipsCm: this.effectiveValue('hips'),
    });
  });

  readonly series = rxResource({
    params: () => this.type(),
    stream: ({ params }) => this.api.series(params),
  });

  readonly hasValues = computed(() =>
    Object.values(this.fields()).some((v) => v !== '' && Number(v) > 0),
  );

  readonly chartPoints = computed<ChartPoint[]>(() =>
    (this.series.value() ?? []).map((m) => ({
      label: new Date(m.measured_at).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
      }),
      value: m.value,
    })),
  );

  placeholderFor(type: string): string {
    const value = this.latest.value()?.[type];
    return value !== undefined ? String(value) : '—';
  }

  // value currently being typed (if valid) wins over the latest saved one,
  // so the estimate updates live as the user enters new girths
  private effectiveValue(type: string): number | null {
    const typed = this.fields()[type];
    if (typed !== undefined && typed !== '' && Number(typed) > 0) {
      return Number(typed);
    }
    return this.latest.value()?.[type] ?? null;
  }

  // the API expects RFC 3339 timestamps for measured_at
  private measuredAt(): string {
    return new Date(this.date()).toISOString();
  }

  logEstimate() {
    const value = this.estimatedBodyFat();
    if (value === null) return;
    this.api
      .add({ type: 'bodyfat', value, measured_at: this.measuredAt() })
      .subscribe(() => {
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 2000);
        this.latest.reload();
        this.series.reload();
      });
  }

  setField(type: string, value: string) {
    this.fields.update((fields) => {
      const next = { ...fields };
      if (value === '') {
        delete next[type];
      } else {
        next[type] = value;
      }
      return next;
    });
  }

  save(event: Event) {
    event.preventDefault();
    const entries = Object.entries(this.fields()).filter(
      ([, v]) => v !== '' && Number(v) > 0,
    );
    if (entries.length === 0) return;

    forkJoin(
      entries.map(([type, value]) =>
        this.api.add({ type, value: Number(value), measured_at: this.measuredAt() }),
      ),
    ).subscribe(() => {
      this.fields.set({});
      this.saved.set(true);
      setTimeout(() => this.saved.set(false), 2000);
      this.latest.reload();
      this.series.reload();
    });
  }

  remove(id: string) {
    this.api.remove(id).subscribe(() => {
      this.series.reload();
      this.latest.reload();
    });
  }
}
