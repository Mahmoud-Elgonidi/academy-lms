/**
 * Where a notification opens in the teacher's portal, from the `screen` and
 * `id` the API puts on every message (as the app's targetOf does).
 */
export function targetOf(screen: string | null | undefined, id: string | null | undefined): string {
  switch (screen) {
    case 'write_report':
      return id ? `/teach/reports/${id}` : '/teach/reports';
    case 'homework_to_mark':
      return '/teach/marking';
    case 'shift_proposal':
      return '/teach/hours';
    case 'time_off':
      return '/teach/time-off';
    case 'payroll_month':
      return id && /^\d{4}-\d{2}$/.test(id) ? `/teach/earnings?month=${id}` : '/teach/earnings';
    case 'trial':
      return '/teach/trials';
    case 'my_concerns':
      return '/teach/concern';
    case 'my_checks':
      return '/teach/documents';
    case 'conversation':
      return id ? `/teach/messages/${id}` : '/teach/messages';
    default:
      return '/teach';
  }
}
