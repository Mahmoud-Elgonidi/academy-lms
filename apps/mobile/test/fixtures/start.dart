import '../helpers.dart';

Map<String, Object?> fxMoney(String text, int minor, {String currency = 'SAR'}) => {
  'amount_minor': minor,
  'currency': currency,
  'amount': text,
};

/// A plan as `family/start/options` prices it: Tajweed, two 45-minute lessons a week, 8 in a block.
Map<String, Object?> fxStartPlan({
  String id = 'p1',
  String name = 'باقة التجويد الأساسية',
  String format = 'private',
  int price = 30000,
  bool offer = true,
  bool shared = false,
  int perWeek = 2,
}) => {
  'id': id,
  'name': name,
  'format': format,
  'shared': shared,
  'session_minutes': 45,
  'sessions_per_week': perWeek,
  'sessions_per_block': 8,
  'validity_days': 30,
  'renews_at_lessons_left': 2,
  'price': fxMoney('${(price / 100).toStringAsFixed(2)} ر.س', price),
  'per_lesson': fxMoney('${(price / 800).toStringAsFixed(2)} ر.س', price ~/ 8),
  'first_invoice': {
    'discounts': [
      if (offer)
        {'label': 'عرض الحصة التجريبية (10٪)', 'amount_minor': -(price ~/ 10), 'amount': '-${(price / 1000).toStringAsFixed(2)} ر.س'},
    ],
    'total': fxMoney('${((price - (offer ? price ~/ 10 : 0)) / 100).toStringAsFixed(2)} ر.س', price - (offer ? price ~/ 10 : 0)),
    'offer_applied': offer,
  },
};

/// What Jana's mother may start: Jana (waiting for consent) and Omar, Tajweed (and Arabic, running already), a private
/// and a group plan, Mona (the trial's teacher) and Ahmad, and the trial's offer open.
Map<String, Object?> fxStartOptions({
  bool selfServe = true,
  bool twoLearners = false,
  bool consentWaiting = false,
  String learner = 'c1',
  List<Map<String, Object?>>? plans,
  bool offer = true,
  bool teachers = true,
  bool trialTeacher = true,
}) => {
  'data': {
    'self_serve': selfServe,
    'why_off': selfServe ? null : 'تسجّل الأكاديمية الكورسات بنفسها. راسلوها لتختار معكم الباقة والمعلّم والمواعيد.',
    'learner': {'id': learner, 'name': learner == 'c1' ? 'جنى' : 'عمر'},
    'learners': selfServe
        ? [
            {'id': 'c1', 'name': 'جنى', 'is_self': false, 'consent_waiting': consentWaiting},
            if (twoLearners) {'id': 'c2', 'name': 'عمر', 'is_self': false, 'consent_waiting': false},
          ]
        : <Object>[],
    'subjects': selfServe
        ? [
            {'id': 'sub1', 'name': 'التجويد', 'course': null, 'can_start': true, 'why_not': null},
            {
              'id': 'sub2',
              'name': 'اللغة العربية',
              'course': {'enrollment_id': 'e5', 'status': 'active'},
              'can_start': false,
              'why_not': 'جنى تدرس اللغة العربية بالفعل. افتحوا الكورس من صفحة الحصص.',
            },
          ]
        : <Object>[],
    'region': selfServe ? {'id': 'r1', 'name': 'السعودية', 'currency': 'SAR'} : null,
    'plans': selfServe
        ? (plans ?? [fxStartPlan(offer: offer), fxStartPlan(id: 'p2', name: 'مجموعة التجويد', format: 'group', price: 16000, offer: false)])
        : <Object>[],
    'teachers': selfServe && teachers
        ? [
            {
              'id': 't1',
              'name': 'أ. منى',
              'bio': 'معلّمة تجويد بإجازة.',
              'photo_url': null,
              'gender': 'female',
              'trial_teacher': trialTeacher,
              'private': true,
              'groups': 1,
            },
            {
              'id': 't2',
              'name': 'أ. أحمد',
              'bio': null,
              'photo_url': null,
              'gender': 'male',
              'trial_teacher': false,
              'private': true,
              'groups': 0,
            },
          ]
        : <Object>[],
    'offer': offer
        ? {
            'state': 'open',
            'percent': 10,
            'ends_at': time('2099-11-03T18:00:00Z', '2099-11-03T21:00:00+03:00', label: 'الثلاثاء 3 نوفمبر · 9:00 م'),
          }
        : null,
    'trial': {'session_id': 's1', 'teacher_available': trialTeacher},
    'payment': selfServe ? {'unpaid_cancel_hours': 24, 'notice_hours': 36} : null,
  },
};

Map<String, Object?> fxTime(
  String key,
  int weekday,
  String start, {
  String day = 'السبت',
  String date = '2099-11-07',
  List<Map<String, Object?>> skips = const [],
}) => {
  'key': key,
  'start': start,
  'end': '${(int.parse(start.substring(0, 2)) + 1).toString().padLeft(2, '0')}:00',
  'label': '$day $start',
  'first': time('${date}T$start:00Z', '${date}T$start:00+03:00', label: '$day ${date.substring(8)} نوفمبر · $start'),
  'skips': skips,
};

