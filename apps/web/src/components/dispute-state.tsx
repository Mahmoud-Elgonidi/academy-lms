import { getTranslations } from 'next-intl/server';
import { join } from '@/components/ui';
import type { LessonDispute } from '@/lib/teach/types';

/** The viewer's own dispute of a lesson: where it stands, and the academy's answer once decided. */
export async function DisputeState({ dispute }: { dispute: LessonDispute }) {
  const t = await getTranslations('dispute');
  const tone = dispute.status === 'upheld' ? 'bg-brand-soft text-brand-strong' : dispute.status === 'rejected' ? 'bg-paper text-ink' : 'bg-attention-soft text-ink';
  return (
    <div className={join('mt-3 rounded-control px-3 py-2 text-sm', tone)}>
      <p className="font-semibold">
        {t('state', { status: dispute.status_label })} · <span className="font-normal">{dispute.reason}</span>
      </p>
      {dispute.answer ? <p className="mt-1 whitespace-pre-line">{t('answer', { answer: dispute.answer })}</p> : null}
    </div>
  );
}
