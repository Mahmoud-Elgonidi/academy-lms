import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/core/time/clock_style.dart';
import 'package:academy_app/core/time/formats.dart';
import 'package:academy_app/core/time/local_time.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';

void main() {
  setUpAll(() async {
    await initializeDateFormatting('ar');
    await initializeDateFormatting('en');
    DateFormat.useNativeDigitsByDefaultFor('ar', false);
  });
  tearDown(() => ClockStyle.format = TimeFormat.h12);

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

  test('writes a clock the reader\'s way: 12 hours with the day\'s half by default, or 24', () {
    String en(String s) => s.replaceAll('\u202f', ' ');
    expect(ClockStyle.format, TimeFormat.h12);
    expect(t.clock('ar'), '7:00 م');
    expect(en(t.clock('en')), '7:00 PM');
    expect(t.hourMinute('ar'), '7:00');
    expect(t.dayPeriod('ar'), 'م');
    expect(t.dayPeriod('en'), 'PM');
    expect(wallClock('17:00', 'ar'), '5:00 م');
    expect(en(wallClock('17:00', 'en')), '5:00 PM');
    expect(en(wallClock('09:30', 'en')), '9:30 AM');
    // The day's end, as a shift keeps it.
    expect(wallClock('24:00', 'ar'), '12:00 ص');
    expect(wallRange('16:00', '20:00', 'ar'), '4:00 م – 8:00 م');

    ClockStyle.format = TimeFormat.h24;
    expect(t.clock('ar'), '19:00');
    expect(t.clock('en'), '19:00');
    expect(t.hourMinute('ar'), '19:00');
    expect(t.dayPeriod('ar'), '');
    expect(t.dayPeriod('en'), '');
    expect(wallClock('17:00', 'ar'), '17:00');
    expect(wallClock('9:05', 'en'), '09:05');
    expect(wallClock('24:00', 'ar'), '24:00');
    expect(wallRange('16:00', '20:00', 'ar'), '16:00–20:00');
    // Not a time: left as it came.
    expect(wallClock('soon', 'ar'), 'soon');
  });

  test('reads the way from the API: h12 or h24, else nothing', () {
    expect(TimeFormat.parse('h12'), TimeFormat.h12);
    expect(TimeFormat.parse('h24'), TimeFormat.h24);
    expect(TimeFormat.parse(null), isNull);
    expect(TimeFormat.parse('24'), isNull);
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

  testWidgets('words an amount of hours for its number', (tester) async {
    late BuildContext ar;
    late BuildContext en;
    Widget app(Locale locale, void Function(BuildContext) keep) => Localizations(
      locale: locale,
      delegates: AppLocalizations.localizationsDelegates,
      child: Builder(
        builder: (c) {
          keep(c);
          return const SizedBox();
        },
      ),
    );
    await tester.pumpWidget(Column(children: [app(const Locale('ar'), (c) => ar = c), app(const Locale('en'), (c) => en = c)]));
    expect([1, 2, 3, 10, 11, 30, 1.5].map((h) => hoursAmount(ar, h)), [
      'ساعة',
      'ساعتين',
      '3 ساعات',
      '10 ساعات',
      '11 ساعة',
      '30 ساعة',
      '1.5 ساعة',
    ]);
    expect([1, 3, 1.5].map((h) => hoursAmount(en, h)), ['1 hour', '3 hours', '1.5 hours']);
  });
}
