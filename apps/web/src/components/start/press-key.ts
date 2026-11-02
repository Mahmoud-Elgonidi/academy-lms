'use client';

import { useRef } from 'react';

/**
 * An idempotency key for one press (a start, a payment, a coupon, calling a
 * start off): the same while its answer is lost, so a retry does it once; a
 * new one after any answer (the API keeps refusals too, so the next try must
 * not replay the last one).
 */
export function usePressKey() {
  const key = useRef('');
  return {
    take: () => (key.current ||= crypto.randomUUID()),
    answered: () => {
      key.current = '';
    },
  };
}
