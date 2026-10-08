import localFont from 'next/font/local';

// The students' looks, as in the app and the panel's stage shells: Baloo Bhaijaan 2 for
// kids, Rubik for middle school (OFL, src/fonts/OFL.txt). Loaded only in the family area.
export const baloo = localFont({
  src: [
    { path: '../../../fonts/BalooBhaijaan2-500.ttf', weight: '500' },
    { path: '../../../fonts/BalooBhaijaan2-700.ttf', weight: '700' },
    { path: '../../../fonts/BalooBhaijaan2-800.ttf', weight: '800' },
  ],
  variable: '--font-baloo',
  display: 'swap',
});

export const rubik = localFont({
  src: [
    { path: '../../../fonts/Rubik-400.ttf', weight: '400' },
    { path: '../../../fonts/Rubik-500.ttf', weight: '500' },
    { path: '../../../fonts/Rubik-700.ttf', weight: '700' },
    { path: '../../../fonts/Rubik-800.ttf', weight: '800' },
  ],
  variable: '--font-rubik',
  display: 'swap',
});
