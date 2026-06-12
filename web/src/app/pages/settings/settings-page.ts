import { ChangeDetectionStrategy, Component, effect, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { MeasurementsApi } from '../../services/measurements-api';

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
  private readonly api = inject(MeasurementsApi);

  readonly sex = signal('');
  readonly birthDate = signal('');
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
        this.birthDate.set(p.birthDate ? p.birthDate.slice(0, 10) : '');
        this.restSeconds.set(String(p.defaultRestSeconds));
      }
    });
  }

  save(event: Event) {
    event.preventDefault();
    this.api
      .updateProfile({
        sex: (this.sex() || null) as 'male' | 'female' | null,
        birthDate: this.birthDate() || null,
        defaultRestSeconds: Number(this.restSeconds()) || 120,
      })
      .subscribe(() => {
        this.saved.set(true);
        setTimeout(() => this.saved.set(false), 2000);
      });
  }
}
