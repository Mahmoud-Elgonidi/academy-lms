'use client';

import { useSyncExternalStore } from 'react';
import { MUTED_EVENT, sound } from '@/lib/games/sound';

function subscribe(changed: () => void) {
  document.addEventListener(MUTED_EVENT, changed);
  return () => document.removeEventListener(MUTED_EVENT, changed);
}

/** Whether the world's sound is off on this device (the choice the islands keep), following every change. */
export function useMuted(): boolean {
  return useSyncExternalStore(subscribe, () => sound.muted(), () => false);
}
