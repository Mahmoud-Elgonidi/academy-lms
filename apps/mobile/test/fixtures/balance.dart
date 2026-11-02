import '../helpers.dart';

/// A course's balance as `GET family/courses/{enrollment}/balance` answers it, in [stage]'s look: what a look does not
/// show is null, as the API leaves it out.
Map<String, Object?> fxCourseBalance({
  String id = 'e1',
  String stage = 'classic',
  int left = 3,
  bool soon = true,
  bool waiting = false,
  bool payer = true,
  String status = 'active',
  bool next = true,
  String? renewalNote,
}) {
  final shape = stage != 'kids';
  final money = stage == 'classic';
  return {
    'data': {
      'enrollment_id': id,
      'status': status,
      'viewer_stage': stage,
      'lessons_left': left,
      'lessons_text': 'بقيت $left حصص',
      'makeup_lessons': shape ? 1 : null,
      'minutes_left': shape ? left * 45 : null,
      'plan': shape
          ? {
              'id': 'p1',
              'name': 'الباقة الشهرية',
              'format': 'private',
              'shared': false,
              'session_minutes': 45,
              'sessions_per_week': 2,
              'sessions_per_block': 8,
              'renews_at_lessons_left': 2,
            }
          : null,
      'expiry': shape
          ? {
              'at': time('2099-10-20T21:00:00Z', '2099-10-21T00:00:00+03:00', label: 'الأربعاء 21 أكتوبر'),
              'lessons': 2,
              'soon': soon,
              'text': soon
                  ? 'تنتهي صلاحية 2 من الحصص في الأربعاء 21 أكتوبر. احجزوا مواعيدها قبل ذلك حتى لا تضيع.'
                  : 'تنتهي صلاحية الحصص المتبقية في الأربعاء 21 أكتوبر.',
            }
          : null,
      'paused_until': null,
      'ends_on': null,
      'is_payer': money ? payer : null,
      'renews': money ? renewalNote == null : null,
      'waiting_invoice': money && waiting
          ? {
              'id': payer ? 'i9' : null,
              'number': 'INV-2099-0009',
              'outstanding': '400 ر.س.',
              'outstanding_minor': 40000,
              'currency': 'SAR',
              'due_at': time('2099-10-12T15:00:00Z', '2099-10-12T18:00:00+03:00', label: 'الاثنين 12 أكتوبر'),
              'text': 'فاتورة الباقة التالية INV-2099-0009 تنتظر الدفع (المتبقي 400 ر.س.).',
            }
          : null,
      'next_invoice': money && !waiting && next && renewalNote == null
          ? {
              'at_lessons_left': 2,
              'lessons_until': 1,
              'amount': '400 ر.س.',
              'amount_minor': 40000,
              'currency': 'SAR',
              'on': time('2099-10-14T21:00:00Z', '2099-10-15T00:00:00+03:00', zone: 'Asia/Riyadh', label: 'الخميس 15 أكتوبر'),
              'text': 'تصدر الفاتورة التالية عندما يبقى 2 حصص، بنحو 400 ر.س.، في الخميس 15 أكتوبر تقريبًا.',
            }
          : null,
      'renewal_note': money ? renewalNote : null,
      'auto_renew': null,
    },
  };
}

/// The learner's balance (`GET family/balance`) with one course.
Map<String, Object?> fxBalanceList({String id = 'e1', String subject = 'التجويد', int sessions = 3}) => {
  'data': [
    {
      'enrollment_id': id,
      'subject': subject,
      'shared': false,
      'format': 'private',
      'group': null,
      'minutes': sessions * 45,
      'session_minutes': 45,
      'sessions': sessions,
      'status': 'active',
      'paused_until': null,
      'ends_on': null,
      'extra': null,
    },
  ],
};

/// `GET family/consents` with the wording for a child about to be added.
Map<String, Object?> fxConsentsWithNewChild({int version = 4}) => {
  'age': 13,
  'children': <Object>[],
  'new_child': {
    'version': version,
    'title': 'موافقتك على حساب {child}',
    'intro': 'تقدّم أكاديمية النور لـ {child} حصصًا عبر الإنترنت.',
    'sections': [
      {'title': 'ما الذي نحتفظ به', 'body': 'الاسم وتاريخ الميلاد ومواعيد حصص {child}.'},
    ],
    'agree': 'أنا ولي أمر {child}، وأوافق.',
  },
};

/// `POST family/children`, answered.
Map<String, Object?> fxAddedChild({String id = 'c9', String name = 'يارا سالم'}) => {
  'data': {
    'id': id,
    'name': name,
    'stage': 'kids',
    'consent': 'granted',
    'start': {'learner_id': id},
  },
  'message': 'أُضيف $name إلى حسابكم. اختاروا له الآن الكورس والمواعيد.',
};
