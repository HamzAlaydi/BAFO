import 'package:bafo/core/theme/theme.dart';
import 'package:bafo/core/theme/tokens.dart';
import 'package:bafo/widgets/brand_mark.dart';
import 'package:material_ui/material_ui.dart';

/// Shown while the session is restored; continues the native splash
/// (charcoal with the colour mark) so start-up has no visual jump.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion(
      value: BafoTheme.systemBarsOn(Brightness.dark),
      child: const Scaffold(
        backgroundColor: BafoTokens.brandCharcoal,
        body: Center(child: BrandMark(size: 120)),
      ),
    );
  }
}
