import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/widgets/bafo_button.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// Switches to the other language, named in its own script («العربية» /
/// "English"). Used before sign-in (welcome, login) and in app bars.
class LanguageSwitchButton extends StatelessWidget {
  const LanguageSwitchButton({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final isArabic = context.languageCode == 'ar';
    return Semantics(
      hint: l10n.commonLanguageSwitch,
      child: BafoButton.text(
        icon: Icons.language_rounded,
        label: isArabic ? l10n.commonLanguageEnglish : l10n.commonLanguageArabic,
        onPressed: () => context.read<LocaleCubit>().select(
          isArabic ? AppLocales.english : AppLocales.arabic,
        ),
      ),
    );
  }
}

/// Both languages as a segmented choice (welcome slide 1, account).
class LanguageSegmented extends StatelessWidget {
  const LanguageSegmented({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final locale = context.watch<LocaleCubit>().state;
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: BafoSpacing.xs),
      child: SizedBox(
        width: double.infinity,
        child: SegmentedButton<String>(
          showSelectedIcon: false,
          segments: [
            ButtonSegment(
              value: AppLocales.arabic.languageCode,
              label: Text(l10n.commonLanguageArabic),
            ),
            ButtonSegment(
              value: AppLocales.english.languageCode,
              label: Text(l10n.commonLanguageEnglish),
            ),
          ],
          selected: {locale.languageCode},
          onSelectionChanged: (selection) =>
              context.read<LocaleCubit>().select(Locale(selection.first)),
        ),
      ),
    );
  }
}
