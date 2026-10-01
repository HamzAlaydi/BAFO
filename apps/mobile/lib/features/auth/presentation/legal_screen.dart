import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/time/bafo_date_format.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/legal_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M13: the latest published legal document in the app language, with its
/// version and date. Refetched when the language changes (the server
/// localises it).
class LegalScreen extends StatelessWidget {
  const LegalScreen({required this.code, super.key});

  final LegalCode code;

  @override
  Widget build(BuildContext context) => BlocProvider(
    // Keyed by language: a switch builds a new cubit and refetches.
    key: ValueKey(context.languageCode),
    create: (context) =>
        LegalCubit(context.read<LegalRepository>(), code)..load(),
    child: const _LegalView(),
  );
}

class _LegalView extends StatelessWidget {
  const _LegalView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    return BlocBuilder<LegalCubit, LegalState>(
      builder: (context, state) {
        final title = switch (state) {
          LegalLoaded(:final document) => document.title,
          _ => switch (context.read<LegalCubit>().code) {
            LegalCode.terms => l10n.legalLinksTerms,
            LegalCode.privacy => l10n.legalLinksPrivacy,
            LegalCode.competitionRules => l10n.legalLinksCompetitionRules,
            _ => '',
          },
        };
        return Scaffold(
          appBar: BafoAppBar(title: title),
          body: switch (state) {
            LegalInitial() || LegalLoading() => const LoadingSkeletonList(),
            LegalUnavailable() => NotFoundState(message: l10n.legalUnavailable),
            LegalFailure(:final error) => ErrorState(
              error: error,
              onRetry: context.read<LegalCubit>().load,
            ),
            LegalLoaded(:final document) => ListView(
              padding: BafoSpacing.pagePadding,
              children: [
                Wrap(
                  spacing: BafoSpacing.sm,
                  children: [
                    StatusPill(
                      label: l10n.legalVersion(ltrIsolate(document.version)),
                    ),
                    if (document.publishedAt != null)
                      StatusPill(
                        label: l10n.legalPublishedOn(
                          BafoDateFormat.longDate(
                            document.publishedAt!,
                            context.languageCode,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: BafoSpacing.lg),
                DefaultTextStyle.merge(
                  style: theme.textTheme.bodyLarge,
                  child: MarkdownView(data: document.bodyMarkdown),
                ),
              ],
            ),
          },
        );
      },
    );
  }
}
