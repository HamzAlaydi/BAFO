import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/auth/presentation/onboarding_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M02: three slides (create → invite → compete live and award) with the
/// language choice on the first. Shown once, on the first run without a
/// session. The art is text-free; captions are ARB strings.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({this.from, super.key});

  /// A protected location to restore after sign-in.
  final String? from;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => OnboardingCubit(context.read<PreferencesStore>()),
    child: _WelcomeView(from: from),
  );
}

class _WelcomeView extends StatefulWidget {
  const _WelcomeView({this.from});

  final String? from;

  @override
  State<_WelcomeView> createState() => _WelcomeViewState();
}

class _WelcomeViewState extends State<_WelcomeView> {
  final PageController _pages = PageController();

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  Future<void> _finish(String target) async {
    await context.read<OnboardingCubit>().complete();
    if (!mounted) return;
    final from = widget.from;
    context.go(
      from == null || target != AppRoutes.login
          ? target
          : Uri(path: target, queryParameters: {'from': from}).toString(),
    );
  }

  void _next(int page) {
    final disableAnimations = MediaQuery.disableAnimationsOf(context);
    if (disableAnimations) {
      _pages.jumpToPage(page + 1);
    } else {
      _pages.nextPage(
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final page = context.select<OnboardingCubit, int>((c) => c.state.page);
    final last = page == OnboardingCubit.pageCount - 1;
    final slides = [
      (Icons.campaign_outlined, l10n.authWelcomeSlide1Title, l10n.authWelcomeSlide1Body),
      (Icons.group_add_outlined, l10n.authWelcomeSlide2Title, l10n.authWelcomeSlide2Body),
      (Icons.gavel_outlined, l10n.authWelcomeSlide3Title, l10n.authWelcomeSlide3Body),
    ];

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsetsDirectional.symmetric(
                horizontal: BafoSpacing.sm,
              ),
              child: Row(
                children: [
                  // Keeps the header height when "Skip" disappears.
                  const SizedBox(height: BafoSizes.minTouchTarget),
                  const BrandMark(size: 32),
                  const Spacer(),
                  if (!last)
                    BafoButton.text(
                      key: const Key('welcome.skip'),
                      label: l10n.commonActionsSkip,
                      onPressed: () => _finish(AppRoutes.login),
                    ),
                ],
              ),
            ),
            Expanded(
              child: PageView(
                controller: _pages,
                onPageChanged: context.read<OnboardingCubit>().pageChanged,
                children: [
                  for (final (index, (icon, title, body)) in slides.indexed)
                    _Slide(
                      icon: icon,
                      title: title,
                      body: body,
                      header: index == 0
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  l10n.authWelcomeLanguageTitle,
                                  style: Theme.of(context).textTheme.titleSmall,
                                ),
                                const LanguageSegmented(),
                                const SizedBox(height: BafoSpacing.xl),
                              ],
                            )
                          : null,
                    ),
                ],
              ),
            ),
            Semantics(
              label: l10n.commonPageOf(page + 1, OnboardingCubit.pageCount),
              excludeSemantics: true,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 0; i < OnboardingCubit.pageCount; i++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      margin: const EdgeInsetsDirectional.symmetric(
                        horizontal: BafoSpacing.xs,
                      ),
                      width: i == page ? 20 : 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: i == page
                            ? Theme.of(context).colorScheme.primary
                            : Theme.of(context).colorScheme.surfaceContainerHigh,
                        borderRadius: BorderRadius.circular(BafoRadii.pill),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsetsDirectional.all(BafoSpacing.xl),
              child: last
                  ? Column(
                      children: [
                        BafoButton(
                          key: const Key('welcome.signIn'),
                          label: l10n.authLoginSubmit,
                          expand: true,
                          onPressed: () => _finish(AppRoutes.login),
                        ),
                        const SizedBox(height: BafoSpacing.md),
                        BafoButton.outline(
                          key: const Key('welcome.register'),
                          label: l10n.authWelcomeCreateAccount,
                          expand: true,
                          onPressed: () => _finish(AppRoutes.register),
                        ),
                      ],
                    )
                  : BafoButton(
                      key: const Key('welcome.next'),
                      label: l10n.commonActionsNext,
                      expand: true,
                      onPressed: () => _next(page),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Slide extends StatelessWidget {
  const _Slide({
    required this.icon,
    required this.title,
    required this.body,
    this.header,
  });

  final IconData icon;
  final String title;
  final String body;
  final Widget? header;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final brand = context.semanticColors;
    return SingleChildScrollView(
      padding: const EdgeInsetsDirectional.symmetric(horizontal: BafoSpacing.xl),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: BafoSpacing.lg),
          ?header,
          Center(
            child: ExcludeSemantics(
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  color: brand.leading.background,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 72, color: brand.brandGreen),
              ),
            ),
          ),
          const SizedBox(height: BafoSpacing.xl),
          Semantics(
            header: true,
            child: Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall,
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          Text(
            body,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}
