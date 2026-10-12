import { seesMoney } from './rules';
import type { Invoice, Stage } from './types';

type Payable = Pick<Invoice, 'number' | 'outstanding_minor' | 'transfer_details'>;

/**
 * Where to send the money by hand for one invoice, as the academy wrote it
 * (the API picks the text for the payer's price region): only in the classic
 * look and only while something is left to pay. Shown as text, line breaks kept.
 */
export function transferText(invoice: Payable, stage: Stage): string | null {
  if (!seesMoney(stage) || invoice.outstanding_minor <= 0) return null;
  const text = invoice.transfer_details?.trim();
  return text ? text : null;
}

/**
 * The transfer details over the invoices still to pay, each text once (most
 * invoices share their region's), with the invoices it is for, in the order
 * the invoices come.
 */
export function transferGroups(invoices: readonly Payable[], stage: Stage): { text: string; numbers: string[] }[] {
  const groups: { text: string; numbers: string[] }[] = [];
  for (const invoice of invoices) {
    const text = transferText(invoice, stage);
    if (text === null) continue;
    const group = groups.find((g) => g.text === text);
    if (group) group.numbers.push(invoice.number);
    else groups.push({ text, numbers: [invoice.number] });
  }
  return groups;
}
