import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/l10n/generated/app_localizations.dart';

/// Client-side field rules (SCREENS.md S7). They mirror the API rules for
/// feedback only; the server stays the authority and its field errors win.
///
/// Each validator returns a localised message or null.
abstract final class Validators {
  static final RegExp _email = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
  static final RegExp _phone = RegExp(r'^\+9665\d{8}$');
  static final RegExp _localPhone = RegExp(r'^5\d{8}$');
  static final RegExp _cr = RegExp(r'^\d{10}$');
  static final RegExp _vat = RegExp(r'^3\d{13}3$');
  static final RegExp _shortAddress = RegExp(r'^[A-Z]{4}\d{4}$');

  static String? required(String? value, AppLocalizations l10n) =>
      (value ?? '').trim().isEmpty ? l10n.validationRequired : null;

  static String? email(String? value, AppLocalizations l10n) {
    final email = (value ?? '').trim();
    if (email.isEmpty) return l10n.validationRequired;
    return _email.hasMatch(email) ? null : l10n.validationEmail;
  }

  /// [value] is the E.164 number (`+9665XXXXXXXX`).
  static String? phone(String? value, AppLocalizations l10n) {
    final phone = (value ?? '').trim();
    if (phone.isEmpty) return l10n.validationRequired;
    return _phone.hasMatch(phone) ? null : l10n.validationPhone;
  }

  /// The 9 digits typed after the fixed `+966` prefix.
  static String? localPhone(String? value, AppLocalizations l10n) {
    final digits = normalizeDigits((value ?? '').trim());
    if (digits.isEmpty) return l10n.validationRequired;
    return _localPhone.hasMatch(digits) ? null : l10n.validationPhone;
  }

  static String? cr(String? value, AppLocalizations l10n) {
    final digits = normalizeDigits((value ?? '').trim());
    if (digits.isEmpty) return l10n.validationRequired;
    return _cr.hasMatch(digits) ? null : l10n.validationCr;
  }

  static String? vat(String? value, AppLocalizations l10n) {
    final digits = normalizeDigits((value ?? '').trim());
    if (digits.isEmpty) return l10n.validationRequired;
    return _vat.hasMatch(digits) ? null : l10n.validationVat;
  }

  static String? password(String? value, AppLocalizations l10n) {
    final password = value ?? '';
    if (password.isEmpty) return l10n.validationRequired;
    return PasswordRules.of(password).allMet ? null : l10n.validationPasswordRules;
  }

  static String? passwordConfirmation(
    String? value,
    String password,
    AppLocalizations l10n,
  ) {
    if ((value ?? '').isEmpty) return l10n.validationRequired;
    return value == password ? null : l10n.validationPasswordMismatch;
  }

  /// Optional https URL.
  static String? httpsUrl(String? value, AppLocalizations l10n) {
    final url = (value ?? '').trim();
    if (url.isEmpty) return null;
    final uri = Uri.tryParse(url);
    final ok = uri != null && uri.scheme == 'https' && uri.host.contains('.');
    return ok ? null : l10n.validationUrlHttps;
  }

  static String? maxLength(String? value, int max, AppLocalizations l10n) =>
      (value ?? '').trim().length > max ? l10n.validationMaxLength(max) : null;

  /// Optional numeric field of exactly [count] digits (address numbers).
  static String? optionalDigits(
    String? value,
    int count,
    AppLocalizations l10n,
  ) {
    final digits = normalizeDigits((value ?? '').trim());
    if (digits.isEmpty) return null;
    return RegExp('^\\d{$count}\$').hasMatch(digits)
        ? null
        : l10n.validationExactDigits(count);
  }

  /// Optional short national address, `ABCD1234`.
  static String? optionalShortAddress(String? value, AppLocalizations l10n) {
    final text = (value ?? '').trim().toUpperCase();
    if (text.isEmpty) return null;
    return _shortAddress.hasMatch(normalizeDigits(text))
        ? null
        : l10n.validationShortAddress;
  }

  static String? otp(String? value, AppLocalizations l10n) {
    final code = normalizeDigits((value ?? '').trim());
    return RegExp(r'^\d{6}$').hasMatch(code) ? null : l10n.validationOtp;
  }
}

/// The password rule (ARCHITECTURE.md §13.9), per requirement, for the live
/// checklist.
final class PasswordRules {
  const PasswordRules({
    required this.length,
    required this.lowercase,
    required this.uppercase,
    required this.digit,
    required this.symbol,
  });

  /// The same character classes as Laravel's `Password` rule: `\p{Ll}` and
  /// `\p{Lu}` for mixed case, `\p{N}` for numbers, `\p{P}\p{S}\p{Z}` for
  /// symbols.
  factory PasswordRules.of(String password) => PasswordRules(
    length: password.runes.length >= 8,
    lowercase: RegExp(r'\p{Ll}', unicode: true).hasMatch(password),
    uppercase: RegExp(r'\p{Lu}', unicode: true).hasMatch(password),
    digit: RegExp(r'\p{N}', unicode: true).hasMatch(password),
    symbol: RegExp(r'[\p{P}\p{S}\p{Z}]', unicode: true).hasMatch(password),
  );

  final bool length;
  final bool lowercase;
  final bool uppercase;
  final bool digit;
  final bool symbol;

  bool get allMet => length && lowercase && uppercase && digit && symbol;
}
