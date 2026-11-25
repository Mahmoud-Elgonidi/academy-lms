/*
 * The words a person never reads in an academy for adults (companies and
 * offices that train professionals; docs/architecture/02, section 29): a
 * school's or a university's (a lesson, a class, a subject, homework, a
 * teacher, a student, a lecture, an exam) and a family's (a child, a son, a
 * guardian, the family, the parents). Their training words are a session, a
 * trainer, a trainee, a programme, a task. Tests hold the adults' words
 * (messages/adults/*.json over the base) to it.
 */

const LETTERS = '\\u0621-\\u064A\\u0671-\\u06D3';
const STEMS = [
  // School and university.
  'حصة', 'حصتك', 'حصتي', 'حصته', 'حصتها', 'حصتكم', 'حصتان', 'حصتين', 'حصص', 'حصصك', 'حصصي', 'حصصه', 'حصصكم',
  'درس', 'دروس', 'دروسك', 'دراسة', 'دراسته', 'مادة', 'مادتك', 'مواد', 'موادك', 'مقرر', 'مقررات', 'منهج', 'مناهج', 'منهاج',
  'طالب', 'طالبة', 'طالبك', 'طلاب', 'طلابك', 'طلبة', 'تلميذ', 'تلاميذ', 'متعلم', 'متعلمة', 'متعلمون', 'متعلمين',
  'معلم', 'معلمة', 'معلمك', 'معلمه', 'معلمها', 'معلمو', 'معلمون', 'معلمين', 'معلمي',
  'مدرسة', 'مدرستك', 'مدارس', 'جامعة', 'جامعات', 'محاضرة', 'محاضرات', 'واجب', 'واجبات', 'واجبك', 'واجباتك', 'امتحان', 'امتحانات',
  // Family.
  'ولي الأمر', 'ولي أمر', 'وليه', 'أولياء', 'طفل', 'طفلك', 'طفلة', 'أطفال', 'أطفالك', 'ابن', 'ابنك', 'ابنتك', 'ابني', 'ابنتي', 'أبناء', 'أبنائك', 'أبنائكم',
  'أسرة', 'أسرتك', 'أسرتكم', 'أسر', 'عائلة', 'عائلتك', 'أهل', 'أهلك', 'إخوة', 'إخوته', 'أخ', 'أخت',
];

/** Arabic, after the short vowels and the shadda are taken out: the stems with و ف ب and ال ل لل ك before them, and الصف, الفصل as a classroom. */
const ADULTS_AR = new RegExp(`(?<![${LETTERS}])(?:[وفب]?(?:لل|ال|ل|ك)?(?:${STEMS.join('|')})|[وفب]?(?:لل|ال)(?:صف|فصل))(?![${LETTERS}])`, 'gu');

const ADULTS_EN = /\b(?:lessons?|teach(?:er|ers|ing|es)?|tutors?|students?|pupils?|learners?|class(?:es|room|mates?)?|schools?|universit(?:y|ies)|college|lectures?|homework|curricul(?:um|a)|child(?:ren|'s)?|kids?|parents?|guardians?|famil(?:y|ies)|sons?|daughters?|siblings?)\b/gi;

/** The words of a text an academy for adults never says, as they are written there (Arabic without its marks). */
export function refusedForAdults(text: string): string[] {
  const plain = text.replace(/[ً-ْٰ]/g, '');
  return [...(plain.match(ADULTS_AR) ?? []), ...(text.match(ADULTS_EN) ?? [])];
}
