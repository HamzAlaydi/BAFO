import 'package:material_ui/material_ui.dart';

/// An LTR island inside RTL layout (SCREENS.md S1): amounts, reference
/// numbers, e-mails, phones, CR/VAT numbers, codes and URLs keep their
/// left-to-right order in Arabic.
///
/// The island itself sits on the start edge of the **surrounding**
/// direction: when its parent gives it the full width (a stretched column,
/// an `Expanded`), an Arabic page shows `2026-09-29 23:26 (KSA)` on the right
/// like the text around it, not on the left. With loose or unbounded
/// constraints (in a `Row`, a `Wrap`) it takes its child's size, as before.
class Ltr extends StatelessWidget {
  const Ltr({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) => Align(
    alignment: AlignmentDirectional.centerStart.resolve(
      Directionality.of(context),
    ),
    widthFactor: 1,
    heightFactor: 1,
    child: Directionality(textDirection: TextDirection.ltr, child: child),
  );
}

/// Wraps [text] in Unicode directional isolates (U+2066 LRI … U+2069 PDI)
/// for LTR values composed into a translated sentence, e.g. an e-mail in
/// «أرسلنا رمزاً إلى …».
String ltrIsolate(String text) => '\u2066$text\u2069';

/// Wraps user-provided text of unknown direction (names, titles) in a
/// first-strong isolate (U+2068 FSI … U+2069 PDI) for composed strings, so
/// an Arabic name in an English line (or the reverse) keeps its neighbours
/// in order: «سارة · 40 minutes ago», not «40 · سارة minutes ago».
String bidiIsolate(String text) => '\u2068$text\u2069';
