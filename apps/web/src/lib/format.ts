/**
 * Words for dates and zones the API gives as plain values (a calendar day
 * "2026-10-11", an IANA zone). Calendar days are written in UTC so a day stays
 * the same day; instants always come from the API's LocalTime labels.
 */

/** "الأحد 11 أكتوبر" for a day on someone's calendar ("YYYY-MM-DD"). */
export function dayLabel(day: string, locale: string, options: Intl.DateTimeFormatOptions = { weekday: 'long', day: 'numeric', month: 'long' }): string {
  const date = new Date(`${day.slice(0, 10)}T00:00:00Z`);
  if (Number.isNaN(date.getTime())) return day;
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB', { ...options, timeZone: 'UTC' }).format(date);
}

/** "أكتوبر 2026" for a month ("YYYY-MM"). */
export function monthLabel(month: string, locale: string): string {
  return dayLabel(`${month}-01`, locale, { month: 'long', year: 'numeric' });
}

/** A calendar day moved by some days ("YYYY-MM-DD"). */
export function addDays(day: string, days: number): string {
  const date = new Date(`${day.slice(0, 10)}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

/** A month moved by some months ("YYYY-MM"). */
export function addMonths(month: string, months: number): string {
  const date = new Date(`${month}-01T00:00:00Z`);
  date.setUTCMonth(date.getUTCMonth() + months);
  return date.toISOString().slice(0, 7);
}

/** Hours from minutes, as people say them: "1.5", "6". */
export function hoursOf(minutes: number): string {
  const hours = minutes / 60;
  return Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
}

/** A zone as people know it: its city ("القاهرة", "Cairo"). */
export function zoneName(zone: string | null | undefined, locale: string): string {
  if (!zone) return '';
  const city = zone.split('/').pop()!.replace(/_/g, ' ');
  return locale === 'ar' ? (ARABIC_CITIES[city] ?? city) : city;
}

const ARABIC_CITIES: Record<string, string> = {
  Riyadh: 'الرياض', Cairo: 'القاهرة', Dubai: 'دبي', Kuwait: 'الكويت', Qatar: 'الدوحة', Bahrain: 'البحرين', Muscat: 'مسقط',
  Amman: 'عمّان', Baghdad: 'بغداد', Beirut: 'بيروت', Damascus: 'دمشق', Jerusalem: 'القدس', Gaza: 'غزة', Khartoum: 'الخرطوم',
  Tripoli: 'طرابلس', Tunis: 'تونس', Algiers: 'الجزائر', Casablanca: 'الدار البيضاء', Istanbul: 'إسطنبول', London: 'لندن',
  Paris: 'باريس', Berlin: 'برلين', Amsterdam: 'أمستردام', Stockholm: 'ستوكهولم', Madrid: 'مدريد', Rome: 'روما',
  'New York': 'نيويورك', Chicago: 'شيكاغو', Denver: 'دنفر', 'Los Angeles': 'لوس أنجلوس', Toronto: 'تورونتو',
  Vancouver: 'فانكوفر', Sydney: 'سيدني', 'Kuala Lumpur': 'كوالالمبور', Jakarta: 'جاكرتا', Karachi: 'كراتشي', UTC: 'UTC',
};

/** The days of the week in the order people here count them: from Saturday in Arabic, from Monday in English (ISO numbers). */
export function weekOrder(locale: string): number[] {
  return locale === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
}

/** The name of an ISO weekday (1 Monday … 7 Sunday). */
export function weekdayName(weekday: number, locale: string, width: 'long' | 'short' = 'long'): string {
  // 2024-01-01 was a Monday.
  return dayLabel(addDays('2024-01-01', weekday - 1), locale, { weekday: width });
}
