import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/money/money.dart';
import 'package:bafo/core/money/money_format.dart';
import 'package:bafo/core/theme/typography.dart';
import 'package:material_ui/material_ui.dart';

/// A formatted amount (`1,250.50 ر.س` / `SAR 1,250.50`) with tabular figures
/// so live price updates do not shift the layout.
class MoneyText extends StatelessWidget {
  const MoneyText(this.money, {this.style, this.textAlign, super.key});

  final Money money;
  final TextStyle? style;

  final TextAlign? textAlign;

  @override
  Widget build(BuildContext context) {
    final base = style ?? DefaultTextStyle.of(context).style;
    return Text(
      MoneyFormat.format(money, languageCode: context.languageCode),
      style: BafoTypography.tabular(base),
      textAlign: textAlign,
      maxLines: 1,
      softWrap: false,
    );
  }
}
