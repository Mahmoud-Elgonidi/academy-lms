import 'package:flutter/widgets.dart';
import 'package:intl/intl.dart';

import '../l10n/l10n.dart';
import 'local_time.dart';

/// Showing the API's times: always on the wall clock the API wrote them in
/// (the viewer's zone), never converted again on the phone.
extension LocalTimeFormats on LocalTime {
  /// The wall-clock reading as a plain DateTime (no zone), for formatting only.
  DateTime get wall =>
      DateTime(int.parse(local.substring(0, 4)), int.parse(local.substring(5, 7)), int.parse(local.substring(8, 10)), hour, minute);

  /// The zone's offset at this instant, from the `local` string ("+03:00").
  Duration get offset {
    final m = RegExp(r'([+-])(\d{2}):(\d{2})$').firstMatch(local);
    if (m == null) return Duration.zero;
    final d = Duration(hours: int.parse(m.group(2)!), minutes: int.parse(m.group(3)!));
    return m.group(1) == '-' ? -d : d;
  }

  /// "7:00 م" / "7:00 PM".
  String clock(String locale) => DateFormat.jm(locale).format(wall);

  /// "7:00" without the time of day, for a list's time column.
  String hourMinute(String locale) => DateFormat('h:mm', locale).format(wall);

  /// "م" / "PM": the time of day that goes with [hourMinute].
  String dayPeriod(String locale) => DateFormat('a', locale).format(wall);

  /// "الأربعاء 7 أكتوبر" / "Wednesday 7 October".
  String day(String locale) => DateFormat('EEEE d MMMM', locale).format(wall);

  /// "الأربعاء 7 أكتوبر 2026".
  String date(String locale) => DateFormat('d MMMM y', locale).format(wall);

  /// "Today", "Tomorrow" or the day, on the viewer's calendar.
  String relativeDay(BuildContext context) {
    final now = DateTime.now().toUtc().add(offset);
    final today = DateTime(now.year, now.month, now.day);
    final that = DateTime(wall.year, wall.month, wall.day);
    final days = that.difference(today).inDays;
    if (days == 0) return context.l10n.today;
    if (days == 1) return context.l10n.tomorrow;
    return day(context.locale);
  }

  /// "Today · 7:00 PM".
  String when(BuildContext context) => '${relativeDay(context)} · ${clock(context.locale)}';

  Duration fromNow() => utc.difference(DateTime.now().toUtc());
}

/// "12 minutes", "2 hours" for a wait.
String waitText(BuildContext context, Duration d) {
  final minutes = d.inMinutes < 1 ? 1 : d.inMinutes;
  if (minutes < 60) return context.l10n.inMinutes(minutes);
  return context.l10n.inHours((minutes / 60).round());
}

/// "1.5" hours from minutes, without a trailing ".0".
String hoursText(int minutes) {
  final h = minutes / 60;
  return h == h.roundToDouble() ? h.toStringAsFixed(0) : h.toStringAsFixed(1);
}

/// "3 ساعات", "ساعتين", "1.5 ساعة": an amount of hours, worded for its number.
String hoursAmount(BuildContext context, num hours) {
  if (hours == hours.roundToDouble()) return context.l10n.inHours(hours.round());
  return context.l10n.hours(hours.toStringAsFixed(1));
}

/// "Asia/Riyadh" → "الرياض" / "Riyadh": how people name a zone.
String zoneName(String? zone, [String locale = 'en']) {
  if (zone == null || zone.isEmpty) return '';
  final city = zone.split('/').last.replaceAll('_', ' ');
  return locale == 'ar' ? (_arabicCities[city] ?? city) : city;
}

const _arabicCities = {
  'Riyadh': 'الرياض',
  'Cairo': 'القاهرة',
  'Dubai': 'دبي',
  'Kuwait': 'الكويت',
  'Qatar': 'الدوحة',
  'Bahrain': 'البحرين',
  'Muscat': 'مسقط',
  'Amman': 'عمّان',
  'Baghdad': 'بغداد',
  'Beirut': 'بيروت',
  'Damascus': 'دمشق',
  'Jerusalem': 'القدس',
  'Gaza': 'غزة',
  'Khartoum': 'الخرطوم',
  'Tripoli': 'طرابلس',
  'Tunis': 'تونس',
  'Algiers': 'الجزائر',
  'Casablanca': 'الدار البيضاء',
  'Istanbul': 'إسطنبول',
  'London': 'لندن',
  'Paris': 'باريس',
  'Berlin': 'برلين',
  'Amsterdam': 'أمستردام',
  'Stockholm': 'ستوكهولم',
  'Madrid': 'مدريد',
  'Rome': 'روما',
  'New York': 'نيويورك',
  'Chicago': 'شيكاغو',
  'Denver': 'دنفر',
  'Los Angeles': 'لوس أنجلوس',
  'Toronto': 'تورونتو',
  'Vancouver': 'فانكوفر',
  'Sydney': 'سيدني',
  'Kuala Lumpur': 'كوالالمبور',
  'Jakarta': 'جاكرتا',
  'Karachi': 'كراتشي',
  'UTC': 'UTC',
};
