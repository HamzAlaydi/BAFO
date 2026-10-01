import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('MoneyFormat', () {
    test('Arabic puts the amount first and uses ر.س', () {
      expect(
        MoneyFormat.format(const Money(125050), languageCode: 'ar'),
        '1,250.50 ر.س',
      );
    });

    test('English prefixes SAR', () {
      expect(
        MoneyFormat.format(const Money(125050), languageCode: 'en'),
        'SAR 1,250.50',
      );
    });

    test('always uses Western digits, also in Arabic', () {
      final text = MoneyFormat.format(
        const Money(987654321),
        languageCode: 'ar',
      );
      expect(text, '9,876,543.21 ر.س');
      expect(RegExp('[٠-٩]').hasMatch(text), isFalse);
    });

    test('pads halalas to two digits', () {
      expect(MoneyFormat.amountOnly(const Money(5)), '0.05');
      expect(MoneyFormat.amountOnly(const Money(0)), '0.00');
      expect(MoneyFormat.amountOnly(const Money(100)), '1.00');
    });

    test('always shows two decimals', () {
      expect(MoneyFormat.amountOnly(const Money(1200000)), '12,000.00');
    });

    test('formats negative amounts', () {
      expect(MoneyFormat.amountOnly(const Money(-123456)), '-1,234.56');
    });

    test('falls back to the ISO code for other currencies', () {
      expect(
        MoneyFormat.format(
          const Money(100, currency: 'USD'),
          languageCode: 'ar',
        ),
        '1.00 USD',
      );
    });
  });

  group('Money.tryParse', () {
    test('parses major units into halalas', () {
      expect(Money.tryParse('1250'), const Money(125000));
      expect(Money.tryParse('1,250.5'), const Money(125050));
      expect(Money.tryParse(' 0.05 '), const Money(5));
    });

    test('accepts Arabic-Indic digits and separators', () {
      expect(Money.tryParse('١٢٥٠٫٥'), const Money(125050));
      expect(Money.tryParse('١٬٢٥٠٫٧٥'), const Money(125075));
      expect(Money.tryParse('۱۲۳'), const Money(12300));
    });

    test('rejects invalid input', () {
      expect(Money.tryParse(''), isNull);
      expect(Money.tryParse('abc'), isNull);
      expect(Money.tryParse('12.345'), isNull);
      expect(Money.tryParse('-5'), isNull);
      expect(Money.tryParse('1.2.3'), isNull);
    });
  });

  group('Money', () {
    test('reads and writes the API shape', () {
      final money = Money.fromJson(const {
        'amount_minor': 125050,
        'currency': 'SAR',
      });
      expect(money, const Money(125050));
      expect(money.toJson(), {'amount_minor': 125050, 'currency': 'SAR'});
    });

    test('rejects a non-integer amount', () {
      expect(
        () => Money.fromJson(const {'amount_minor': 12.5, 'currency': 'SAR'}),
        throwsFormatException,
      );
    });

    test('adds, subtracts and compares in integers', () {
      const a = Money(1000);
      const b = Money(250);
      expect(a + b, const Money(1250));
      expect(a - b, const Money(750));
      expect(b < a, isTrue);
      expect(a > b, isTrue);
    });

    test('refuses to mix currencies', () {
      expect(
        () => const Money(1) + const Money(1, currency: 'USD'),
        throwsArgumentError,
      );
    });
  });

  group('Vat', () {
    test('is 15% rounded half up to the halala (matches the API)', () {
      expect(Vat.of(const Money(10000)), const Money(1500));
      expect(Vat.of(const Money(3)), const Money(0)); // 0.45 halala
      expect(Vat.of(const Money(4)), const Money(1)); // 0.60 halala
      expect(Vat.of(const Money(10)), const Money(2)); // 1.50 halala
      expect(Vat.gross(const Money(125050)), const Money(143808));
    });
  });
}
