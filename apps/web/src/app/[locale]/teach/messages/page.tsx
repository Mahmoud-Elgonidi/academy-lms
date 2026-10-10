import { getTranslations } from 'next-intl/server';
import { ChatIndex } from '@/components/messages/chat-pages';

/** "Messages": each student the teacher teaches, with the student's guardians in every conversation. */
export default async function TeacherMessages() {
  const t = await getTranslations('chat');
  return <ChatIndex base="/teach/messages" title={t('teacherTitle')} />;
}
