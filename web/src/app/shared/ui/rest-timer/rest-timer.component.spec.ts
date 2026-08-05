import { ComponentFixture, TestBed } from '@angular/core/testing';
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
    return fixture;
  }

  let nonce = 0;

  function rest(fixture: ComponentFixture<RestTimerComponent>, seconds: number) {
    fixture.componentRef.setInput('request', { seconds, nonce: nonce++ });
    fixture.detectChanges();
  }

  it('counts down once started', () => {
    const fixture = create();
    rest(fixture, 90);
    expect(fixture.componentInstance.remaining()).toBe(90);
    expect(fixture.componentInstance.running()).toBe(true);

    vi.advanceTimersByTime(3000);
    expect(fixture.componentInstance.remaining()).toBe(87);
  });

  it('stops at zero', () => {
    const fixture = create();
    rest(fixture, 2);
    vi.advanceTimersByTime(5000);
    expect(fixture.componentInstance.remaining()).toBe(0);
    expect(fixture.componentInstance.running()).toBe(false);
  });

  it('restarting replaces the countdown', () => {
    const fixture = create();
    rest(fixture, 60);
    vi.advanceTimersByTime(10_000);
    rest(fixture, 90);
    expect(fixture.componentInstance.remaining()).toBe(90);
    vi.advanceTimersByTime(1000);
    expect(fixture.componentInstance.remaining()).toBe(89);
  });

  it('restarts for a repeated duration', () => {
    const fixture = create();
    rest(fixture, 60);
    vi.advanceTimersByTime(10_000);
    expect(fixture.componentInstance.remaining()).toBe(50);

    rest(fixture, 60);
    expect(fixture.componentInstance.remaining()).toBe(60);
  });

  it('can be dismissed', () => {
    const fixture = create();
    rest(fixture, 60);
    fixture.componentInstance.stop();
    expect(fixture.componentInstance.running()).toBe(false);
    expect(fixture.componentInstance.remaining()).toBe(0);
  });
});
