import '../helpers.dart';

/// A moment [from] now, as the API writes it (Riyadh, three hours ahead).
Map<String, Object?> fxAt(Duration from, {String label = ''}) {
  final utc = DateTime.now().toUtc().add(from);
  final local = utc.add(const Duration(hours: 3));
  String two(int n) => n.toString().padLeft(2, '0');
  final wall = '${local.year}-${two(local.month)}-${two(local.day)}T${two(local.hour)}:${two(local.minute)}:00+03:00';
  return time(utc.toIso8601String(), wall, label: label);
}

Map<String, Object?> fxPrice(String text, int minor, {String currency = 'SAR'}) => {
  'price': text,
  'amount_minor': minor,
  'currency': currency,
};

Map<String, Object?> fxPlan({String name = 'باقة التجويد الأساسية', String format = 'private'}) => {
  'id': 'p1',
  'name': name,
  'format': format,
  'session_minutes': 45,
  'sessions_per_week': 2,
  'sessions_per_block': 8,
  'validity_days': 30,
};

/// Jana's Tajweed trial report, as the API answers it for her mother: the
/// offer open for [offerIn], unless another [offerState] is asked for.
Map<String, Object?> fxReport({
  String stage = 'classic',
  String offerState = 'open',
  Duration offerIn = const Duration(hours: 53),
  bool plan = true,
  bool withdrawn = false,
  bool price = true,
  bool familyBigger = false,
  Map<String, Object?>? next,
  String? course,
  bool card = true,
  bool second = false,
  Map<String, Object?>? start,
}) {
  final classic = stage == 'classic';
  final open = classic && offerState == 'open';
  return {
    'viewer_stage': stage,
    'data': {
      'id': 's1',
      'learner': 'جنى',
      'subject': {'id': 'sub1', 'name': 'التجويد'},
      'held_at': time('2026-10-28T15:00:00Z', '2026-10-28T18:00:00+03:00', label: 'الأربعاء 28 أكتوبر · 6:00 م'),
      'second': second,
      'teacher': {
        'name': 'أ. منى',
        'card': card ? {'name': 'أ. منى إبراهيم', 'bio': 'معلّمة تجويد بإجازة في رواية حفص.', 'photo_url': null} : null,
      },
      'level': 'المستوى الأول: أحكام النون الساكنة',
      'strengths': 'تقرأ بصوت واضح وثقة، وتحفظ بسرعة.',
      'to_work_on': 'أحكام النون الساكنة والتنوين\nالمدود',
      'note': 'سعدت بالتعرف إلى جنى. أساسها جيد.',
      'recommendation': {
        'plan': plan && !withdrawn ? fxPlan() : null,
        'plan_withdrawn': withdrawn,
        'per_week': 2,
        'price': classic && price && plan && !withdrawn ? fxPrice('300.00 ر.س', 30000) : null,
        'region': classic && price ? {'id': 'r1', 'name': 'السعودية', 'currency': 'SAR'} : null,
      },
      'course': course == null ? null : {'status': course},
      'offer_state': classic ? (course != null ? 'started' : offerState) : 'none',
      'offer': open && course == null
          ? {
              'percent': 10,
              'ends_at': fxAt(offerIn, label: 'السبت 31 أكتوبر · 9:00 م'),
              'price': price && plan && !withdrawn
                  ? fxPrice(familyBigger ? '255.00 ر.س' : '270.00 ر.س', familyBigger ? 25500 : 27000)
                  : null,
              'saving': price && plan && !withdrawn ? fxPrice(familyBigger ? '0.00 ر.س' : '30.00 ر.س', familyBigger ? 0 : 3000) : null,
              'family_discount_bigger': familyBigger,
            }
          : null,
      'next_trial': classic ? next : null,
      'start': classic ? (start ?? fxStart(plan: plan && !withdrawn ? 'p1' : null)) : null,
    },
  };
}

Map<String, Object?> fxSecond({bool available = true, String? why}) => {
  'kind': 'second',
  'available': available,
  'why_not': why,
  'until': time('2026-11-11T15:00:00Z', '2026-11-11T18:00:00+03:00', label: 'الأربعاء 11 نوفمبر · 6:00 م'),
};

Map<String, Object?> fxTrial(
  String id, {
  String status = 'attended',
  String? outcome,
  bool ready = true,
  String kind = 'first',
  Map<String, Object?>? next,
  Duration ago = const Duration(days: 3),
}) => {
  'id': id,
  'subject': {'id': 'sub1', 'name': 'التجويد'},
  'starts_at': fxAt(-ago, label: 'الأربعاء 28 أكتوبر · 6:00 م'),
  'teacher': 'أ. منى',
  'kind': kind,
  'status': status,
  'outcome': outcome,
  'report_ready': ready,
  'next': next,
};

Map<String, Object?> fxTrials(List<Map<String, Object?>> trials, {String stage = 'classic'}) => {
  'learner': {'id': 'c1', 'name': 'سلمى رامي'},
  'viewer_stage': stage,
  'data': trials,
};

Map<String, Object?> fxSlots() => {
  'data': [
    {'starts_at': time('2099-11-07T15:00:00Z', '2099-11-07T18:00:00+03:00', label: 'السبت 7 نوفمبر · 6:00 م')},
    {'starts_at': time('2099-11-07T16:00:00Z', '2099-11-07T19:00:00+03:00', label: 'السبت 7 نوفمبر · 7:00 م')},
    {'starts_at': time('2099-11-08T15:00:00Z', '2099-11-08T18:00:00+03:00', label: 'الأحد 8 نوفمبر · 6:00 م')},
  ],
};

/// The report's «ابدأ بهذه الباقة» (J3): open with the plan and the trial's teacher, or why not.
Map<String, Object?> fxStart({bool available = true, String? why, String? reason, String? plan = 'p1', String? enrollment}) => {
  'available': available,
  'why_not': why,
  'reason': reason,
  'learner_id': 'c1',
  'subject_id': 'sub1',
  'plan_id': plan,
  'teacher_id': available ? 't1' : null,
  'enrollment_id': enrollment,
};
