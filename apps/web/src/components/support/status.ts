import type { Tone } from '@/components/ui';
import type { TicketStatus } from '@/lib/support/types';

/** How each status looks: the person's turn stands out. */
export const STATUS_TONE: Record<TicketStatus, Tone> = { open: 'muted', waiting: 'brand', resolved: 'info', closed: 'muted' };
