// A lesson's shared mushaf and the mistakes notebook, as the API answers them: made-up words in Tanzil's shape
// (never the Quran). Page 2 ends the first surah and begins the second (its basmala from the platform's copy);
// every other page is the second surah's.
import '../helpers.dart';

/// A word marked in this lesson.
Map<String, Object?> fxMark(int surah, int verse, int word, String kind) => {'surah': surah, 'verse': verse, 'word': word, 'kind': kind};

const fxMushafKinds = {'wrong': 'خطأ', 'hesitation': 'تردد', 'tajweed': 'تجويد'};

List<Map<String, Object?>> _verses(int page) => page == 2
    ? [
        {
          'surah': 1,
          'verse': 3,
          'words': ['كَلِمَةٌ', 'ثَالِثَةٌ'],
        },
        {
          'surah': 2,
          'verse': 1,
          'words': ['جُمْلَةٌ', 'قَصِيرَةٌ', 'جِدًّا'],
        },
        {
          'surah': 2,
          'verse': 2,
          'words': ['سَطْرٌ', 'آخَرُ'],
        },
      ]
    : [
        {
          'surah': 2,
          'verse': page * 2,
          'words': ['صَفْحَةٌ', 'رَقْمُ', 'كَذَا'],
        },
      ];

/// `GET learning/lessons/{session}/mushaf` (and what turning and marking answer).
Map<String, Object?> fxMushaf({
  int page = 2,
  int? teacherPage,
  String role = 'teacher',
  bool canMark = true,
  List<Map<String, Object?>> marks = const [],
  List<Map<String, Object?>> earlier = const [],
}) => {
  'data': {
    'session_id': 'l1',
    'role': role,
    'can_mark': canMark,
    'page': page,
    'teacher_page': teacherPage,
    'start_page': 2,
    'pages_total': 604,
    'juz': 1,
    'title': page == 2 ? 'صفحة $page · السورة الأولى، السورة التانية' : 'صفحة $page · السورة التانية',
    'surahs': [
      if (page == 2) {'number': 1, 'name_ar': 'السورة الأولى', 'name_en': 'First'},
      {'number': 2, 'name_ar': 'السورة التانية', 'name_en': 'Second'},
    ],
    'basmala': page == 2 ? ['بِاسْمِ', 'الْبِدَايَةِ'] : null,
    'verses': _verses(page),
    'marks': marks,
    'earlier': earlier,
    'kinds': fxMushafKinds,
    'attribution': 'Tanzil Quran Text',
  },
};

/// `GET learning/mistakes` (and a student's for their teacher): three words of the second surah, in two verses.
Map<String, Object?> fxMistakesBook({bool empty = false}) => {
  'data': {
    'learner_id': 'u-teen',
    'words': empty ? 0 : 3,
    'surahs': empty
        ? <Object>[]
        : [
            {
              'surah': 2,
              'name_ar': 'السورة التانية',
              'name_en': 'Second',
              'words': 3,
              'verses': [
                {
                  'verse': 1,
                  'words': ['جُمْلَةٌ', 'قَصِيرَةٌ', 'جِدًّا'],
                  'marks': [
                    {
                      'word': 2,
                      'times': 2,
                      'kinds': ['hesitation', 'wrong'],
                      'last_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
                    },
                    {
                      'word': 3,
                      'times': 1,
                      'kinds': ['tajweed'],
                      'last_at': time('2099-10-06T16:00:00Z', '2099-10-06T19:00:00+03:00', label: 'الثلاثاء 6 أكتوبر'),
                    },
                  ],
                },
                {
                  'verse': 2,
                  'words': ['سَطْرٌ', 'آخَرُ'],
                  'marks': [
                    {
                      'word': 1,
                      'times': 1,
                      'kinds': ['wrong'],
                      'last_at': time('2099-10-01T16:00:00Z', '2099-10-01T19:00:00+03:00', label: 'الخميس 1 أكتوبر'),
                    },
                  ],
                },
              ],
            },
          ],
    'kinds': fxMushafKinds,
  },
};
