import { ChangeDetectionStrategy, Component, OnDestroy, signal } from '@angular/core';

@Component({
  selector: 'app-rest-timer',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (running()) {
      <div class="timer" [class.done]="remaining() === 0">
        <span class="time">{{ display() }}</span>
        <button (click)="stop()">skip</button>
      </div>
    }
  `,
  styles: `
    .timer {
      position: fixed;
      bottom: 1rem;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: var(--surface);
      border: 1px solid var(--accent);
      border-radius: 999px;
      padding: 0.5rem 1.1rem;
      z-index: 50;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.5);
    }
    .time {
      font-variant-numeric: tabular-nums;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--accent);
    }
    button {
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
      font: inherit;
    }
  `,
})
export class RestTimerComponent implements OnDestroy {
  readonly remaining = signal(0);
  readonly running = signal(false);

  private interval: ReturnType<typeof setInterval> | null = null;

  display(): string {
    const total = this.remaining();
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  }

  start(seconds: number) {
    this.clear();
    this.remaining.set(seconds);
    this.running.set(true);
    this.interval = setInterval(() => {
      const next = this.remaining() - 1;
      this.remaining.set(Math.max(0, next));
      if (next <= 0) {
        this.beep();
        this.finish();
      }
    }, 1000);
  }

  stop() {
    this.finish();
  }

  ngOnDestroy() {
    this.clear();
  }

  private finish() {
    this.clear();
    this.remaining.set(0);
    this.running.set(false);
  }

  private clear() {
    if (this.interval !== null) {
      clearInterval(this.interval);
      this.interval = null;
    }
  }

  private beep() {
    try {
      const Ctx =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
          .webkitAudioContext;
      if (!Ctx) return;
      const ctx = new Ctx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
      osc.start();
      osc.stop(ctx.currentTime + 0.6);
      osc.onended = () => void ctx.close();
    } catch {
      // no audio available (e.g. tests) — timer still works silently
    }
  }
}
