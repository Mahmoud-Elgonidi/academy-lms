import { describe, expect, it } from 'vitest';
import { transferGroups, transferText } from './transfer';
import { STAGES } from './types';

// Obviously made-up accounts: never a real number in a test.
const GULF = 'Bank: Example Bank\nIBAN: SA00 0000 0000 0000 0000 0000';
const EGYPT = 'InstaPay: academy@instapay\nVodafone Cash: 01000000000';

const invoice = (number: string, outstanding_minor: number, transfer_details: string | null) => ({ number, outstanding_minor, transfer_details });

describe('where to transfer for an invoice', () => {
  it('gives the academy\'s words, line breaks kept, while something is left to pay', () => {
    expect(transferText(invoice('NR-1', 40000, GULF), 'classic')).toBe(GULF);
    expect(transferText(invoice('NR-1', 40000, `  ${EGYPT}\n `), 'classic')).toBe(EGYPT);
  });

  it('gives nothing for a paid invoice, an empty text, or none written', () => {
    expect(transferText(invoice('NR-1', 0, GULF), 'classic')).toBeNull();
    expect(transferText(invoice('NR-1', 40000, '   '), 'classic')).toBeNull();
    expect(transferText(invoice('NR-1', 40000, null), 'classic')).toBeNull();
  });

  it('shows it only in the classic look: a student\'s own look has no money', () => {
    expect(STAGES.filter((stage) => transferText(invoice('NR-1', 40000, GULF), stage) !== null)).toEqual(['classic']);
    expect(transferGroups([invoice('NR-1', 40000, GULF)], 'kids')).toEqual([]);
  });

  it('says each place once, with the invoices that go to it, in their order', () => {
    const owed = [invoice('NR-1', 40000, GULF), invoice('NR-2', 0, EGYPT), invoice('NR-3', 22000, EGYPT), invoice('NR-4', 1000, GULF), invoice('NR-5', 500, null)];
    expect(transferGroups(owed, 'classic')).toEqual([
      { text: GULF, numbers: ['NR-1', 'NR-4'] },
      { text: EGYPT, numbers: ['NR-3'] },
    ]);
    expect(transferGroups([invoice('NR-1', 40000, GULF), invoice('NR-4', 1000, `${GULF} `)], 'classic')).toEqual([{ text: GULF, numbers: ['NR-1', 'NR-4'] }]);
  });

  it('keeps markup as plain text, for React to escape', () => {
    const typed = 'IBAN <b>SA00</b> & "quotes"';
    expect(transferText(invoice('NR-1', 100, typed), 'classic')).toBe(typed);
  });
});
