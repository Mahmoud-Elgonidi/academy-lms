/**
 * Where a notification opens in the family's portal, from the `screen` and
 * `id` the API puts on every message (as the app's targetOf does for the
 * family and the student stages). Money opens only for whoever sees money
 * (an invoice its own page, starting a course the start flow); the journey,
 * its games and its missions open the journey.
 */
export function familyTargetOf(screen: string | null | undefined, money: boolean, id?: string | null): string {
  switch (screen) {
    case 'session':
    case 'holiday':
    case 'lesson_rating':
      return '/family/lessons';
    case 'invoice':
      // The invoice's own page (its lines, paying it); without an id, the money page.
      if (!money) return '/family';
      return id ? `/family/invoices/${encodeURIComponent(id)}` : '/family/money';
    case 'course_balance':
      // A course's balance (the renewal coming, lessons about to expire): its page, at the card.
      return id ? `/family/progress/${encodeURIComponent(id)}#balance-${encodeURIComponent(id)}` : money ? '/family/money' : '/family';
    case 'start':
      // Starting a course again (an unpaid start that closed itself): the start flow for that learner.
      if (!money) return '/family';
      return id ? `/family/start?learner=${encodeURIComponent(id)}` : '/family/start';
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
    case 'family_trial':
      // A free trial's report, or booking one more after it.
      return id ? `/family/trials/${id}` : '/family/trials';
    case 'announcement':
      // The academy's announcement is read in full where it is: the notifications.
      return '/family/notifications';
    default:
      return '/family';
  }
}
