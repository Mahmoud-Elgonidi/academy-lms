import '../helpers.dart';

/// The Quran program as the API answers it: passages by surah and verses with their titles (never the Quran's words).

Map<String, Object?> fxRange(int surah, int from, int to, String title) => {'surah': surah, 'from': from, 'to': to, 'title': title};

Map<String, Object?> fxPortion({bool near = true, bool far = true}) => {
  'new': {
    'label': 'الجديد',
    'ranges': [fxRange(67, 1, 10, 'الملك ١–١٠')],
    'pages': [562],
  },
  'near': near
      ? {
          'label': 'المراجعة القريبة',
          'ranges': [fxRange(68, 1, 52, 'القلم ١–٥٢')],
          'pages': [564, 565, 566],
        }
      : null,
  'far': far
      ? {
          'label': 'المراجعة البعيدة',
          'ranges': [fxRange(78, 1, 40, 'النبأ ١–٤٠')],
          'pages': [582, 583],
        }
      : null,
  'lesson': null,
  'stops': [
    {'kind': 'surah', 'number': 67, 'label': 'نهاية سورة الملك'},
  ],
  'finished': false,
};

Map<String, Object?> fxPlan({String? enrollment = 'e1', String? group, String? frozen, double progress = 0.42}) => {
  'id': 'p1',
  'enrollment_id': enrollment,
  'group_id': group,
  'program': {
    'id': 'pr1',
    'name': 'حفظ جزء تبارك',
    'track': 'hifz',
    'track_label': 'حفظ',
    'direction': 'forward',
    'unit': 'verses',
    'unit_label': 'آيات',
    'pass_grade': 'good',
    'exam_stops': 'surah',
  },
  'pace': {'new_amount': 10, 'near_portions': 3, 'far_pages': 2},
  'position': {'surah': 67, 'verse': 1, 'title': 'الملك ١'},
  'finished': false,
  'frozen': frozen,
  'frozen_label': frozen == null ? null : 'الكورس موقوف',
  'progress': progress,
  'portion': fxPortion(),
};

Map<String, Object?> fxResult({String student = 'u-kid', bool counts = true, bool notes = true, bool advanced = true}) => {
  'id': 'res-$student',
  'session_id': 'l1',
  'student_id': student,
  'parts': {
    'new': {
      'label': 'الجديد',
      'ranges': [fxRange(66, 1, 12, 'التحريم ١–١٢')],
      'lesson': null,
      'grade': 'very_good',
      'grade_label': 'جيد جدًّا',
      'mistakes': counts
          ? [
              {'kind': 'wrong', 'label': 'خطأ', 'count': 2},
              {'kind': 'tajweed', 'label': 'تجويد', 'count': 0},
            ]
          : <Object>[],
      if (notes) 'computed': 'good',
      if (notes) 'note': 'تحسّن واضح',
    },
  },
  'advanced': advanced,
  'frozen': false,
  'recorded_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
};

const fxMistakeTypes = [
  {'key': 'wrong', 'label': 'خطأ', 'weight': 1},
  {'key': 'hesitation', 'label': 'تردد', 'weight': 0.5},
  {'key': 'tajweed', 'label': 'تجويد', 'weight': 0.25},
];

const fxGrades = [
  {'key': 'excellent', 'label': 'ممتاز'},
  {'key': 'very_good', 'label': 'جيد جدًّا'},
  {'key': 'good', 'label': 'جيد'},
  {'key': 'acceptable', 'label': 'مقبول'},
  {'key': 'repeat', 'label': 'إعادة'},
];

Map<String, Object?> fxLessonPortion({bool plan = true, bool group = false, List<Map<String, Object?>> results = const []}) => {
  'data': {
    'session_id': 'l1',
    'group': group,
    'students': group
        ? [
            {'id': 'u-kid', 'name': 'يوسف خالد'},
            {'id': 'u-two', 'name': 'سلمى رامي'},
          ]
        : [
            {'id': 'u-kid', 'name': 'يوسف خالد'},
          ],
    'plan': plan ? fxPlan(enrollment: group ? null : 'e1', group: group ? 'g1' : null) : null,
    'mistake_types': fxMistakeTypes,
    'grades': fxGrades,
    'results': results,
  },
};

