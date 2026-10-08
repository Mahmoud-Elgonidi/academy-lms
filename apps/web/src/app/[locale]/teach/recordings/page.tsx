import { getTranslations } from 'next-intl/server';
import { RecordingConsentCard, RecordingList } from '@/components/recordings';
import { Heading, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
import type { RecordingConsents, RecordingList as Recordings } from '@/lib/recordings';

/** A teacher's consent to their lessons being recorded, and their lessons' recordings to watch (never to download). */
export default async function TeacherRecordings() {
  const t = await getTranslations('recordings');
  const [consents, list] = await Promise.all([get<RecordingConsents>('/v1/recordings/consents'), get<Recordings>('/v1/teacher/recordings')]);
  const own = consents.people.find((p) => p.self);
  const off = consents.recording === 'off';

  return (
    <div className="space-y-5">
      <Heading title={t('teacherTitle')} />
      {off ? <Notice tone="muted">{t('off')}</Notice> : null}
      {own ? <RecordingConsentCard consent={own} off={off} /> : null}
      {list.shared ? (
        <section className="space-y-3">
          <h2 className="font-heading text-lg font-bold">{t('teacherList')}</h2>
          <RecordingList items={list.data} withStudent />
        </section>
      ) : (
        <Notice tone="muted">{t('notShared')}</Notice>
      )}
    </div>
  );
}
