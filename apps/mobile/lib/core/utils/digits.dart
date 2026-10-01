import 'package:flutter/services.dart';

/// Converts Arabic-Indic (٠–٩) and Persian (۰–۹) digits to Western digits
/// (CONVENTIONS.md §6.1: inputs accept them; the app always shows 0–9).
String normalizeDigits(String input) {
  final out = StringBuffer();
  for (final rune in input.runes) {
    if (rune >= 0x0660 && rune <= 0x0669) {
      out.writeCharCode(0x30 + rune - 0x0660);
    } else if (rune >= 0x06F0 && rune <= 0x06F9) {
      out.writeCharCode(0x30 + rune - 0x06F0);
    } else {
      out.writeCharCode(rune);
    }
  }
  return out.toString();
}

/// Keeps only digits (after normalising Arabic-Indic and Persian ones), up to
/// [maxLength] when given. For CR, VAT, OTP, phone and address numbers.
class DigitsOnlyFormatter extends TextInputFormatter {
  DigitsOnlyFormatter({this.maxLength});

  final int? maxLength;

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var digits = normalizeDigits(newValue.text).replaceAll(RegExp(r'\D'), '');
    final limit = maxLength;
    if (limit != null && digits.length > limit) {
      digits = digits.substring(0, limit);
    }
    if (digits == newValue.text) return newValue;
    return TextEditingValue(
      text: digits,
      selection: TextSelection.collapsed(offset: digits.length),
    );
  }
}

/// Normalises digits and the Arabic decimal and thousands separators in
/// amount inputs, and drops anything that cannot be part of an amount.
class AmountInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final text = normalizeDigits(newValue.text)
        .replaceAll('٫', '.')
        .replaceAll('٬', ',')
        .replaceAll(RegExp(r'[^0-9.,]'), '');
    if (text == newValue.text) return newValue;
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
