/**
 * Which answer about the shared mushaf the screen shows, when several are on
 * their way (a page read again for a signal while the teacher marks words, a
 * room message and the live signal for the same change): each request takes
 * a number as it starts; an answer is shown unless something asked after it
 * is already on screen, or a write asked after it is still on its way (that
 * write's answer will say more, and its mark is already drawn). A read asked
 * for while writes are on their way waits for them (`readLater`), so another
 * device's change is not missed.
 */
export type Sequence = {
  /** A read starts: its number. */
  read(): number;
  /** A write starts: its number. */
  write(): number;
  /** A write ended, answered or not. Whether a read was asked for meanwhile (now is the time for it). */
  written(): boolean;
  /** Whether the answer to request n may be shown (and then it is the one on screen). */
  accept(n: number): boolean;
  /** Writes on their way. */
  busy(): boolean;
  /** A read asked for while writes are on their way: done once they end. */
  readLater(): void;
};

export function sequence(): Sequence {
  let started = 0;
  let shown = 0;
  let writing = 0;
  let lastWrite = 0;
  let wanted = false;
  return {
    read: () => ++started,
    write: () => {
      writing += 1;
      lastWrite = ++started;
      return lastWrite;
    },
    written: () => {
      writing = Math.max(0, writing - 1);
      if (writing > 0 || !wanted) return false;
      wanted = false;
      return true;
    },
    accept: (n) => {
      if (n < shown) return false;
      if (n < lastWrite && writing > 0) return false;
      shown = n;
      return true;
    },
    busy: () => writing > 0,
    readLater: () => {
      wanted = true;
    },
  };
}
