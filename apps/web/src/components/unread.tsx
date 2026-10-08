import { join } from '@/components/ui';

/**
 * How many messages are unread, next to "notifications" (a dot on a phone's
 * "more" tab); it updates with the page when a message arrives or is read.
 */
export function Unread({ n, label, dot = false, className }: { n: number; label: string; dot?: boolean; className?: string }) {
  if (n <= 0) return null;
  if (dot) {
    return (
      <span className={join('absolute size-2.5 rounded-full bg-danger ring-2 ring-white', className ?? 'top-2 end-[calc(50%-0.9rem)]')}>
        <span className="sr-only">{label}</span>
      </span>
    );
  }
  return (
    <span className={join('ms-auto min-w-6 rounded-full bg-danger px-1.5 text-center text-xs font-bold leading-5 text-white tabular-nums', className)}>
      {n > 99 ? '99+' : n}
      <span className="sr-only"> {label}</span>
    </span>
  );
}
