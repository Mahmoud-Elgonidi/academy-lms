/** GET /v1/teacher/checks: the teacher's own vetting documents and where their criminal record check stands. */
export type MyDocuments = {
  required: boolean;
  state: 'verified' | 'pending' | 'expired' | 'missing' | 'not_required';
  state_label: string;
  valid_until: string | null;
  kinds: { key: string; label: string }[];
  checks: TeacherDocument[];
};

export type TeacherDocument = {
  id: string;
  kind: { key: string; label: string };
  reference: string | null;
  issued_on: string | null;
  expires_on: string | null;
  status: { key: 'pending' | 'verified' | 'rejected'; label: string };
  reason: string | null;
  sent_at: string | null;
  file: { name: string; url: string } | null;
};

/** The tone a state shows in: in order, waiting, or something to do. */
export function documentTone(state: MyDocuments['state']): 'brand' | 'attention' | 'danger' {
  return state === 'verified' ? 'brand' : state === 'pending' ? 'attention' : 'danger';
}
