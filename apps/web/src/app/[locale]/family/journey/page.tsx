import { JourneyScreen } from '@/components/journey/screen';

/**
 * The journey for everyone (a student's home shows the same): ?homework=
 * starts that homework's practice, ?island= opens an island (back from the
 * album). Old links to the games and the challenges come here.
 */
export default async function JourneyPage({ searchParams }: { searchParams: Promise<{ homework?: string; island?: string }> }) {
  const asked = await searchParams;
  return <JourneyScreen homework={asked.homework} island={asked.island} />;
}
