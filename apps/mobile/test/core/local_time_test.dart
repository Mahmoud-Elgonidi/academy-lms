import 'package:academy_app/core/time/formats.dart';
import 'package:academy_app/core/time/local_time.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

void main() {
  setUpAll(() async {
    await initializeDateFormatting('ar');
    await initializeDateFormatting('en');
    DateFormat.useNativeDigitsByDefaultFor('ar', false);
  });

  final t = LocalTime.fromJson({
    'utc': '2026-10-07T16:00:00Z',
    'local': '2026-10-07T19:00:00+03:00',
    'timezone': 'Asia/Riyadh',
    'label': 'الأربعاء 7 أكتوبر · 19:00',
  })!;

  test('keeps the wall clock the API wrote, never converting again', () {
    expect(t.localDate, '2026-10-07');
    expect(t.hhmm, '19:00');
    expect(t.wall, DateTime(2026, 10, 7, 19));
    expect(t.offset, const Duration(hours: 3));
    expect(t.utc, DateTime.utc(2026, 10, 7, 16));
  });

  test('formats in each language with western digits', () {
    expect(t.clock('ar'), '7:00 م');
    expect(t.clock('en').replaceAll('\u202f', ' '), '7:00 PM');
    expect(t.day('ar'), 'الأربعاء 7 أكتوبر');
    expect(t.day('en'), 'Wednesday 7 October');
  });

  test('negative offsets and missing values', () {
    final ny = LocalTime.fromJson({
      'utc': '2026-11-06T09:19:24Z',
      'local': '2026-11-06T04:19:24-05:00',
      'timezone': 'America/New_York',
      'label': '',
    })!;
    expect(ny.offset, const Duration(hours: -5));
    expect(ny.hour, 4);
    expect(LocalTime.fromJson(null), isNull);
  });

  test('names zones as people do', () {
    expect(zoneName('Asia/Riyadh', 'ar'), 'الرياض');
    expect(zoneName('America/New_York', 'en'), 'New York');
    expect(zoneName('Pacific/Chatham', 'ar'), 'Chatham');
    expect(hoursText(90), '1.5');
    expect(hoursText(120), '2');
  });
}