/// A week a weekly time starts without, as the API writes it (`skips` on the slots, `skipped` on the start's answer).
Map<String, Object?> fxSkip({String date = '2099-11-14', String reason = 'holiday', String day = 'السبت', String start = '17:00'}) => {
  'date': date,
  'reason': reason,
  'at': time('${date}T$start:00Z', '${date}T$start:00+03:00', label: '$day ${date.substring(8)} نوفمبر · $start'),
  'text': 'لا حصة يوم $day ${int.parse(date.substring(8))} نوفمبر: ${reason == 'holiday' ? 'إجازة الأكاديمية' : 'إجازة المعلّم'}',
};

/// Mona's weekly times: Saturday (two), Monday and Wednesday; Saturday and Monday suggested.
///
/// With [skips], Saturday 17:00 starts without its second week (an academy holiday on 14 November), and Monday
/// without its third (the teacher's leave on 23 November).
Map<String, Object?> fxStartSlots({List<String>? suggested, bool empty = false, int perWeek = 2, bool skips = false}) => {
  'data': {
    'format': 'private',
    'timezone': 'Asia/Riyadh',
    'per_week': perWeek,
    'days': empty
        ? <Object>[]
        : [
            {
              'weekday': 6,
              'name': 'السبت',
              'times': [
                fxTime('6-17:00', 6, '17:00', skips: [if (skips) fxSkip()]),
                fxTime('6-18:00', 6, '18:00'),
              ],
            },
            {
              'weekday': 1,
              'name': 'الاثنين',
              'times': [
                fxTime(
                  '1-17:00',
                  1,
                  '17:00',
                  day: 'الاثنين',
                  date: '2099-11-09',
                  skips: [if (skips) fxSkip(date: '2099-11-23', reason: 'teacher_leave', day: 'الاثنين')],
                ),
              ],
            },
            {
              'weekday': 3,
              'name': 'الأربعاء',
              'times': [fxTime('3-19:00', 3, '19:00', day: 'الأربعاء', date: '2099-11-11')],
            },
          ],
    'suggested': empty ? null : (suggested ?? ['6-17:00', '1-17:00']),
    'first_day': time('2099-11-04T00:00:00Z', '2099-11-04T03:00:00+03:00', label: 'الأربعاء 4 نوفمبر'),
    'groups': <Object>[],
  },
};

Map<String, Object?> fxGroupSlots({bool forming = true}) => {
  'data': {
    'format': 'group',
    'timezone': 'Asia/Riyadh',
    'per_week': 2,
    'days': <Object>[],
    'suggested': null,
    'first_day': null,
    'groups': [
      {
        'id': 'g1',
        'name': 'مجموعة التجويد للبنات',
        'teacher': {'id': 't1', 'name': 'أ. منى'},
        'status': forming ? 'forming' : 'running',
        'seats': {'capacity': 5, 'taken': 2, 'free': 3},
        'min_to_start': 4,
        'times': [
          {'weekday': 6, 'start': '17:00', 'label': 'السبت 5:00 م'},
          {'weekday': 1, 'start': '17:00', 'label': 'الاثنين 5:00 م'},
        ],
        'next': null,
        'held': 0,
      },
    ],
  },
};

/// The invoice as its payer reads it: the block, the trial's offer, VAT 15% added.
Map<String, Object?> fxInvoice({
  String id = 'i1',
  String status = 'issued',
  bool paid = false,
  Map<String, Object?>? coupon,
  bool couponOpen = true,
  String? whyNot,
  bool offer = true,
}) {
  const block = 30000;
  final off = offer ? 3000 : 0;
  final sub = block - off;
  final vat = sub * 15 ~/ 100;
  final total = sub + vat;
  String m(int minor) => '${(minor / 100).toStringAsFixed(2)} ر.س';
  return {
    'id': id,
    'number': 'INV-2026-0042',
    'status': paid ? 'paid' : status,
    'currency': 'SAR',
    'lines': [
      {'kind': 'block', 'description': 'باقة التجويد الأساسية (8 حصص)', 'quantity': 1, 'amount_minor': block, 'amount': m(block)},
      if (offer)
        {'kind': 'discount', 'description': 'عرض الحصة التجريبية (10٪)', 'quantity': 1, 'amount_minor': -off, 'amount': '-${m(off)}'},
    ],
    'subtotal': {'amount_minor': sub, 'amount': m(sub)},
    'vat': {'rate': 15, 'included': false, 'amount_minor': vat, 'amount': m(vat)},
    'total': {'amount_minor': total, 'amount': m(total)},
    'paid': {'amount_minor': paid ? total : 0, 'amount': m(paid ? total : 0)},
    'outstanding': {'amount_minor': paid ? 0 : total, 'amount': m(paid ? 0 : total)},
    'due_at': time('2099-11-06T15:00:00Z', '2099-11-06T18:00:00+03:00', label: 'الجمعة 6 نوفمبر · 6:00 م'),
    'coupon': coupon ?? (offer ? {'code': 'TRIAL-ABC', 'personal': true} : null),
    'coupon_entry': {'open': couponOpen, 'why_not': whyNot},
    'pay_url': 'https://demo.test/pay/x',
    'document_url': 'https://demo.test/pay/x/document',
  };
}

