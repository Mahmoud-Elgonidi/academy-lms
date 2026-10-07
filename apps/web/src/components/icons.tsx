import type { SVGProps } from 'react';

/** The portal's own line icons (24 × 24, drawn here, in the text's colour). */
const paths = {
  sun: 'M12 4V2.5M12 21.5V20M4 12H2.5M21.5 12H20M6.3 6.3 5.2 5.2M18.8 18.8l-1.1-1.1M6.3 17.7l-1.1 1.1M18.8 5.2l-1.1 1.1M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
  calendar: 'M7 3v3M17 3v3M4 9h16M5.5 5h13A1.5 1.5 0 0 1 20 6.5v12a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-12A1.5 1.5 0 0 1 5.5 5Z',
  report: 'M8 8h8M8 12h8M8 16h5M6.5 3h11A1.5 1.5 0 0 1 19 4.5v15a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19.5v-15A1.5 1.5 0 0 1 6.5 3Z',
  check: 'm5 12.5 4.2 4L19 7',
  mark: 'M4 20h4L19 9l-4-4L4 16v4ZM13.5 6.5l4 4',
  people: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20c.6-3.2 3.2-5 6.5-5s5.9 1.8 6.5 5M16 4.3a3.5 3.5 0 0 1 0 6.4M18 15.2c2 .6 3.2 2.3 3.5 4.8',
  spark: 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3ZM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z',
  clock: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  leave: 'M4 20c3-6 6-9 8-9s5 3 8 9M12 11V4M12 4c-3 0-5 1.5-6 4M12 4c3 0 5 1.5 6 4',
  wallet: 'M4 7.5A1.5 1.5 0 0 1 5.5 6H18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5.5A1.5 1.5 0 0 1 4 18.5v-11ZM4 8l11-4.5V6M16 13h.01',
  chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
  bell: 'M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16ZM10 20.5a2 2 0 0 0 4 0',
  menu: 'M4 7h16M4 12h16M4 17h16',
  out: 'M14 4h4.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H14M10 16l-4-4 4-4M6 12h10',
  video: 'M4.5 6h9A1.5 1.5 0 0 1 15 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16.5v-9A1.5 1.5 0 0 1 4.5 6ZM15 10.5l6-3.5v10l-6-3.5',
  chevron: 'm9 6 6 6-6 6',
  plus: 'M12 5v14M5 12h14',
  x: 'M6 6l12 12M18 6 6 18',
  play: 'M7 5v14l11-7L7 5Z',
  file: 'M14 3H7.5A1.5 1.5 0 0 0 6 4.5v15A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V7l-4-4ZM14 3v4h4',
  globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.5 9h17M3.5 15h17M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9Z',
  star: 'm12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9L12 3.5Z',
  send: 'M4 12 20 4l-6 16-2.5-6.5L4 12ZM11.5 13.5 20 4',
} as const;

export type IconName = keyof typeof paths;

export function Icon({ name, size = 20, className, ...rest }: { name: IconName; size?: number } & SVGProps<SVGSVGElement>) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
      {...rest}
    >
      <path d={paths[name]} />
    </svg>
  );
}
