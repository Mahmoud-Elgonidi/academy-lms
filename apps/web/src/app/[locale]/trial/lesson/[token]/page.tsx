import { redirect } from 'next/navigation';

type Props = { params: Promise<{ locale: string; token: string }> };

/** The trial's own link without a step leads to changing its time (where cancelling is offered too). */
export default async function TrialLessonPage({ params }: Props) {
  const { locale, token } = await params;
  redirect(`/${locale}/trial/lesson/${token}/change`);
}
