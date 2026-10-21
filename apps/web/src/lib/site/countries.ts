/** The countries visitors come from most, first. */
const COUNTRIES = ['SA', 'EG', 'AE', 'KW', 'QA', 'BH', 'OM', 'JO', 'MA', 'DZ', 'TN', 'IQ', 'LB', 'LY', 'SD', 'PS', 'YE', 'TR', 'MY', 'ID', 'PK', 'GB', 'US', 'CA', 'DE', 'FR', 'AU'];

/**
 * Countries by name in the page's language, made on the server (the browser's
 * own names may differ, and the form must read the same before and after its
 * script runs). Any extra codes (a price region's) come after the usual ones.
 */
export function countriesIn(locale: string, extra: string[] = []): { code: string; name: string }[] {
  const names = new Intl.DisplayNames([locale], { type: 'region' });
  const codes = [...COUNTRIES, ...extra.filter((code) => /^[A-Z]{2}$/.test(code) && !COUNTRIES.includes(code))];
  return codes.map((code) => ({ code, name: names.of(code) ?? code }));
}
