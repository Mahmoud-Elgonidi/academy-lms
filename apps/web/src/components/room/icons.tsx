/** The lesson room's line icons (24 × 24, in the text's colour). */
const paths = {
  mic: 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3ZM5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M8.5 21h7',
  micOff: 'M15 10V6a3 3 0 0 0-5.7-1.3M9 9v3a3 3 0 0 0 4.6 2.5M18.5 11a6.5 6.5 0 0 1-1 3.5M5.5 11a6.5 6.5 0 0 0 10 5.5M12 17.5V21M8.5 21h7M4 4l16 16',
  camera: 'M4.5 6.5h9A1.5 1.5 0 0 1 15 8v8a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 3 16V8a1.5 1.5 0 0 1 1.5-1.5ZM15 10.5l6-3.5v10l-6-3.5',
  cameraOff: 'M8 6.5h5.5A1.5 1.5 0 0 1 15 8v4.5M15 10.5l6-3.5v10l-3-1.75M13.5 17.5h-9A1.5 1.5 0 0 1 3 16V8a1.5 1.5 0 0 1 1-1.4M4 4l16 16',
  screen: 'M4.5 4.5h15A1.5 1.5 0 0 1 21 6v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 15.5V6a1.5 1.5 0 0 1 1.5-1.5ZM8.5 20.5h7M12 17v3.5M12 13.5V8M9.5 10.5 12 8l2.5 2.5',
  screenStop: 'M4.5 4.5h15A1.5 1.5 0 0 1 21 6v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 15.5V6a1.5 1.5 0 0 1 1.5-1.5ZM8.5 20.5h7M12 17v3.5M9.5 8.5l5 5M14.5 8.5l-5 5',
  pip: 'M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5ZM12.5 12h5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-.5.5h-5a.5.5 0 0 1-.5-.5v-3a.5.5 0 0 1 .5-.5Z',
  layout: 'M4.5 4h15A1.5 1.5 0 0 1 21 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5v-13A1.5 1.5 0 0 1 4.5 4ZM12 4v16M3 12h18',
  panel: 'M4.5 4h15A1.5 1.5 0 0 1 21 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5v-13A1.5 1.5 0 0 1 4.5 4ZM15 4v16',
  book: 'M12 6.5C10.5 5 8 4.5 4 4.5v14c4 0 6.5.5 8 2 1.5-1.5 4-2 8-2v-14c-4 0-6.5.5-8 2ZM12 6.5v14',
  more: 'M12 6h.01M12 12h.01M12 18h.01',
  leave: 'M3 14.5c5-4.5 13-4.5 18 0l-2 2.5-3.5-1.2v-2.6a10 10 0 0 0-7 0v2.6L5 17l-2-2.5Z',
  people: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20c.6-3.2 3.2-5 6.5-5s5.9 1.8 6.5 5M16 4.3a3.5 3.5 0 0 1 0 6.4M18 15.2c2 .6 3.2 2.3 3.5 4.8',
  pin: 'M9 4h6l-1 5 3 3v1.5H7V12l3-3-1-5ZM12 13.5V20',
  check: 'm5 12.5 4.2 4L19 7',
  x: 'M6 6l12 12M18 6 6 18',
  sound: 'M4 9.5h3.5L12 6v12l-4.5-3.5H4v-5ZM15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11',
  record: 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
};

export type RoomIconName = keyof typeof paths;

export function RoomIcon({ name, className = 'ic' }: { name: RoomIconName; className?: string }) {
  const bold = name === 'more';
  return (
    <svg viewBox="0 0 24 24" className={className} fill="none" stroke="currentColor" strokeWidth={bold ? 3 : 1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      <path d={paths[name]} />
    </svg>
  );
}
