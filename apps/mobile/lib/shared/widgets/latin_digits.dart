import 'package:flutter/services.dart';

/// [text] with Arabic-Indic (٠–٩) and Persian (۰–۹) digits as 0–9, and, when
/// [decimal], the Arabic decimal separator (٫) and a comma as a point. Many
/// Arabic keyboards type those digits; codes, phone numbers and amounts go
/// to the API in Latin ones.
String latinDigits(String text, {bool decimal = false}) {
  final out = StringBuffer();
  for (final rune in text.runes) {
    if (rune >= 0x0660 && rune <= 0x0669) {
      out.writeCharCode(0x30 + rune - 0x0660);
    } else if (rune >= 0x06F0 && rune <= 0x06F9) {
      out.writeCharCode(0x30 + rune - 0x06F0);
    } else if (decimal && (rune == 0x066B || rune == 0x2C)) {
      out.write('.');
    } else {
      out.writeCharCode(rune);
    }
  }
  return out.toString();
}

/// Turns digits typed on an Arabic keyboard into Latin ones as they are
/// typed; put it before any filter that keeps only 0–9.
class LatinDigits extends TextInputFormatter {
  const LatinDigits({this.decimal = false});

  /// Also the decimal separator (amounts, hours).
  final bool decimal;

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final text = latinDigits(newValue.text, decimal: decimal);
    // Each digit stays one UTF-16 unit, so the selection keeps its place.
    return text == newValue.text ? newValue : newValue.copyWith(text: text);
  }
}
