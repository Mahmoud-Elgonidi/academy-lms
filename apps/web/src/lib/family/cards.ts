import type { LocalTime } from '@/lib/time';

/*
 * The payer's saved card and automatic renewal (docs/architecture/02,
 * section 27, J5b), as the portal reads GET family/cards and the balance's
 * automatic_renewal: what the card page and the pay forms work out from the
 * API's answer alone. A card is only ever its brand, last four and expiry;
 * times and sums are written as the API wrote them.
 */

/** A card the gateway keeps for the payer (never its number or reference). */
export type SavedCard = {
  id: string;
  brand: string;
  brand_label: string;
  last4: string;
  exp_month: number;
  exp_year: number;
  /** "Visa ****4242", as the API writes it everywhere. */
  label: string;
  /** The gateway account it is kept at, as payers know it. */
  account: string;
  /** It expires within a month, or has expired. */
  expires_soon: boolean;
  saved_at: LocalTime | null;
};

/** An automatic charge coming (scheduled), tried once more after a soft decline (retry), or under way (charging). */
export type ComingCharge = {
  invoice_id: string;
  number: string;
  amount: string;
  amount_minor: number;
  currency: string;
  status: 'scheduled' | 'retry' | 'charging' | string;
  at: LocalTime | null;
};

/** GET family/cards' data. */
export type Cards = {
  cards: SavedCard[];
  /** Per billing account (one per currency, usually one): renewal on, and the card it charges. */
  accounts: { id: string; currency: string; auto_renew: boolean; card_id: string | null }[];
  auto_renew: boolean;
  coming: ComingCharge[];
  mandate: { version: string; text: string };
};

/** The balance's automatic_renewal (the payer, in the classic look). */
export type AutomaticRenewal = {
  on: boolean;
  card: { id: string; brand: string; last4: string; exp_month: number; exp_year: number; label: string; expires_soon: boolean } | null;
  next_charge: { at: LocalTime | null; amount: string; amount_minor: number; status: string } | null;
  text: string;
};

/** Saving the card while paying (GET family/invoices/{id}: save_card). */
export type SaveCardOffer = { offered: boolean; version: string; text: string };

/** Where the card page lives (under the family's money). */
export const CARDS_PATH = '/family/money/cards';

/** Whether to offer "save my card" for this gateway account: the payer's look allows it and the gateway keeps cards. */
export function offersSaveCard(offer: SaveCardOffer | null | undefined, account: { saves_cards?: boolean } | null | undefined): boolean {
  return Boolean(offer?.offered && offer.version && account?.saves_cards);
}

/**
 * What POST family/invoices/{id}/checkout is sent: the gateway account, where
 * to come back, and the card to keep with the mandate's version the payer
 * read. Saving always comes back to the portal, so it can say the card was
 * kept.
 */
export function checkoutBody(account: string | null, back: 'portal' | undefined, save: { version: string } | null): {
  account_id?: string;
  return?: 'portal';
  save_card?: true;
  mandate_version?: string;
} {
  const to = back ?? (save ? 'portal' : undefined);
  return {
    ...(account ? { account_id: account } : {}),
    ...(to ? { return: to } : {}),
    ...(save ? { save_card: true as const, mandate_version: save.version } : {}),
  };
}

/** The expiry as printed on a card: 04/2031. */
export function cardExpiry(card: Pick<SavedCard, 'exp_month' | 'exp_year'>): string {
  return `${String(card.exp_month).padStart(2, '0')}/${card.exp_year}`;
}

/** How the card page opens: no card yet, a card with renewal on, or a card with it off. */
export type CardsState = 'none' | 'on' | 'off';

export function cardsState(cards: Pick<Cards, 'cards' | 'auto_renew'>): CardsState {
  if (cards.cards.length === 0) return 'none';
  return cards.auto_renew ? 'on' : 'off';
}

/** A coming charge's tone and words' key: due as planned, tried once more (said kindly), or under way (no longer stoppable). */
export function chargeLook(status: string): { tone: 'info' | 'attention' | 'brand'; key: 'scheduled' | 'retry' | 'charging' } {
  if (status === 'retry') return { tone: 'attention', key: 'retry' };
  if (status === 'charging') return { tone: 'brand', key: 'charging' };
  return { tone: 'info', key: 'scheduled' };
}

/**
 * What removing a card does, for its confirmation: whether renewal goes off
 * with it (it was on, and no other card serves an account it is on for).
 */
export function removalStopsRenewal(cards: Pick<Cards, 'cards' | 'accounts'>, cardId: string): boolean {
  return cards.accounts.some((a) => a.auto_renew && a.card_id === cardId) && cards.cards.length <= 1;
}

/** Whether a charge under way would still go through after turning renewal off (said in the confirmation). */
export function chargeUnderWay(cards: Pick<Cards, 'coming'>): boolean {
  return cards.coming.some((c) => c.status === 'charging');
}

/** The cards page's short status for the money page's entry to it. */
export function cardsLine(cards: Pick<Cards, 'cards' | 'auto_renew'>): { state: CardsState; card: string | null } {
  const state = cardsState(cards);
  return { state, card: cards.cards[0]?.label ?? null };
}

/** Whether the payment just made kept the card (checkout.card_saved; the published API types it loosely). */
export function cardSaved(checkout: { card_saved?: unknown } | null | undefined): boolean {
  const v = checkout?.card_saved;
  return v === true || v === 1 || v === '1' || v === 'true';
}

/** Whether the saved card pays a course's waiting invoice (its charge scheduled or under way; a retry asks the payer too). */
export function cardPays(b: { automatic_renewal?: AutomaticRenewal | null }): boolean {
  const status = b.automatic_renewal?.on ? b.automatic_renewal.next_charge?.status : undefined;
  return status === 'scheduled' || status === 'charging';
}
