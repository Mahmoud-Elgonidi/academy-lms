/**
 * Where everything sits on the 1920×1080 frame. A desktop lesson records the
 * screen at 1280×720 (CSS pixels) and shows it at 1536×864 under the lesson's
 * title bar, with the caption under it; a phone lesson records a 390×844
 * phone (at twice the pixels) and shows it at 434×940 in a phone on the
 * left, the caption on the right.
 */
export const FRAME = { width: 1920, height: 1080, fps: 30 };

export const DESKTOP = {
  viewport: { width: 1280, height: 720 },
  scale: 1.2,
  app: { x: 192, y: 84, width: 1536, height: 864, radius: 16 },
  caption: { x: 0, y: 952, width: 1920, height: 128 },
};

export const PHONE = {
  viewport: { width: 390, height: 844 },
  scale: 2,
  app: { x: 244, y: 104, width: 434, height: 940, radius: 36 },
  bezel: { x: 228, y: 88, width: 466, height: 972, radius: 52 },
  caption: { x: 780, y: 300, width: 1000, height: 560 },
};

export const CARD_SECONDS = { title: 5, end: 3.5 };
