// The hifz map's answers, shared by the hifz tests and the small phones'.
import '../helpers.dart';

/// The API's strength names (as the reader's language gives them).
const fxHifzStrengths = {'new': 'لم يبدأ', 'learning': 'قيد الحفظ', 'strong': 'ثابت', 'fading': 'يحتاج إلى مراجعة قريبًا', 'weak': 'ضعيف'};

/// The mushaf's 604 pages in 30 parts (20 each, the last 24), each page new unless [strengths] says otherwise.
List<Map<String, Object?>> fxHifzJuzs([Map<int, String> strengths = const {}]) => [
  for (var j = 1; j <= 30; j++)
    {
      'juz': j,
      'pages': [
        for (var p = (j - 1) * 20 + 1; p <= (j == 30 ? 604 : j * 20); p++) {'page': p, 'strength': strengths[p] ?? 'new'},
      ],
    },
];

Map<String, Object?> _hifzCounts({int memorized = 3, int learning = 1, int due = 2}) => {
  'memorized': memorized,
  'learning': learning,
  'strong': 1,
  'fading': 1,
  'weak': 1,
  'due': due,
};

Map<String, Object?> _wirdPage(int page, {String strength = 'fading', int overdue = 0}) => {
  'page': page,
  'juz': 1,
  'title': 'صفحة $page · السورة الأولى',
  'surahs': [
    {'number': 1, 'name_ar': 'السورة الأولى', 'name_en': 'First'},
  ],
  'strength': strength,
  'overdue_days': overdue,
};

/// `GET learning/hifz`: pages 1–5 strong, fading, weak, learning and new; [wird] left today, [done] done.
Map<String, Object?> fxHifzBoard({String mode = 'self', List<int> wird = const [2, 3], int done = 0, Map<String, Object?>? latest}) => {
  'data': {
    'learner_id': 'c1',
    'mode': mode,
    'pages_total': 604,
    'counts': _hifzCounts(due: wird.length),
    'juzs': fxHifzJuzs({1: 'strong', 2: 'fading', 3: 'weak', 4: 'learning'}),
    'strengths': fxHifzStrengths,
    'wird': {
      'daily': 3,
      'done_today': done,
      'pages': [for (final p in wird) _wirdPage(p, strength: p == 3 ? 'weak' : 'fading', overdue: p == 3 ? 2 : 0)],
    },
    'latest_recitation': latest,
  },
};

/// A page's text (made-up words in Tanzil's shape, never the Quran).
Map<String, Object?> fxQuranPage(int page) => {
  'data': {
    'page': page,
    'juz': 1,
    'title': 'صفحة $page · السورة الأولى',
    'surahs': [
      {'number': 1, 'name_ar': 'السورة الأولى', 'name_en': 'First'},
    ],
    'verses': [
      {'surah': 1, 'verse': 1, 'text': 'كَلِمَةٌ أُولَى'},
      {'surah': 1, 'verse': 2, 'text': 'جُمْلَةٌ قَصِيرَةٌ'},
    ],
    'attribution': 'Tanzil Quran Text',
    'strength': page == 3 ? 'weak' : 'fading',
    'due_at': time('2099-10-15T21:00:00Z', '2099-10-16T00:00:00+03:00', label: 'الجمعة 16 أكتوبر'),
  },
};

/// A recited page: pending while it is heard, then its words checked (or why it could not be).
Map<String, Object?> fxRecitation({String status = 'pending'}) => {
  'id': 'rc1',
  'page': 3,
  'status': status,
  'status_label': switch (status) {
    'ready' => 'اتسمع',
    'failed' => 'ماتسمعش',
    _ => 'بيتسمع',
  },
  'failure': status == 'failed' ? 'التسجيل مش واضح.' : null,
  'summary_label': status == 'ready' ? 'صح 2 من 4 · ناقصة 1 · غلط 1 · زيادة 1' : null,
  'grade': status == 'ready' ? 2 : null,
  'grade_label': status == 'ready' ? 'بصعوبة' : null,
  'verses': status != 'ready'
      ? <Object>[]
      : [
          {
            'surah': 1,
            'verse': 1,
            'words': [
              {'text': 'كَلِمَةٌ', 'state': 'ok', 'heard': null},
              {'text': 'أُولَى', 'state': 'missing', 'heard': null},
            ],
          },
          {
            'surah': 1,
            'verse': 2,
            'words': [
              {'text': 'جُمْلَةٌ', 'state': 'ok', 'heard': null},
              {'text': 'قَصِيرَةٌ', 'state': 'wrong', 'heard': 'طويله'},
            ],
          },
        ],
  'extra': status != 'ready'
      ? <Object>[]
      : [
          {'after': 1, 'text': 'كلمه'},
        ],
  'recited_at': time('2099-10-15T10:00:00Z', '2099-10-15T13:00:00+03:00', label: '13:00'),
  'note': 'التصحيح آلي وممكن يغلط؛ المدرّس هو اللي بيحكم في الآخر.',
};

/// `GET teacher/students/{enrollment}/hifz`, made-up surahs: the first on page 1, the second on pages 2 to 49.
Map<String, Object?> fxStudentHifz({int total = 604, bool follows = true}) => {
  'data': {
    'enrollment_id': 'e1',
    'follows': follows,
    'student': {'id': 'u-kid', 'name': 'يوسف خالد'},
    'pages_total': total,
    'counts': _hifzCounts(),
    'juzs': total == 0 ? <Object>[] : fxHifzJuzs({1: 'strong', 2: 'fading', 3: 'weak', 4: 'learning'}),
    'strengths': fxHifzStrengths,
    'recent': [
      {
        'page': 2,
        'grade': 3,
        'grade_label': 'جيد',
        'source': 'report',
        'source_label': 'تقرير الحصة',
        'at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
      },
    ],
    'surahs': [
      {
        'number': 1,
        'name_ar': 'السورة الأولى',
        'name_en': 'First',
        'verses': 7,
        'pages': [1],
      },
      {
        'number': 2,
        'name_ar': 'السورة التانية',
        'name_en': 'Second',
        'verses': 286,
        'pages': [for (var p = 2; p <= 49; p++) p],
      },
    ],
    'marks': {'strong': 'حافظها كويس', 'good': 'حافظها', 'weak': 'حافظها بس ضعيفة'},
  },
};

const fxHifzToday = {'due': 2, 'done_today': 0, 'daily': 3};
