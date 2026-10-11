/**
 * Where a notification opens in the family's portal, from the `screen` and
 * `id` the API puts on every message (as the app's targetOf does for the
 * family and the student stages). Money opens only for whoever sees money.
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
    case 'challenges':
      return '/family/challenges';
    case 'games':
      return '/family/games';
    case 'homework':
      return '/family/homework';
    case 'guardian_consent':
      return '/family/privacy';
    case 'my_concerns':
      return '/family/tell-us';
    case 'conversation':
      return id ? `/family/messages/${id}` : '/family/messages';
    default:
      return '/family';
  }
}
