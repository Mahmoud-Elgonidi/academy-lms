import type { ConsentText } from './types';

/*
 * «إضافة طفل» (docs/architecture/02, section 27, J5a): a guardian adds their
 * own child (POST family/children) and goes straight into the start flow.
 * What the form sends, the consent wording with the name being typed, and
 * the API's refusals put by the field they are about.
 */

export type ChildForm = {
  name: string;
  /** How the birth is given: the day, or only the year. */
  birthBy: 'date' | 'year';
  birthDate: string;
  birthYear: string;
  gender: '' | 'female' | 'male';
  agree: boolean;
};

export const EMPTY_CHILD: ChildForm = { name: '', birthBy: 'year', birthDate: '', birthYear: '', gender: '', agree: false };

export type ChildBody = {
  name: string;
  birth_date: string | null;
  birth_year: number | null;
  gender: 'female' | 'male' | null;
  agree: boolean;
  version: number;
};

/** The request's body: the birth the way it was given (only one of the two), the name tidied. */
export function childBody(form: ChildForm, version: number): ChildBody {
  const year = Number.parseInt(form.birthYear, 10);
  return {
    name: form.name.trim().replace(/\s+/g, ' '),
    birth_date: form.birthBy === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(form.birthDate) ? form.birthDate : null,
    birth_year: form.birthBy === 'year' && Number.isInteger(year) ? year : null,
    gender: form.gender === '' ? null : form.gender,
    agree: form.agree,
    version,
  };
}

/** The fields the form checks before sending (the API checks them all again). */
export type ChildField = 'name' | 'birth' | 'agree';

export function missing(form: ChildForm): ChildField[] {
  const out: ChildField[] = [];
  if (form.name.trim().length < 2) out.push('name');
  if (form.birthBy === 'date' ? !/^\d{4}-\d{2}-\d{2}$/.test(form.birthDate) : !/^\d{4}$/.test(form.birthYear)) out.push('birth');
  if (!form.agree) out.push('agree');
  return out;
}

/** The years to choose from: this year back to the oldest a child added here may be (the API refuses older than 25). */
export function birthYears(thisYear: number, span = 25): number[] {
  return Array.from({ length: span }, (_, n) => thisYear - n);
}

/** The wording with the child's name where {child} stands: the name typed, else "your child". */
export function fillChild(text: string, name: string, fallback: string): string {
  const who = name.trim().replace(/\s+/g, ' ') || fallback;
  return text.split('{child}').join(who);
}

export function filledWording(text: ConsentText, name: string, fallback: string): ConsentText {
  const fill = (s: string) => fillChild(s, name, fallback);
  return {
    version: text.version,
    title: fill(text.title),
    intro: fill(text.intro),
    sections: text.sections.map((p) => ({ title: fill(p.title), body: fill(p.body) })),
    agree: fill(text.agree),
  };
}

/**
 * The API's refusal by the field it is about: the name (the same child
 * already there, too many children), the birth (missing, not sensible), the
 * agreement; a newer consent wording (version) or anything else is said above
 * the form.
 */
export function refusalsOf(errors: Record<string, string[]>, title?: string): { fields: Partial<Record<ChildField, string>>; form: string | null; wordingChanged: boolean } {
  const fields: Partial<Record<ChildField, string>> = {};
  const first = (key: string) => errors[key]?.[0];
  if (first('name')) fields.name = first('name');
  const birth = first('birth_year') ?? first('birth_date');
  if (birth) fields.birth = birth;
  if (first('agree')) fields.agree = first('agree');
  const rest = Object.entries(errors).filter(([key]) => !['name', 'birth_year', 'birth_date', 'agree'].includes(key));
  const wordingChanged = Boolean(errors.version?.length);
  const form = rest[0]?.[1]?.[0] ?? (Object.keys(fields).length === 0 ? (title || null) : null);
  return { fields, form, wordingChanged };
}
