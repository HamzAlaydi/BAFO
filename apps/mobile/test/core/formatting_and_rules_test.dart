import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/money/amount_input.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/competitions/domain/competition_status_visual.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  late AppLocalizations ar;
  late AppLocalizations en;

  setUpAll(() async {
    await initializeDateFormatting();
    BafoDateFormat.useWesternDigits();
    ar = await AppLocalizations.delegate.load(const Locale('ar'));
    en = await AppLocalizations.delegate.load(const Locale('en'));
  });

  group('BafoDateFormat (S6)', () {
    // 2026-11-09 09:59:58.412 UTC = 12:59:58 in Riyadh.
    final instant = DateTime.utc(2026, 11, 9, 9, 59, 58, 412);

    test('Gregorian, Arabic month names, Riyadh time', () {
      expect(BafoDateFormat.longDate(instant, 'ar'), '9 نوفمبر 2026');
      expect(BafoDateFormat.longDate(instant, 'en'), '9 Nov 2026');
      expect(BafoDateFormat.time(instant, 'ar'), '12:59 م');
      expect(BafoDateFormat.time(instant, 'en'), '12:59 PM');
      expect(BafoDateFormat.dateTime(instant, 'ar'), '9 نوفمبر 2026، 12:59 م');
      expect(BafoDateFormat.dateTime(instant, 'en'), '9 Nov 2026, 12:59 PM');
      expect(
        BafoDateFormat.deadline(instant, 'ar', ar),
        '9 نوفمبر 2026، 12:59 م بتوقيت الرياض',
      );
      expect(
        BafoDateFormat.deadline(instant, 'en', en),
        '9 Nov 2026, 12:59 PM Riyadh time',
      );
    });

    test('machine format with milliseconds', () {
      expect(BafoDateFormat.machine(instant), '2026-11-09 12:59:58.412 (KSA)');
      expect(BafoDateFormat.timeWithMillis(instant), '12:59:58.412');
    });

    test('relative times (< 7 days), then the long date', () {
      String relative(Duration age, AppLocalizations l10n, String code) =>
          BafoDateFormat.relative(
            instant.subtract(age),
            now: instant,
            languageCode: code,
            l10n: l10n,
          );
      expect(relative(const Duration(seconds: 20), ar, 'ar'), 'الآن');
      expect(relative(const Duration(minutes: 5), ar, 'ar'), 'منذ 5 دقائق');
      expect(relative(const Duration(minutes: 2), ar, 'ar'), 'منذ دقيقتين');
      expect(relative(const Duration(minutes: 5), en, 'en'), '5 minutes ago');
      expect(relative(const Duration(hours: 1), en, 'en'), '1 hour ago');
      expect(relative(const Duration(days: 3), ar, 'ar'), 'منذ 3 أيام');
      expect(relative(const Duration(days: 8), en, 'en'), '1 Nov 2026');
    });

    test('Riyadh wall clock ↔ UTC for date pickers', () {
      final wall = BafoDateFormat.toRiyadhWallClock(instant);
      expect(wall.hour, 12);
      expect(
        BafoDateFormat.fromRiyadhWallClock(DateTime(2026, 11, 9, 12, 59)),
        DateTime.utc(2026, 11, 9, 9, 59),
      );
    });

    test('R-M3: every formatted value uses Western digits only', () {
      final arabicIndic = RegExp('[٠-٩۰-۹]');
      for (var month = 1; month <= 12; month++) {
        final at = DateTime.utc(2026, month, 28, 20, 5);
        for (final text in [
          BafoDateFormat.longDate(at, 'ar'),
          BafoDateFormat.dateTime(at, 'ar'),
          BafoDateFormat.deadline(at, 'ar', ar),
          BafoDateFormat.relative(
            at,
            now: at.add(const Duration(days: 3)),
            languageCode: 'ar',
            l10n: ar,
          ),
        ]) {
          expect(text, isNot(contains(arabicIndic)), reason: text);
          expect(text, contains(RegExp('[0-9]')), reason: text);
        }
      }
    });
  });

  group('input rules (S7)', () {
    test('digits are normalised', () {
      expect(normalizeDigits('٠١٢٣٤٥٦٧٨٩'), '0123456789');
      expect(normalizeDigits('۱۲۳'), '123');
    });

    test('phone, CR, VAT, short address, OTP', () {
      expect(Validators.localPhone('512345678', en), isNull);
      expect(Validators.localPhone('٥١٢٣٤٥٦٧٨', en), isNull);
      expect(Validators.localPhone('412345678', en), en.validationPhone);
      expect(Validators.phone('+966512345678', en), isNull);
      expect(Validators.cr('1010000001', en), isNull);
      expect(Validators.cr('123', ar), ar.validationCr);
      expect(Validators.vat('300000000000003', en), isNull);
      expect(Validators.vat('300000000000004', en), en.validationVat);
      expect(Validators.optionalShortAddress('', en), isNull);
      expect(Validators.optionalShortAddress('abcd1234', en), isNull);
      expect(Validators.optionalShortAddress('AB1234', en), isNotNull);
      expect(Validators.optionalDigits('12345', 5, en), isNull);
      expect(Validators.optionalDigits('1234', 5, en), en.validationExactDigits(5));
      expect(Validators.otp('١٢٣٤٥٦', en), isNull);
      expect(Validators.otp('12345', en), en.validationOtp);
      expect(Validators.httpsUrl('http://a.sa', en), en.validationUrlHttps);
      expect(Validators.httpsUrl('https://a.sa', en), isNull);
      expect(Validators.email('a@b', ar), ar.validationEmail);
    });

    test('the password rule matches the server classes', () {
      expect(PasswordRules.of('Bafo-Mobile-2026!').allMet, isTrue);
      final weak = PasswordRules.of('password');
      expect(weak.length, isTrue);
      expect(weak.uppercase, isFalse);
      expect(weak.digit, isFalse);
      expect(weak.symbol, isFalse);
      // Arabic letters are not symbols.
      expect(PasswordRules.of('Abcdefg1ب').symbol, isFalse);
      expect(Validators.passwordConfirmation('a', 'b', en), en.validationPasswordMismatch);
    });

    test('amounts parse to halalas without floats', () {
      expect(parseAmountInput('1,250.5').amountMinor, 125050);
      expect(parseAmountInput('١٢٥٠٫٥').amountMinor, 125050);
      expect(parseAmountInput('').error, AmountInputError.empty);
      expect(parseAmountInput('1.234').error, AmountInputError.invalid);
      expect(parseAmountInput('0').error, AmountInputError.notPositive);
      expect(
        parseAmountInput('100.50', granularityMinor: 100).error,
        AmountInputError.granularity,
      );
      expect(parseAmountInput('100', granularityMinor: 100).amountMinor, 10000);
    });

    test('bound direction comes from the server rule', () {
      expect(Direction.tender.satisfiesBound(9800, 9800), isTrue);
      expect(Direction.tender.satisfiesBound(9801, 9800), isFalse);
      expect(Direction.auction.satisfiesBound(9801, 9800), isTrue);
      expect(Direction.auction.satisfiesBound(9799, 9800), isFalse);
    });
  });

  group('CompetitionStatusVisual (S2 table)', () {
    final now = DateTime.utc(2026, 11, 9, 12);

    CompetitionStatusVisual of(
      CompetitionStatus status, {
      CompetitionPhase? phase,
      Duration? left,
      int extensions = 0,
    }) => CompetitionStatusVisual.of(
      status: status,
      phase: phase,
      effectiveCloseAt: left == null ? null : now.add(left),
      extensionCount: extensions,
      serverNow: now,
    );

    test('status and phase map to key, tone and icon', () {
      final table = {
        of(CompetitionStatus.draft): (StatusVisualKey.draft, StatusVisualTone.neutral),
        of(CompetitionStatus.scheduled): (StatusVisualKey.scheduled, StatusVisualTone.info),
        of(CompetitionStatus.live, phase: CompetitionPhase.initial):
            (StatusVisualKey.live, StatusVisualTone.primarySoft),
        of(CompetitionStatus.live, phase: CompetitionPhase.open):
            (StatusVisualKey.live, StatusVisualTone.primarySoft),
        of(CompetitionStatus.live, phase: CompetitionPhase.finalWindow):
            (StatusVisualKey.finalWindow, StatusVisualTone.primarySoft),
        of(CompetitionStatus.live, phase: CompetitionPhase.sealed):
            (StatusVisualKey.liveSealed, StatusVisualTone.primarySoft),
        of(CompetitionStatus.closed): (StatusVisualKey.closed, StatusVisualTone.neutral),
        of(CompetitionStatus.bafoRound): (StatusVisualKey.bafoRound, StatusVisualTone.inverse),
        of(CompetitionStatus.awarded): (StatusVisualKey.awarded, StatusVisualTone.primarySolid),
        of(CompetitionStatus.notAwarded): (StatusVisualKey.notAwarded, StatusVisualTone.neutral),
        of(CompetitionStatus.cancelled): (StatusVisualKey.cancelled, StatusVisualTone.dangerSoft),
      };
      table.forEach((visual, expected) {
        expect((visual.key, visual.tone), expected);
      });
      expect(
        of(CompetitionStatus.live, phase: CompetitionPhase.finalWindow).pulsing,
        isTrue,
      );
      expect(of(CompetitionStatus.live, phase: CompetitionPhase.sealed).icon, StatusVisualIcon.lock);
      expect(of(CompetitionStatus.cancelled).icon, StatusVisualIcon.ban);
    });

    test('overlay pills only while live', () {
      expect(of(CompetitionStatus.live, left: const Duration(minutes: 9)).closingSoon, isTrue);
      expect(of(CompetitionStatus.live, left: const Duration(minutes: 11)).closingSoon, isFalse);
      expect(of(CompetitionStatus.live, left: Duration.zero).closingSoon, isFalse);
      expect(of(CompetitionStatus.scheduled, left: const Duration(minutes: 5)).closingSoon, isFalse);
      expect(of(CompetitionStatus.live, extensions: 1).extended, isTrue);
      expect(of(CompetitionStatus.closed, extensions: 2).extended, isFalse);
    });
  });
}
