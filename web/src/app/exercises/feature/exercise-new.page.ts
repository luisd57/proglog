import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { MuscleDiagramComponent } from '../../shared/ui/muscle-diagram/muscle-diagram.component';
import { muscleHighlights } from '../../shared/utils/muscle-map';
import { EQUIPMENT_NAMES, MUSCLE_NAMES } from '../../shared/utils/exercise.model';
import { extractErrorMessage } from '../../shared/utils/api-response.model';
import { ExercisesService } from '../../shared/data-access/exercises.service';

@Component({
  selector: 'app-exercise-new-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [MuscleDiagramComponent, RouterLink],
  template: `
    <a routerLink="/exercises" class="back">← Exercises</a>
    <header class="page-header">
      <h1>New exercise</h1>
    </header>

    <div class="detail-grid">
      <form (submit)="save($event)">
        <label>
          Name
          <input
            type="text"
            required
            [value]="name()"
            (input)="name.set($any($event.target).value)"
          />
        </label>

        <fieldset>
          <legend>Primary muscles</legend>
          <div class="muscle-grid">
            @for (m of muscles; track m) {
              <label class="checkbox">
                <input
                  type="checkbox"
                  [checked]="primary().includes(m)"
                  (change)="toggle(primary, m)"
                />
                {{ m }}
              </label>
            }
          </div>
        </fieldset>

        <fieldset>
          <legend>Secondary muscles</legend>
          <div class="muscle-grid">
            @for (m of muscles; track m) {
              <label class="checkbox">
                <input
                  type="checkbox"
                  [checked]="secondary().includes(m)"
                  (change)="toggle(secondary, m)"
                />
                {{ m }}
              </label>
            }
          </div>
        </fieldset>

        <label>
          Equipment
          <select
            [value]="equipment()"
            (change)="equipment.set($any($event.target).value)"
          >
            <option value="">—</option>
            @for (e of equipments; track e) {
              <option [value]="e">{{ e }}</option>
            }
          </select>
        </label>

        <label>
          Notes / instructions
          <textarea
            rows="4"
            [value]="instructions()"
            (input)="instructions.set($any($event.target).value)"
          ></textarea>
        </label>

        @if (error()) {
          <p class="error">{{ error() }}</p>
        }
        <button class="button" type="submit" [disabled]="!valid()">
          Create exercise
        </button>
      </form>

      <aside>
        <app-muscle-diagram [highlights]="preview()" />
      </aside>
    </div>
  `,
  styles: `
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr minmax(14rem, 20rem);
      gap: 2rem;
    }
    @media (max-width: 40rem) {
      .detail-grid {
        grid-template-columns: 1fr;
      }
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      max-width: 36rem;
    }
    .muscle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
      gap: 0.25rem;
    }
    .checkbox {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.9rem;
    }
    .back {
      display: inline-block;
      margin-bottom: 0.5rem;
    }
  `,
})
export class ExerciseNewPage {
  private readonly api = inject(ExercisesService);
  private readonly router = inject(Router);

  readonly name = signal('');
  readonly primary = signal<string[]>([]);
  readonly secondary = signal<string[]>([]);
  readonly equipment = signal('');
  readonly instructions = signal('');
  readonly error = signal('');

  protected readonly muscles = MUSCLE_NAMES;
  protected readonly equipments = EQUIPMENT_NAMES;

  readonly valid = computed(
    () => this.name().trim().length > 0 && this.primary().length > 0,
  );

  readonly preview = computed(() =>
    muscleHighlights(this.primary(), this.secondary()),
  );

  toggle(list: typeof this.primary, muscle: string) {
    list.update((current) =>
      current.includes(muscle)
        ? current.filter((m) => m !== muscle)
        : [...current, muscle],
    );
  }

  save(event: Event) {
    event.preventDefault();
    if (!this.valid()) return;
    this.api
      .create({
        name: this.name().trim(),
        primary_muscles: this.primary(),
        secondary_muscles: this.secondary(),
        equipment: this.equipment() || undefined,
        instructions: this.instructions().trim() || undefined,
      })
      .subscribe({
        next: (created) => void this.router.navigate(['/exercises', created.id]),
        error: (err: unknown) =>
          this.error.set(extractErrorMessage(err, 'Could not create the exercise.')),
      });
  }
}