Map<String, Object?> fxQuranPlans({bool counts = true, int courses = 1}) => {
  'data': {
    'learner_id': 'c1',
    'courses': [
      for (var i = 0; i < courses; i++)
        {
          'enrollment_id': 'e${i + 1}',
          'subject': 'القرآن الكريم',
          'plan': fxPlan(),
          'recent': [fxResult(counts: counts, notes: false)],
        },
    ],
  },
};

Map<String, Object?> fxExam({String status = 'passed', bool counts = true, bool family = true}) => {
  'id': 'x-$status',
  'student_id': 'u-kid',
  'student': family ? null : 'يوسف خالد',
  'enrollment_id': 'e1',
  'program': {'id': 'pr1', 'name': 'حفظ جزء تبارك'},
  'stop': {'kind': 'surah', 'number': 67, 'label': 'سورة الملك'},
  'status': status,
  'status_label': switch (status) {
    'passed' => 'ناجح',
    'failed' => 'لم ينجح',
    'booked' => 'محجوز',
    _ => 'مقترح',
  },
  'retake': false,
  'not_before': null,
  'examiner': {'id': 'u-ex', 'name': 'أ. هدى سالم'},
  'lesson': status == 'suggested'
      ? null
      : {
          'session_id': 'l9',
          'status': 'scheduled',
          'starts_at': time('2099-10-12T16:00:00Z', '2099-10-12T19:00:00+03:00', label: 'الاثنين 12 أكتوبر · 7:00 م'),
          'ends_at': time('2099-10-12T16:30:00Z', '2099-10-12T19:30:00+03:00'),
        },
  'result': status == 'passed' || status == 'failed'
      ? {
          'grade': status == 'passed' ? 'excellent' : 'repeat',
          'grade_label': status == 'passed' ? 'ممتاز' : 'إعادة',
          'passed': status == 'passed',
          'mistakes': counts
              ? [
                  {'kind': 'wrong', 'label': 'خطأ', 'count': 1},
                ]
              : <Object>[],
          'note': status == 'failed' ? 'راجع الآيات ١١–٢٠' : null,
          'retake_on': status == 'failed' ? '2099-10-20' : null,
          'recorded_at': time('2099-10-05T16:00:00Z', '2099-10-05T19:00:00+03:00', label: 'الاثنين 5 أكتوبر'),
        }
      : null,
};

Map<String, Object?> fxCertificate({bool revoked = false}) => {
  'id': revoked ? 'cert-2' : 'cert-1',
  'number': 'Q-2099-000001',
  'title': 'شهادة إتمام سورة الملك',
  'portion': {'kind': 'surah', 'number': 67, 'label': 'سورة الملك'},
  'riwaya': 'حفص عن عاصم',
  'grade': 'excellent',
  'grade_label': 'ممتاز',
  'examiner': 'أ. هدى سالم',
  'issued_at': time('2099-10-05T16:00:00Z', '2099-10-05T19:00:00+03:00', label: 'الاثنين 5 أكتوبر'),
  'revoked': revoked,
  'url': revoked ? null : 'https://demo.test/quran/certificates/cert-1?signature=abc',
  'code': revoked ? null : 'ABCD-EFGH-JKLM-NPQR',
};

const fxNotInPlan = {'type': 'feature-not-in-plan', 'title': 'هذا غير متاح في باقة الأكاديمية.', 'status': 403};

/// A family's Quran answers: the plans, the exams and the certificates.
void fxQuranRoutes(FakeApi api, {bool counts = true}) {
  api
    ..on('GET', 'quran/plan', fxQuranPlans(counts: counts))
    ..on('GET', 'quran/exams', {
      'data': {
        'learner_id': 'c1',
        'exams': [fxExam(status: 'booked', counts: counts), fxExam(status: 'failed', counts: counts), fxExam(counts: counts)],
      },
    })
    ..on('GET', 'quran/certificates', {
      'data': {
        'learner_id': 'c1',
        'shown': true,
        'certificates': [fxCertificate(), fxCertificate(revoked: true)],
      },
    });
}
