/**
 * Where a notification opens in the family's portal, from the `screen` and
 * `id` the API puts on every message (as the app's targetOf does for the
 * family and the student stages). Money opens only for whoever sees money;
 * the journey, its games and its missions open the journey.
 */
export function familyTargetOf(screen: string | null | undefined, money: boolean, id?: string | null): string {
  switch (screen) {
    case 'session':
    case 'holiday':
    case 'lesson_rating':
      return '/family/lessons';
    case 'invoice':
      return money ? '/family/money' : '/family';
    case 'report':
    case 'roadmap':
      return '/family/progress';
    case 'journey':
    case 'games':
    case 'challenges':
      // The games and the week's missions are on the journey now.
      return '/family/journey';
    case 'homework':
      return '/family/homework';
    case 'guardian_consent':
      return '/family/privacy';
    case 'my_concerns':
      return '/family/tell-us';
    case 'conversation':
      return id ? `/family/messages/${id}` : '/family/messages';
    case 'announcement':
      // The academy's announcement is read in full where it is: the notifications.
      return '/family/notifications';
    default:
      return '/family';
  }
}
