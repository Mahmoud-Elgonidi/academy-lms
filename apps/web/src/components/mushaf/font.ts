import localFont from 'next/font/local';

// The mushaf's text, in the shared mushaf and the mistakes notebook only: Amiri Quran
// (OFL, src/fonts/OFL-AmiriQuran.txt), the same file the panels serve. Its variable goes
// on the part that shows the Quran; `font-quran` (globals.css) uses it. Not preloaded:
// every lesson's page carries the class page, and most lessons have no mushaf, so the
// file comes only when the Quran is on screen.
export const quranFont = localFont({
  src: '../../fonts/AmiriQuran-Regular.ttf',
  weight: '400',
  variable: '--font-amiri-quran',
  display: 'swap',
  preload: false,
  adjustFontFallback: 'Times New Roman',
});
