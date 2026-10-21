import { describe, expect, it } from 'vitest';
import { sequence } from './sync';

describe('which answer about the mushaf is shown', () => {
  it('is the newest asked for: an older read answering late is left out', () => {
    const seq = sequence();
    const first = seq.read();
    const second = seq.read();
    expect(seq.accept(second)).toBe(true);
    expect(seq.accept(first)).toBe(false);
  });

  it('waits for the last of the teacher’s marks: an earlier mark’s answer would take a later one off the screen', () => {
    const seq = sequence();
    const a = seq.write();
    const b = seq.write();
    seq.written();
    expect(seq.accept(a)).toBe(false);
    seq.written();
    expect(seq.accept(b)).toBe(true);
    expect(seq.busy()).toBe(false);
  });

  it('leaves out a read that started before a mark still on its way', () => {
    const seq = sequence();
    const read = seq.read();
    const mark = seq.write();
    expect(seq.accept(read)).toBe(false);
    seq.written();
    expect(seq.accept(mark)).toBe(true);
  });

  it('reads once the marks on their way are done, when a signal asked meanwhile', () => {
    const seq = sequence();
    seq.write();
    seq.write();
    expect(seq.busy()).toBe(true);
    seq.readLater();
    expect(seq.written()).toBe(false);
    expect(seq.written()).toBe(true);
    // Asked for once: not again.
    seq.write();
    expect(seq.written()).toBe(false);
  });

  it('shows a read after the marks are done', () => {
    const seq = sequence();
    const mark = seq.write();
    seq.written();
    expect(seq.accept(mark)).toBe(true);
    const read = seq.read();
    expect(seq.accept(read)).toBe(true);
  });
});
