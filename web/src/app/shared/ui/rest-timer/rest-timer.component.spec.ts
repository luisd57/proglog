import { TestBed } from '@angular/core/testing';
import { RestTimerComponent } from './rest-timer.component';

describe('RestTimerComponent', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  function create() {
    const fixture = TestBed.createComponent(RestTimerComponent);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('counts down once started', () => {
    const timer = create();
    timer.start(90);
    expect(timer.remaining()).toBe(90);
    expect(timer.running()).toBe(true);

    vi.advanceTimersByTime(3000);
    expect(timer.remaining()).toBe(87);
  });

  it('stops at zero', () => {
    const timer = create();
    timer.start(2);
    vi.advanceTimersByTime(5000);
    expect(timer.remaining()).toBe(0);
    expect(timer.running()).toBe(false);
  });

  it('restarting replaces the countdown', () => {
    const timer = create();
    timer.start(60);
    vi.advanceTimersByTime(10_000);
    timer.start(90);
    expect(timer.remaining()).toBe(90);
    vi.advanceTimersByTime(1000);
    expect(timer.remaining()).toBe(89);
  });

  it('can be dismissed', () => {
    const timer = create();
    timer.start(60);
    timer.stop();
    expect(timer.running()).toBe(false);
    expect(timer.remaining()).toBe(0);
  });
});
