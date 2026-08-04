import { ChangeDetectionStrategy, Component, effect, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { MeasurementsService } from '../../shared/data-access/measurements.service';

@Component({
  selector: 'app-settings-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="page-header">
      <h1>Settings</h1>
    </header>

    <form (submit)="save($event)">
      <label>
        Sex (for strength standards)
        <select [value]="sex()" (change)="sex.set($any($event.target).value)">
          <option value="">—</option>
          <option value="male">male</option>
          <option value="female">female</option>
        </select>
      </label>

      <label>
        Birth date
        <input
          type="date"
          [value]="birthDate()"
          (change)="birthDate.set($any($event.target).value)"
        />
      </label>

      <label>
        Height (cm)
        <input
          type="number" min="0" step="0.5" inputmode="decimal"
          placeholder="for body-fat estimate"
          [value]="heightCm()"
          (input)="heightCm.set($any($event.target).value)"
        />
      </label>

      <label>
        Default rest between sets (seconds)
        <input
          type="number" min="0" step="15"
          [value]="restSeconds()"
          (input)="restSeconds.set($any($event.target).value)"
        />
      </label>

      <button class="button" type="submit">Save</button>
      @if (saved()) {
        <span class="count">Saved.</span>
      }
    </form>
  `,
  styles: `
    form {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      max-width: 24rem;
    }
  `,
})
export class SettingsPage {
  private readonly api = inject(MeasurementsService);

  readonly sex = signal('');
  readonly birthDate = signal('');
  readonly heightCm = signal('');
  readonly restSeconds = signal('120');
  readonly saved = signal(false);

  private readonly profile = rxResource({
    stream: () => this.api.profile(),
  });

  private populated = false;

  constructor() {
    effect(() => {
      const p = this.profile.value();
      if (p && !this.populated) {
        this.populated = true;
        this.sex.set(p.sex ?? '');
        this.birthDate.set(p.birth_date ? p.birth_date.slice(0, 10) : '');
        this.heightCm.set(p.height_cm != null ? String(p.height_cm) : '');
        this.restSeconds.set(String(p.default_rest_seconds));
      }
    });
  }

  save(event: Event) {
    event.preventDefault();
    this.api
      .updateProfile({
        sex: (this.sex() || null) as 'male' | 'female' | null,
        birth_date: this.birthDate() || null,
        height_cm: this.heightCm() ? Number(this.heightCm()) : null,
        default_rest_seconds: Number(this.restSeconds()) || 120,
      })
      .subscribe(() => {
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 2000);
      });
  }
}
