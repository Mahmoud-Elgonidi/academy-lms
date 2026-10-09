/** "My data" as the API gives it (GET /v1/me/data-requests): whom one may ask about, the requests, and the academy's public pages. */
export type LocalTime = { utc: string; local: string; timezone: string | null; label: string };

export type DataRequest = {
  id: string;
  kind: { key: 'export' | 'erasure'; label: string };
  status: { key: string; label: string };
  subject: { id: string; name: string; self: boolean };
  requester: string;
  reason: string | null;
  answer: string | null;
  created_at: LocalTime | null;
  due_at: LocalTime | null;
  erase_after: LocalTime | null;
  done_at: LocalTime | null;
  expires_at: LocalTime | null;
  size: number | null;
  download_url: string | null;
  held_back: string[];
  can_cancel: boolean;
};

export type MyData = {
  subjects: { id: string; name: string; self: boolean; can_copy: boolean }[];
  requests: DataRequest[];
  goes: string[];
  stays: string[];
  stays_note: string;
  grace_hours: number;
  answer_days: number;
  links: { privacy: string; terms: string; deletion: string };
};

/** The latest copy and the live (or last answered) erasure of one person, from the requests (newest first). */
export function latestOf(requests: readonly DataRequest[], subject: string): { copy?: DataRequest; erasure?: DataRequest } {
  const theirs = requests.filter((r) => r.subject.id === subject);
  return {
    copy: theirs.find((r) => r.kind.key === 'export'),
    erasure: theirs.find((r) => r.kind.key === 'erasure' && r.status.key !== 'cancelled'),
  };
}
