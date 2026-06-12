import { TestBed } from '@angular/core/testing';
import { MuscleDiagram } from './muscle-diagram';

describe('MuscleDiagram', () => {
  function render(highlights: Record<string, string>) {
    const fixture = TestBed.createComponent(MuscleDiagram);
    fixture.componentRef.setInput('highlights', highlights);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  it('renders front and back body views', () => {
    const el = render({});
    expect(el.querySelectorAll('svg').length).toBe(2);
    expect(el.querySelectorAll('polygon[data-muscle="chest"]').length)
      .toBeGreaterThan(0);
    expect(el.querySelectorAll('polygon[data-muscle="gluteal"]').length)
      .toBeGreaterThan(0);
  });

  it('marks primary regions with the primary class', () => {
    const el = render({ chest: 'primary' });
    const chestPolys = el.querySelectorAll('polygon[data-muscle="chest"]');
    chestPolys.forEach((p) => expect(p.classList.contains('primary')).toBe(true));
  });

  it('marks secondary regions with the secondary class', () => {
    const el = render({ 'upper-back': 'secondary' });
    const polys = el.querySelectorAll('polygon[data-muscle="upper-back"]');
    expect(polys.length).toBeGreaterThan(0);
    polys.forEach((p) => {
      expect(p.classList.contains('secondary')).toBe(true);
      expect(p.classList.contains('primary')).toBe(false);
    });
  });

  it('leaves unhighlighted regions unmarked', () => {
    const el = render({ chest: 'primary' });
    const quads = el.querySelectorAll('polygon[data-muscle="quadriceps"]');
    quads.forEach((p) => {
      expect(p.classList.contains('primary')).toBe(false);
      expect(p.classList.contains('secondary')).toBe(false);
    });
  });
});
