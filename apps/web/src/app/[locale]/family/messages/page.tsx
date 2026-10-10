import { getTranslations } from 'next-intl/server';
import { ChatIndex } from '@/components/messages/chat-pages';
import { family } from '@/lib/family/context';

/** "Messages": the learner on screen with each of their teachers, in every look (a child writes too). */
export default async function FamilyMessages() {
  const [fam, t] = await Promise.all([family(), getTranslations('chat')]);
  return <ChatIndex base="/family/messages" title={t(`title.${fam.stage}`)} learnerId={fam.current?.id} />;
}
