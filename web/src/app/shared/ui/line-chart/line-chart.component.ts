import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  OnDestroy,
  effect,
  input,
  viewChild,
} from '@angular/core';
import { Chart } from 'chart.js/auto';

export interface ChartPoint {
  label: string;
  value: number;
}

@Component({
  selector: 'app-line-chart',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <figure>
      <figcaption>{{ title() }}</figcaption>
      <div class="chart-box"><canvas #canvas></canvas></div>
    </figure>
  `,
  styles: `
    figure { margin: 0 0 1.25rem; }
    figcaption {
      font-size: 0.85rem;
      color: var(--text-dim);
      margin-bottom: 0.4rem;
      font-weight: 600;
    }
    .chart-box {
      background: var(--surface);
      border-radius: 10px;
      padding: 0.75rem;
      height: 13rem;
    }
  `,
})
export class LineChartComponent implements OnDestroy {
  readonly title = input.required<string>();
  readonly points = input.required<ChartPoint[]>();
  readonly unit = input<string>('');

  private readonly canvas =
    viewChild.required<ElementRef<HTMLCanvasElement>>('canvas');

  private chart: Chart | null = null;

  constructor() {
    effect(() => {
      const points = this.points();
      const canvas = this.canvas().nativeElement;
      this.chart?.destroy();
      this.chart = null;
      try {
        this.chart = new Chart(canvas, {
          type: 'line',
          data: {
            labels: points.map((p) => p.label),
            datasets: [
              {
                data: points.map((p) => p.value),
                borderColor: '#e8543f',
                backgroundColor: 'rgba(232, 84, 63, 0.15)',
                fill: true,
                tension: 0.25,
                pointRadius: 3,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) => `${ctx.parsed.y} ${this.unit()}`.trim(),
                },
              },
            },
            scales: {
              x: { ticks: { color: '#9d9da8', maxTicksLimit: 6 }, grid: { display: false } },
              y: { ticks: { color: '#9d9da8' }, grid: { color: '#26262e' } },
            },
          },
        });
      } catch {
        // canvas not available (jsdom) - chart is purely visual
      }
    });
  }

  ngOnDestroy() {
    this.chart?.destroy();
  }
}
