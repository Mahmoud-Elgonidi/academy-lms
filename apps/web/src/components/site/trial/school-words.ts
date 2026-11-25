/*
 * The words of a school or a university that a page for adults (an academy
 * that trains institutions and professionals) never says by default in its
 * trial form (site-builder.md, 9.2): a lesson, a class, a subject, a
 * curriculum, homework, a teacher, a student, a lecture, a school or a
 * university, and a child or a guardian. Tests hold the defaults to it.
 */

const LETTERS = '\\u0621-\\u064A\\u0671-\\u06D3';
const STEMS = [
  'حصة', 'حصتك', 'حصتي', 'حصص', 'درس', 'دروس', 'مادة', 'مادتك', 'مواد', 'موادك', 'مقرر', 'مقررات', 'منهج', 'مناهج', 'منهاج',
  'طالب', 'طالبة', 'طلاب', 'طلبة', 'تلميذ', 'تلاميذ', 'متعلم', 'متعلمة', 'متعلمون', 'متعلمين', 'معلم', 'معلمة', 'معلمك', 'معلمون', 'معلمين',
  'مدرسة', 'مدرستك', 'مدارس', 'جامعة', 'جامعات', 'محاضرة', 'محاضرات', 'واجب', 'واجبات', 'واجبك', 'امتحان', 'امتحانات',
  'ولي الأمر', 'ولي أمر', 'أولياء', 'طفل', 'طفلك', 'طفلة', 'أطفال', 'ابن', 'ابنك', 'ابنتك', 'ابني', 'ابنتي',
];

/** Arabic, after the short vowels and the shadda are taken out: the stems with و ف ب and ال ل لل ك before them, and الصف («وصف» is a description). */
export const SCHOOL_AR = new RegExp(`(?<![${LETTERS}])(?:[وفب]?(?:لل|ال|ل|ك)?(?:${STEMS.join('|')})|[وفب]?(?:لل|ال)صف)(?![${LETTERS}])`, 'gu');

export const SCHOOL_EN = /\b(?:lessons?|teachers?|tutors?|students?|pupils?|learners?|class(?:es|room)?|schools?|universit(?:y|ies)|college|lectures?|homework|curricul(?:um|a)|subjects?|child|children|kids?|parents?|guardians?|sons?|daughters?)\b/gi;

/** The school words in a text, as they are written there (Arabic without its marks). */
export function refusedSchool(text: string): string[] {
  const plain = text.replace(/[ً-ْٰ]/g, '');
  return [...(plain.match(SCHOOL_AR) ?? []), ...(text.match(SCHOOL_EN) ?? [])];
}
