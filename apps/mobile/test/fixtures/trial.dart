import '../helpers.dart';

/// The academy's page as `GET site` answers it: two subjects, families book their own trial (or [booking] staff).
Map<String, Object?> sitePage({
  String booking = 'family',
  int hours = 4,
  bool helpChooseSubject = true,
  String? whatsapp = '+966500000099',
  String? helpChooseSubjectId,
}) => {
  'data': {
    'academy': {'name': 'أكاديمية النور', 'code': 'demo'},
    'content': <String, Object?>{},
    'subjects': [
      {
        'id': 'sub-quran',
        'name': 'القرآن الكريم',
        'levels': ['المستوى الأول', 'المستوى الثاني', 'المستوى الثالث'],
        'categories': ['حفظ'],
        'programs': <Object>[],
      },
      {'id': 'sub-arabic', 'name': 'اللغة العربية', 'levels': <String>[], 'categories': <String>[], 'programs': <Object>[]},
    ],
    'teachers': <Object>[],
    'plans': <Object>[],
    'regions': <Object>[],
    'app': {'android_url': null, 'ios_url': null},
    'whatsapp': whatsapp,
    'trials': {
      'booking': booking,
      'response_hours': hours,
      'notice_hours': 3,
      'help_choose_subject': helpChooseSubject,
      'help_choose_subject_id': ?helpChooseSubjectId,
    },
    'form_token': 'form-token-1',
  },
};

/// One free time on the family's clock (Riyadh), with the API's label.
Map<String, Object?> slot(String date, String hhmm, {String label = ''}) {
  final h = int.parse(hhmm.substring(0, 2));
  final utc = '${date}T${(h - 3).toString().padLeft(2, '0')}:${hhmm.substring(3)}:00Z';
  return {'starts_at': time(utc, '${date}T$hhmm:00+03:00', label: label.isEmpty ? '$date · $hhmm' : label)};
}

/// The times on offer: Saturday 7 November 2099 morning and evening, Sunday 8 evening.
Map<String, Object?> slots([List<Map<String, Object?>>? rows]) => {
  'data':
      rows ??
      [
        slot('2099-11-07', '09:00', label: 'السبت 7 نوفمبر · 9:00 ص'),
        slot('2099-11-07', '18:00', label: 'السبت 7 نوفمبر · 6:00 م'),
        slot('2099-11-08', '19:30', label: 'الأحد 8 نوفمبر · 7:30 م'),
      ],
};

Map<String, Object?> codeSent({int resendAfter = 60, String expires = '2099-01-01T00:10:00Z'}) => {
  'data': {
    'challenge_id': 'ch-trial',
    'channel': 'whatsapp',
    'sent_to': '+966•••••••04',
    'expires_at': time(expires, '2099-01-01T03:10:00+03:00'),
    'resend_after': resendAfter,
  },
};

Map<String, Object?> booked({String? teacher, int? minutes}) => {
  'data': {
    'starts_at': time('2099-11-07T15:00:00Z', '2099-11-07T18:00:00+03:00', label: 'السبت 7 نوفمبر · 6:00 م'),
    'subject': 'القرآن الكريم',
    'teacher': ?teacher,
    'minutes': ?minutes,
  },
};

/// A refusal on one field, in the API's words.
Map<String, Object?> refused(String field, String words) => {
  'type': 'validation',
  'title': 'البيانات المرسلة غير صحيحة.',
  'status': 422,
  'errors': {
    field: [words],
  },
};
