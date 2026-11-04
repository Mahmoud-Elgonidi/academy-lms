import { describe, expect, it } from 'vitest';
import { cardExpiry, cardPays, cardSaved, cardsLine, cardsState, chargeLook, chargeUnderWay, checkoutBody, offersSaveCard, removalStopsRenewal, type Cards } from './cards';

const card = (id: string, over: Partial<Cards['cards'][number]> = {}): Cards['cards'][number] => ({
  id, brand: 'visa', brand_label: 'Visa', last4: '4242', exp_month: 4, exp_year: 2031, label: 'Visa ****4242', account: 'البوابة التجريبية', expires_soon: false, saved_at: null, ...over,
});

function cards(over: Partial<Cards> = {}): Cards {
  return {
    cards: [card('c1')],
    accounts: [{ id: 'a1', currency: 'SAR', auto_renew: true, card_id: 'c1' }],
    auto_renew: true,
    coming: [],
    mandate: { version: '2027-07-20', text: 'نص الموافقة' },
    ...over,
  };
}

describe('the saved card and automatic renewal', () => {
  it('offers saving the card only where the look allows it and the gateway keeps cards', () => {
    const offer = { offered: true, version: '2027-07-20', text: 'نص' };
    expect(offersSaveCard(offer, { saves_cards: true })).toBe(true);
    expect(offersSaveCard(offer, { saves_cards: false })).toBe(false);
    // An older API without the field: not offered.
    expect(offersSaveCard(offer, {})).toBe(false);
    expect(offersSaveCard({ ...offer, offered: false }, { saves_cards: true })).toBe(false);
    expect(offersSaveCard(null, { saves_cards: true })).toBe(false);
    expect(offersSaveCard(offer, undefined)).toBe(false);
  });

  it('sends the card to keep with the version read, and comes back to the portal when it does', () => {
    expect(checkoutBody('g1', 'portal', null)).toEqual({ account_id: 'g1', return: 'portal' });
    expect(checkoutBody('g1', 'portal', { version: 'v2' })).toEqual({ account_id: 'g1', return: 'portal', save_card: true, mandate_version: 'v2' });
    // The money page leaves the way back to the gateway's own page, unless a card is kept.
    expect(checkoutBody(null, undefined, null)).toEqual({});
    expect(checkoutBody(null, undefined, { version: 'v2' })).toEqual({ return: 'portal', save_card: true, mandate_version: 'v2' });
  });

  it('writes the expiry as on the card', () => {
    expect(cardExpiry({ exp_month: 4, exp_year: 2031 })).toBe('04/2031');
    expect(cardExpiry({ exp_month: 12, exp_year: 2034 })).toBe('12/2034');
  });

  it('says how the page opens and what the money page says of it', () => {
    expect(cardsState(cards())).toBe('on');
    expect(cardsState(cards({ auto_renew: false }))).toBe('off');
    expect(cardsState(cards({ cards: [], auto_renew: false }))).toBe('none');
    expect(cardsLine(cards())).toEqual({ state: 'on', card: 'Visa ****4242' });
    expect(cardsLine(cards({ cards: [], auto_renew: false }))).toEqual({ state: 'none', card: null });
  });

  it('reads a coming charge kindly: planned, tried once more, or under way', () => {
    expect(chargeLook('scheduled')).toEqual({ tone: 'info', key: 'scheduled' });
    expect(chargeLook('retry')).toEqual({ tone: 'attention', key: 'retry' });
    expect(chargeLook('charging')).toEqual({ tone: 'brand', key: 'charging' });
    expect(chargeLook('something_new')).toEqual({ tone: 'info', key: 'scheduled' });
    expect(chargeUnderWay(cards())).toBe(false);
    expect(chargeUnderWay(cards({ coming: [{ invoice_id: 'i', number: 'N', amount: '1', amount_minor: 1, currency: 'SAR', status: 'charging', at: null }] }))).toBe(true);
  });

  it('says removing the last card turns renewal off, and not otherwise', () => {
    expect(removalStopsRenewal(cards(), 'c1')).toBe(true);
    expect(removalStopsRenewal(cards({ accounts: [{ id: 'a1', currency: 'SAR', auto_renew: false, card_id: 'c1' }], auto_renew: false }), 'c1')).toBe(false);
    expect(removalStopsRenewal(cards({ cards: [card('c1'), card('c2', { last4: '1111' })] }), 'c1')).toBe(false);
  });

  it('reads whether the payment kept the card, however the answer types it', () => {
    expect(cardSaved({ card_saved: true })).toBe(true);
    expect(cardSaved({ card_saved: '1' })).toBe(true);
    expect(cardSaved({ card_saved: false })).toBe(false);
    expect(cardSaved({ card_saved: '' })).toBe(false);
    expect(cardSaved(null)).toBe(false);
  });

  it('lets the saved card pay a waiting invoice only while its charge is planned or under way', () => {
    const renewal = (status: string | null, on = true) => ({ automatic_renewal: { on, card: null, text: '', next_charge: status ? { at: null, amount: '1', amount_minor: 1, status } : null } });
    expect(cardPays(renewal('scheduled'))).toBe(true);
    expect(cardPays(renewal('charging'))).toBe(true);
    expect(cardPays(renewal('retry'))).toBe(false);
    expect(cardPays(renewal(null))).toBe(false);
    expect(cardPays(renewal('scheduled', false))).toBe(false);
    expect(cardPays({})).toBe(false);
  });
});
