import {
  ChangeDetectionStrategy,
  Component,
  OnDestroy,
  OnInit,
  computed,
  input,
  output,
  signal,
} from '@angular/core';
import { Subject, debounceTime } from 'rxjs';
import { ExerciseBest, isPr } from '../../shared/utils/e1rm';
import { SessionExercise, SetInput } from '../../shared/utils/session.model';

export interface SetRow {
  weight_kg: number;
  reps: number;
  is_warmup: boolean;
  notes: string;
  done: boolean;
}

@Component({
  selector: 'app-session-exercise-card',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <article class="card">
      <header>
        <h3>{{ data().exercise.name }}</h3>
        <button class="remove" (click)="removed.emit()" title="Remove exercise">✕</button>
      </header>

      <p class="hint">
        @if (data().target_sets) {
          <span>Target {{ data().target_sets }}×{{ data().target_reps ?? '?' }}</span>
        }
        @if (data().previous_sets.length) {
          <span>Last: {{ previousSummary() }}</span>
        } @else {
          <span>First time — no history</span>
        }
      </p>

      <div class="sets">
        @for (row of rows(); track $index) {
          <div class="set-row" [class.done]="row.done" [class.warmup]="row.is_warmup">
            <button
              class="warmup-toggle"
              (click)="toggleWarmup($index)"
              [title]="row.is_warmup ? 'Warm-up set' : 'Working set'"
            >
              {{ row.is_warmup ? 'W' : $index + 1 }}
            </button>
            <input
              type="number" inputmode="decimal" step="0.5" min="0" placeholder="kg"
              [value]="row.weight_kg || ''"
              (input)="updateRow($index, 'weight_kg', $any($event.target).value)"
            />
            <span class="x">×</span>
            <input
              type="number" inputmode="numeric" min="0" placeholder="reps"
              [value]="row.reps || ''"
              (input)="updateRow($index, 'reps', $any($event.target).value)"
            />
            @if (isRowPr(row)) {
              <span class="pr">PR</span>
            }
            <button class="done-btn" [class.checked]="row.done" (click)="markDone($index)">✓</button>
            <button class="remove" (click)="removeSet($index)">✕</button>
          </div>
        }
      </div>
      <button class="add-set" (click)="addSet()">+ Add set</button>

      <input
        class="notes"
        type="text"
        placeholder="Notes…"
        [value]="notes()"
        (input)="setNotes($any($event.target).value)"
      />
    </article>
  `,
  styles: `
    .card {
      background: var(--surface);
      border-radius: 10px;
      padding: 0.85rem 1rem;
      margin-bottom: 0.9rem;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    h3 { margin: 0; font-size: 1.05rem; }
    .hint {
      display: flex;
      gap: 1rem;
      color: var(--text-dim);
      font-size: 0.82rem;
      margin: 0.3rem 0 0.6rem;
    }
    .set-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.4rem;
    }
    .set-row.done input { opacity: 0.75; }
    .set-row input[type='number'] {
      width: 5rem;
      text-align: center;
      font-size: 1rem;
    }
    .x { color: var(--text-dim); }
    .warmup-toggle {
      width: 1.9rem;
      height: 1.9rem;
      border-radius: 50%;
      border: 1px solid var(--border);
      background: var(--surface-hover);
      color: var(--text-dim);
      cursor: pointer;
      font-weight: 600;
    }
    .set-row.warmup .warmup-toggle {
      color: #fbbf24;
      border-color: #fbbf24;
    }
    .pr {
      background: var(--accent);
      color: #fff;
      font-size: 0.7rem;
      font-weight: 800;
      padding: 0.1rem 0.4rem;
      border-radius: 4px;
    }
    .done-btn {
      margin-left: auto;
      width: 2.1rem;
      height: 2.1rem;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--surface-hover);
      color: var(--text-dim);
      cursor: pointer;
      font-size: 1rem;
    }
    .done-btn.checked {
      background: #14532d;
      border-color: #22c55e;
      color: #4ade80;
    }
    .remove {
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
    }
    .add-set {
      background: none;
      border: 1px dashed var(--border);
      color: var(--text-dim);
      border-radius: 8px;
      padding: 0.35rem 0.8rem;
      cursor: pointer;
      width: 100%;
      margin: 0.2rem 0 0.6rem;
    }
    .notes { width: 100%; font-size: 0.85rem; }
  `,
})
export class SessionExerciseCardComponent implements OnInit, OnDestroy {
  readonly data = input.required<SessionExercise>();
  readonly best = input<ExerciseBest | null>(null);

  readonly rest = output<number>();
  readonly removed = output<void>();
  readonly saveSets = output<SetInput[]>();
  readonly notesChange = output<string>();

  readonly rows = signal<SetRow[]>([]);
  readonly notes = signal('');

  readonly previousSummary = computed(() =>
    this.data()
      .previous_sets.filter((s) => !s.is_warmup)
      .map((s) => `${s.weight_kg}×${s.reps}`)
      .join(', '),
  );

  private readonly saveQueue = new Subject<SetInput[]>();
  private readonly saveSub = this.saveQueue
    .pipe(debounceTime(600))
    .subscribe((sets) => this.saveSets.emit(sets));

  private readonly notesQueue = new Subject<string>();
  private readonly notesSub = this.notesQueue
    .pipe(debounceTime(800))
    .subscribe((value) => this.notesChange.emit(value));

  private initialized = false;

  ngOnInit() {
    if (!this.initialized) {
      this.initialized = true;
      this.rows.set(
        this.data().sets.map((s) => ({
          weight_kg: s.weight_kg,
          reps: s.reps,
          is_warmup: s.is_warmup,
          notes: s.notes ?? '',
          done: true,
        })),
      );
      this.notes.set(this.data().notes ?? '');
    }
  }

  ngOnDestroy() {
    this.saveSub.unsubscribe();
    this.notesSub.unsubscribe();
  }

  addSet() {
    this.rows.update((rows) => {
      const previous = this.data().previous_sets;
      const template = previous[rows.length] ?? previous[previous.length - 1];
      const last = rows[rows.length - 1];
      const source = template ?? last;
      return [
        ...rows,
        {
          weight_kg: source?.weight_kg ?? 0,
          reps: source?.reps ?? 0,
          is_warmup: source?.is_warmup ?? false,
          notes: '',
          done: false,
        },
      ];
    });
  }

  removeSet(index: number) {
    this.rows.update((rows) => rows.filter((_, i) => i !== index));
    this.persist();
  }

  updateRow(index: number, field: 'weight_kg' | 'reps', value: string) {
    this.rows.update((rows) =>
      rows.map((row, i) =>
        i === index ? { ...row, [field]: value === '' ? 0 : Number(value) } : row,
      ),
    );
    this.persist();
  }

  toggleWarmup(index: number) {
    this.rows.update((rows) =>
      rows.map((row, i) =>
        i === index ? { ...row, is_warmup: !row.is_warmup } : row,
      ),
    );
    this.persist();
  }

  markDone(index: number) {
    this.rows.update((rows) =>
      rows.map((row, i) => (i === index ? { ...row, done: true } : row)),
    );
    this.saveSets.emit(this.toInputs());
    this.rest.emit(this.data().rest_seconds);
  }

  setNotes(value: string) {
    this.notes.set(value);
    this.notesQueue.next(value);
  }

  isRowPr(row: SetRow): boolean {
    if (row.is_warmup) return false;
    const best = this.best();
    if (!best) return false;
    return isPr(row.weight_kg, row.reps, best);
  }

  private toInputs(): SetInput[] {
    return this.rows()
      .filter((row) => row.weight_kg > 0 || row.reps > 0)
      .map((row) => ({
        weight_kg: row.weight_kg,
        reps: row.reps,
        is_warmup: row.is_warmup,
        notes: row.notes || undefined,
      }));
  }

  private persist() {
    this.saveQueue.next(this.toInputs());
  }
}
