import '../../../shared/widgets/latin_digits.dart';

/// What the one sign-in field holds, told apart as it is typed.
enum ContactKind { none, phone, email }

/// The countries whose code the field offers, in this order (the code the
/// phone's clock suggests comes first on screen).
const countryCodes = ['+966', '+20', '+971', '+965', '+974', '+973', '+968', '+962', '+212', '+1', '+44'];

/// A mobile number or an email typed in one field: which it is, whether it
/// looks complete, and the form the API takes (a number with its country
/// code, without the leading zeros of the national form).
abstract final class Contact {
  static ContactKind kindOf(String text) {
    final t = latinDigits(text).trim();
    if (t.isEmpty) return ContactKind.none;
    if (t.contains('@') || RegExp('[A-Za-z]').hasMatch(t)) return ContactKind.email;
    if (RegExp(r'[\d+]').hasMatch(t)) return ContactKind.phone;
    return ContactKind.email;
  }

  /// The number carries its own country code (+20…, 0020…).
  static bool international(String text) {
    final t = latinDigits(text).trim();
    return t.startsWith('+') || t.startsWith('00');
  }

  static String _digits(String text) => latinDigits(text).replaceAll(RegExp(r'\D'), '');

  /// The number as the API takes it: its own +code, or [country] and the
  /// national number without its leading zeros.
  static String phone(String text, String country) {
    final d = _digits(text);
    final t = latinDigits(text).trim();
    if (t.startsWith('+')) return '+$d';
    if (t.startsWith('00')) return '+${d.substring(2)}';
    return '$country${d.replaceFirst(RegExp('^0+'), '')}';
  }

  /// Long enough to be a mobile number (a national one has at least 7 digits).
  static bool phoneComplete(String text) {
    final d = _digits(text);
    return international(text) ? d.replaceFirst(RegExp('^00'), '').length >= 8 : d.replaceFirst(RegExp('^0+'), '').length >= 7;
  }

  static bool emailComplete(String text) => RegExp(r'^[^@\s]+@[^@\s.]+(\.[^@\s.]+)*\.[^@\s.]{2,}$').hasMatch(latinDigits(text).trim());

  /// The country a phone's clock suggests.
  static String countryFor(String? zone) => switch (zone) {
    'Africa/Cairo' => '+20',
    'Asia/Dubai' => '+971',
    'Asia/Kuwait' => '+965',
    'Asia/Qatar' => '+974',
    'Asia/Bahrain' => '+973',
    'Asia/Muscat' => '+968',
    'Asia/Amman' => '+962',
    'Africa/Casablanca' => '+212',
    'Europe/London' => '+44',
    final String z when z.startsWith('America/') => '+1',
    _ => '+966',
  };
}
