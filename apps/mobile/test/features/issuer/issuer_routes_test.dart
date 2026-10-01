import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/issuer/issuer.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'issuer_test_helpers.dart';

/// The issuer routes inside the real app router (SCREENS.md §3.1).
void main() {
  late SessionCubit session;
  late AppGateCubit gate;
  late GoRouter router;

  setUp(() {
    session = SessionCubit(
      tokens: InMemoryTokenStore(),
      unauthorized: const Stream.empty(),
      fetchMe: () async => fixtureMe(),
    );
    gate = AppGateCubit();
    router = createAppRouter(session: session, gate: gate);
  });

  tearDown(() async {
    router.dispose();
    await session.close();
    await gate.close();
  });

  String lastPath(String location) {
    final matches = router.configuration.findMatch(Uri.parse(location));
    expect(matches.isError, isFalse, reason: location);
    return matches.last.route.path;
  }

  test('the tab and the wizard', () {
    expect(lastPath(IssuerPaths.list), '/my-competitions');
    expect(lastPath(IssuerPaths.create), 'new');
  });

  test('issuer-only children resolve after the shared detail routes', () {
    for (final child in [
      'offers',
      'participants',
      'invite',
      'attachments',
      'edit',
      'award',
    ]) {
      final path = lastPath('/competitions/01j9example/$child');
      expect(path, endsWith(child), reason: child);
    }
  });

  test('the shared paths stay with the viewer-role dispatch', () {
    expect(
      lastPath(IssuerPaths.competition('01j9example')),
      isNot(contains('offers')),
    );
    expect(lastPath(IssuerPaths.live('01j9example')), 'live');
    expect(lastPath(IssuerPaths.qa('01j9example')), 'qa');
  });
}
