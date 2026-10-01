import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/live/data/clock_sync_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/l10n/generated/app_localizations.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/single_child_widget.dart';

import '../../helpers/fakes.dart';

class MockCompetitions extends Mock implements CompetitionsRepository {}

class MockAttachments extends Mock implements AttachmentsRepository {}

class MockInvitations extends Mock implements InvitationsRepository {}

class MockLive extends Mock implements LiveRepository {}

class MockComments extends Mock implements CommentsRepository {}

class NoopClockSync implements ClockSyncRepository {
  @override
  Future<void> sync() async {}
}

/// The fixtures' moment (the demo seed ran on 2026-09-29 around 17:47 UTC),
/// so countdowns and "live" states do not depend on the day the tests run.
final DateTime fixtureNow = DateTime.utc(2026, 9, 29, 18, 30);

AppLocalizations l10nOf(String languageCode) =>
    lookupAppLocalizations(Locale(languageCode));

/// Mocks, a realtime fake with its hub, and a signed-in Supplier A session
/// for the participant screens.
class ParticipantHarness {
  final MockCompetitions competitions = MockCompetitions();
  final MockAttachments attachments = MockAttachments();
  final MockInvitations invitations = MockInvitations();
  final MockLive live = MockLive();
  final MockComments comments = MockComments();
  final FakeRealtimeClient realtime = FakeRealtimeClient();
  final ServerClock clock = ServerClock(deviceNow: () => fixtureNow);
  late final CompetitionChannelHub hub = CompetitionChannelHub(realtime);
  late final SessionCubit session = SessionCubit(
    tokens: InMemoryTokenStore('token'),
    unauthorized: const Stream.empty(),
    fetchMe: () async => fixturePayload().me,
  );

  String get organizationId => fixturePayload().me.organization.id;

  Future<void> signIn() => session.restore();

  List<SingleChildWidget> providers() => [
    RepositoryProvider<ServerClock>.value(value: clock),
    RepositoryProvider<CompetitionsRepository>.value(value: competitions),
    RepositoryProvider<AttachmentsRepository>.value(value: attachments),
    RepositoryProvider<InvitationsRepository>.value(value: invitations),
    RepositoryProvider<LiveRepository>.value(value: live),
    RepositoryProvider<CommentsRepository>.value(value: comments),
    RepositoryProvider<CompetitionChannelHub>.value(value: hub),
    BlocProvider<SessionCubit>.value(value: session),
  ];

  Future<void> dispose() async {
    await session.close();
    await hub.dispose();
    await realtime.dispose();
  }
}

/// Every visible text on screen, for "no price / no link" checks.
Iterable<String> visibleTexts(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((text) => text.data ?? text.textSpan?.toPlainText() ?? '');
