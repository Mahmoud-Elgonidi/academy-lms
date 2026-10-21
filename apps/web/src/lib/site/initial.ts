/** A name's first letter, past a title such as "أ." or "Mr" ("أ. خالد" → "خ"), for a picture-less card. */
export function initialOf(name: string): string {
  const words = name.trim().split(/\s+/);
  const word = words.find((w) => !w.endsWith('.') && !/^(mr|mrs|ms|miss|dr)$/i.test(w)) ?? words[0] ?? '';
  return word.charAt(0);
}
