import { getTranslations } from 'next-intl/server';
import { RecordingConsentCard, RecordingList } from '@/components/recordings';
import { Heading, Notice } from '@/components/ui';
import { get } from '@/lib/api/server';
import { family } from '@/lib/family/context';
import type { RecordingConsents, RecordingList as Recordings } from '@/lib/recordings';

/**
 * Lesson recordings for the learner on screen: their consent (a guardian
 * agrees for a child, an adult student for themself) and their recordings to
 * watch when the academy shares them. Live through LiveRefresh (lessons and
 * consents).
 */
export default async function FamilyRecordings() {
  const { current } = await family();
  const t = await getTranslations('recordings');
  const [consents, list] = await Promise.all([
    get<RecordingConsents>('/v1/recordings/consents'),
    current ? get<Recordings>('/v1/family/recordings', { query: { learner_id: current.id } }) : Promise.resolve<Recordings>({ shared: false, data: [] }),
  ]);
  const consent = consents.people.find((p) => p.person.id === current?.id);
  const off = consents.recording === 'off';

  return (
    <div className="space-y-5">
      <Heading title={t('title')} />
      {off ? <Notice tone="muted">{t('off')}</Notice> : null}
      {consent ? <RecordingConsentCard consent={consent} off={off} /> : null}
      {list.shared ? (
        <section className="space-y-3">
          <h2 className="font-heading text-lg font-bold">{t('list', { name: current?.name ?? '' })}</h2>
          <RecordingList items={list.data} />
        </section>
      ) : null}
    </div>
  );
}