Map<String, Object?> fxInvoiceView({
  String id = 'i1',
  bool paid = false,
  String? checkout,
  bool offer = true,
  Map<String, Object?>? coupon,
}) => {
  'data': fxInvoice(id: id, paid: paid, offer: offer, coupon: coupon),
  'checkout': checkout == null
      ? null
      : {
          'id': 'k1',
          'status': checkout,
          'text': switch (checkout) {
            'paid' => 'وصلنا الدفع، شكرًا!',
            'open' => 'نؤكد الدفع مع البوابة…',
            'failed' => 'لم يتم الدفع.',
            _ => 'انتهت صفحة الدفع قبل إتمام الدفع.',
          },
          'hint': checkout == 'failed' ? 'لم يُسحب أي مبلغ. حاول مرة أخرى أو اختر طريقة أخرى.' : 'ستتحدّث الصفحة تلقائيًا.',
        },
};

/// The course just started, as `POST family/courses` answers it.
Map<String, Object?> fxStarted({bool canCancel = true, String offer = 'applied', bool package = false, bool skips = false}) => {
  'data': {
    'enrollment_id': 'e1',
    'status': package ? 'active' : 'pending_payment',
    'learner': {'id': 'c1', 'name': 'جنى'},
    'subject': {'id': 'sub1', 'name': 'التجويد'},
    'teacher': {'id': 't1', 'name': 'أ. منى'},
    'plan': {
      'id': 'p1',
      'name': 'باقة التجويد الأساسية',
      'format': 'private',
      'session_minutes': 45,
      'sessions_per_week': 2,
      'sessions_per_block': 8,
    },
    'weekly_times': [
      {'weekday': 6, 'start': '17:00', 'label': 'السبت 5:00 م'},
      {'weekday': 1, 'start': '17:00', 'label': 'الاثنين 5:00 م'},
    ],
    'first_lesson': time('2099-11-07T14:00:00Z', '2099-11-07T17:00:00+03:00', label: 'السبت 7 نوفمبر · 5:00 م'),
    'lessons': 8,
    'skipped_dates': skips ? ['2099-11-14', '2099-11-23'] : <String>[],
    'skipped': skips ? [fxSkip(), fxSkip(date: '2099-11-23', reason: 'teacher_leave', day: 'الاثنين')] : <Object>[],
    'joined_package': package,
    'group': null,
    'invoice': package ? null : fxInvoice(),
    'pay_url': package ? null : 'https://demo.test/pay/x',
    'offer': {
      'state': offer,
      'percent': 10,
      'message': offer == 'applied'
          ? 'طُبّق عرضكم (10%) على الفاتورة الأولى.'
          : 'انتهت مدة عرض الحصة التجريبية قبل إتمام الطلب، فبدأ الكورس بسعره العادي.',
    },
    'payment': {
      'pay_by': time('2099-11-06T14:00:00Z', '2099-11-06T17:00:00+03:00', label: 'الجمعة 6 نوفمبر · 5:00 م'),
      'unpaid_cancel_hours': 24,
      'text': package
          ? 'لا شيء تدفعونه الآن: حصصها من باقتكم المشتركة.'
          : 'ادفعوا قبل الجمعة 6 نوفمبر · 5:00 م لتبقى الحصة الأولى في موعدها.',
      'renews': 'تصل فاتورة الباقة التالية حين تبقى حصتان، ويمكنكم التوقف متى شئتم.',
    },
    'can_cancel': canCancel,
  },
};

/// A refusal as the API sends it: start-refused with its reason, its sentence and what to act on.
Map<String, Object?> fxRefused(String reason, String detail, {int status = 409, Map<String, Object?> extra = const {}}) => {
  'type': reason == 'consent' ? 'guardian-consent-needed' : 'start-refused',
  'title': detail,
  'status': status,
  'detail': detail,
  'reason': reason,
  ...extra,
};

/// The start flow's own answers, over the family's.
void startRoutes(FakeApi api, {Map<String, Object?>? options}) {
  api
    ..on('GET', 'family/start/options', options ?? fxStartOptions())
    ..on('GET', 'family/start/slots', fxStartSlots())
    ..on('POST', 'family/courses', fxStarted(), status: 201)
    ..on('GET', 'family/invoices/i1', fxInvoiceView())
    ..on('GET', 'family/invoices', {
      'owed': '310.50 ر.س',
      'data': [
        {
          'id': 'i1',
          'number': 'INV-2026-0042',
          'for': 'جنى · التجويد',
          'total': '310.50 ر.س',
          'outstanding': '310.50 ر.س',
          'outstanding_minor': 31050,
          'status': 'issued',
          'status_label': 'بانتظار الدفع',
          'pay_with': [
            {'account_id': 'g1', 'label': 'بطاقة'},
          ],
          'can_send_proof': true,
          'lines': <Object>[],
        },
      ],
    });
}
