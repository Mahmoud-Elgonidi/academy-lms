import { type NextRequest } from 'next/server';
import { calendarEventOk, icsFor } from '@/lib/site/trial';

/**
 * A booked free trial as a calendar file (iCalendar), for "add to calendar":
 * written here on the server from the API's UTC instant and the lesson's
 * length, so every calendar puts it on its owner's clock with no zone worked
 * out in the browser. It holds only what the page already showed (the time
 * and the words given), nothing from the academy's records.
 */
export function GET(request: NextRequest) {
  const query = request.nextUrl.searchParams;
  const event = {
    utc: query.get('start'),
    minutes: Number(query.get('minutes') ?? ''),
    title: (query.get('title') ?? '').slice(0, 120),
    details: (query.get('details') ?? '').slice(0, 300) || undefined,
  };
  if (!calendarEventOk(event) || !event.title.trim()) return new Response('Not found', { status: 404 });
  return new Response(icsFor(event, new Date().toISOString().replace(/\.\d{3}Z$/, 'Z')), {
    headers: {
      'Content-Type': 'text/calendar; charset=utf-8',
      'Content-Disposition': 'attachment; filename="trial-lesson.ics"',
      'Cache-Control': 'no-store',
    },
  });
}
