import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/realtime/unread_count_cubit.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// The signed-in frame: the current tab plus the bottom navigation.
///
/// Tabs (SCREENS.md §3.1): Home, Participating («مشاركاتي»), My
/// competitions («منافساتي»), Notifications (unread badge), Account. Each
/// tab keeps its own navigation stack.
class AppShell extends StatelessWidget {
  const AppShell({required this.navigationShell, super.key});

  final StatefulNavigationShell navigationShell;

  void _select(int index) => navigationShell.goBranch(
    index,
    // Tapping the active tab returns to its root.
    initialLocation: index == navigationShell.currentIndex,
  );

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final unread = context.watch<UnreadCountCubit>().state;
    final scheme = Theme.of(context).colorScheme;
    // Red is for destructive actions and errors only (brand rules): the
    // count uses the primary colour, and is announced once.
    Widget badged(IconData icon) => Semantics(
      label: unread > 0 ? l10n.navNotificationsUnread(unread) : null,
      child: ExcludeSemantics(
        child: Badge(
          isLabelVisible: unread > 0,
          backgroundColor: scheme.primary,
          textColor: scheme.onPrimary,
          label: Text(unread > 99 ? '99+' : '$unread'),
          child: Icon(icon),
        ),
      ),
    );

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          border: BorderDirectional(
            top: BorderSide(
              color: Theme.of(context).colorScheme.outlineVariant,
            ),
          ),
        ),
        child: NavigationBar(
          selectedIndex: navigationShell.currentIndex,
          onDestinationSelected: _select,
          destinations: [
            NavigationDestination(
              icon: const Icon(Icons.home_outlined),
              selectedIcon: const Icon(Icons.home_rounded),
              label: l10n.navHome,
            ),
            NavigationDestination(
              icon: const Icon(Icons.local_offer_outlined),
              selectedIcon: const Icon(Icons.local_offer_rounded),
              label: l10n.navCompetitions,
            ),
            NavigationDestination(
              icon: const Icon(Icons.campaign_outlined),
              selectedIcon: const Icon(Icons.campaign_rounded),
              label: l10n.navMyCompetitionsTab,
            ),
            NavigationDestination(
              icon: badged(Icons.notifications_none_rounded),
              selectedIcon: badged(Icons.notifications_rounded),
              label: l10n.navNotifications,
            ),
            NavigationDestination(
              icon: const Icon(Icons.person_outline_rounded),
              selectedIcon: const Icon(Icons.person_rounded),
              label: l10n.navAccount,
            ),
          ],
        ),
      ),
    );
  }
}
