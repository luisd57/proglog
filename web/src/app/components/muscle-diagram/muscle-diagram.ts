import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { anteriorData, posteriorData } from './body-data';
import { RegionHighlights } from './muscle-map';

@Component({
  selector: 'app-muscle-diagram',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="bodies">
      @for (view of views; track view.label) {
        <svg viewBox="0 0 100 200" [attr.aria-label]="view.label">
          @for (region of view.regions; track region.muscle) {
            @for (points of region.svgPoints; track points) {
              <polygon
                [attr.points]="points"
                [attr.data-muscle]="region.muscle"
                class="region"
                [class.primary]="highlights()[region.muscle] === 'primary'"
                [class.secondary]="highlights()[region.muscle] === 'secondary'"
              />
            }
          }
        </svg>
      }
    </div>
  `,
  styles: `
    .bodies {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
    }
    svg {
      flex: 1;
      max-width: 9rem;
    }
    .region {
      fill: var(--muscle-base, #3f3f46);
      stroke: var(--muscle-stroke, #18181b);
      stroke-width: 0.25;
    }
    .region.primary {
      fill: var(--muscle-primary, #ef4444);
    }
    .region.secondary {
      fill: var(--muscle-secondary, #f9a8a8);
    }
  `,
})
export class MuscleDiagram {
  highlights = input<RegionHighlights>({});

  protected readonly views = [
    { label: 'front', regions: anteriorData },
    { label: 'back', regions: posteriorData },
  ];
}
