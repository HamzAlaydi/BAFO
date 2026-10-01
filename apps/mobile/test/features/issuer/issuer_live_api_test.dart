import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/issuer/data/issuer_report_repository.dart';
import 'package:bafo/features/issuer/presentation/attachments/manage_attachments_cubit.dart';
import 'package:bafo/features/issuer/presentation/award/award_cubits.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_action_cubits.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/edit/edit_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/invite/invite_participants_cubit.dart';
import 'package:bafo/features/issuer/presentation/live/issuer_live_bloc.dart';
import 'package:bafo/features/issuer/presentation/offers_log/offers_log_bloc.dart';
import 'package:bafo/features/issuer/presentation/participants/invitations_cubit.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

/// The issuer flows against a running API (not part of the default run):
/// create → edit → invite (and the all-or-nothing duplicate error) →
/// publish checks → upload and delete a document → delete the draft, then
/// the read-only detail, live monitor, offers log, award and result PDF of
/// the demo competitions.
///
/// ```sh
/// BAFO_LIVE_API=http://localhost:8000/api/app/v1 \
/// BAFO_ISSUER_EMAIL=… BAFO_DEMO_PASSWORD=… \
/// flutter test test/features/issuer/issuer_live_api_test.dart
/// ```
///
/// The credentials are the demo accounts of docs/build/DEMO.md, read from
/// the environment. The draft it creates is deleted at the end.
void main() {
  final base = Platform.environment['BAFO_LIVE_API'];
  final email = Platform.environment['BAFO_ISSUER_EMAIL'];
  final password = Platform.environment['BAFO_DEMO_PASSWORD'];
  final skip = base == null || email == null || password == null
      ? 'Set BAFO_LIVE_API, BAFO_ISSUER_EMAIL and BAFO_DEMO_PASSWORD'
      : null;

  test(
    'issuer flows against the running API',
    () async {
      final apiBase = Uri.parse(base!);
      final env = Env(
        apiBaseUrl: apiBase,
        broadcastingAuthUrl: apiBase.replace(path: '/broadcasting/auth'),
        realtime: const RealtimeConfig(
          appKey: '',
          host: 'localhost',
          port: 8085,
          useTls: false,
        ),
      );
      final tokens = InMemoryTokenStore();
      final clock = ServerClock();
      final api = ApiClient.create(
        env: env,
        tokens: tokens,
        clock: clock,
        languageCode: () => 'ar',
        onUnauthorized: () => fail('unexpected 401'),
        client: const ClientInfo(platform: 'android', appVersion: '1.0.0'),
      );
      final payload = await ApiAuthRepository(
        api,
        deviceName: 'issuer-live-test · android',
      ).login(email: email!, password: password!);
      await tokens.write(payload.token);
      await api.get('time');

      final competitions = ApiCompetitionsRepository(api);
      final attachments = ApiAttachmentsRepository(api);
      final invitations = ApiInvitationsRepository(api);
      final lookups = ApiLookupsRepository(api, languageCode: () => 'ar');
      final live = ApiLiveRepository(api);
      final realtime = FakeRealtimeClient();
      final hub = CompetitionChannelHub(realtime);
      void log(String line) {
        // ignore: avoid_print
        print(line);
      }

      // ── Create (M34–M37) ──
      final create = CreateCompetitionCubit(
        competitions: competitions,
        lookups: lookups,
        now: clock.now,
        auctionEnabled: payload.me.organization.features.auctionEnabled,
      );
      await create.load();
      final loaded = (create.state as CreateCompetitionEditing).lookups;
      create
        ..setDirection(Direction.tender)
        ..setFormat(CompetitionFormat.live);
      expect(create.next(), isTrue);
      create.update(
        (form) => form.copyWith(
          title: 'مسودة اختبار التطبيق (تُحذف تلقائيًا)',
          description: 'نطاق العمل للاختبار.',
          categoryId: () => loaded.categories.first.id,
          regionId: () => loaded.regions.first.id,
        ),
      );
      expect(create.next(), isTrue);
      create.update(
        (form) => form.copyWith(
          startPriceMinor: () => 10000000,
          reservePriceMinor: () => 9000000,
          scheduledCloseAt: () => clock.now().add(const Duration(days: 5)),
        ),
      );
      expect(create.next(), isTrue);
      await create.submit();
      final draft = (create.state as CreateCompetitionSaved).competition;
      await create.close();
      log(
        'Created draft ${draft.id} (${draft.presetCode}), rules summary '
        '${draft.rulesSummary.length} lines',
      );
      expect(draft.status, CompetitionStatus.draft);
      expect(draft.rules.startPriceMinor, 10000000);
      expect(draft.rules.reservePriceMinor, 9000000);

      try {
        // ── Edit (M39) ──
        final edit = EditCompetitionCubit(
          competitions: competitions,
          lookups: lookups,
          now: clock.now,
          competitionId: draft.id,
        );
        await edit.load();
        edit.update(
          (form) => form.copyWith(
            title: 'مسودة اختبار التطبيق (معدّلة)',
            startPriceMinor: () => 12000000,
          ),
        );
        await edit.save();
        final edited = (edit.state as EditCompetitionSaved).competition;
        await edit.close();
        expect(edited.rules.startPriceMinor, 12000000);
        expect(edited.rules.minStepBps, draft.rules.minStepBps);
        log('Edited: ${edited.title}');

        // ── Invite (M40), then the all-or-nothing duplicate ──
        final address =
            'issuer-live-${DateTime.now().millisecondsSinceEpoch}@example.test';
        final invite = InviteParticipantsCubit(
          competitions: competitions,
          invitations: invitations,
          vendors: ApiVendorsRepository(api),
          competitionId: draft.id,
        );
        await invite.load();
        final suggestionsCount =
            (invite.state as InviteReady).suggestions.items.length;
        invite.addEmails(address);
        await invite.submit();
        expect(invite.state, isA<InviteSent>());
        await invite.close();

        final duplicate = InviteParticipantsCubit(
          competitions: competitions,
          invitations: invitations,
          vendors: ApiVendorsRepository(api),
          competitionId: draft.id,
        );
        await duplicate.load();
        duplicate.addEmails('$address other-$address');
        await duplicate.submit();
        final rejected = duplicate.state as InviteReady;
        log('Duplicate rows: ${rejected.rowErrors}');
        expect(
          rejected.rowErrors['email:$address']?.code,
          'invitation_duplicate',
        );
        await duplicate.close();
        log('Invited $address ($suggestionsCount suggestions offered)');

        // ── Participants (M41) ──
        final participants = InvitationsCubit(
          competitions: competitions,
          invitations: invitations,
          hub: hub,
          competitionId: draft.id,
        );
        await participants.load();
        final rows = participants.state as InvitationsLoaded;
        expect(rows.invitations.map((row) => row.email), contains(address));
        await participants.close();

        // ── Publish checks (M43): one invitation of two ──
        final publish = PublishCubit(
          competitions: competitions,
          competitionId: draft.id,
        );
        await publish.publish();
        final failure = publish.state;
        log('Publish: $failure');
        expect(failure, isA<PublishFailed>());
        expect((failure as PublishFailed).missingInvitations, 1);
        await publish.close();

        // ── Documents (M42) ──
        final dir = await Directory.systemTemp.createTemp('bafo-issuer');
        final pdf = File('${dir.path}/specs.pdf')
          ..writeAsBytesSync(
            '%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'.codeUnits,
          );
        final documents = ManageAttachmentsCubit(
          competitions: competitions,
          attachments: attachments,
          competitionId: draft.id,
        );
        await documents.load();
        await documents.upload(
          PickedDocument(
            path: pdf.path,
            name: 'specs.pdf',
            size: pdf.lengthSync(),
          ),
        );
        final uploaded = documents.state as ManageAttachmentsLoaded;
        log(
          'Uploads: ${uploaded.uploads}, attachments ${uploaded.attachments.length}',
        );
        expect(uploaded.attachments, hasLength(1));
        await documents.delete(uploaded.attachments.single);
        expect(
          (documents.state as ManageAttachmentsLoaded).attachments,
          isEmpty,
        );
        await documents.close();
        await dir.delete(recursive: true);
      } finally {
        // ── Delete draft (M45) ──
        final detail = IssuerCompetitionCubit(
          competitions: competitions,
          attachments: attachments,
          hub: hub,
          competitionId: draft.id,
        );
        await detail.load();
        await detail.deleteDraft();
        expect(detail.state, const IssuerCompetitionDeleted());
        await detail.close();
        log('Deleted draft ${draft.id}');
      }

      // ── The demo competitions (read-only views) ──
      final issued = await competitions.list(
        role: CompetitionListRole.issuer,
        perPage: 50,
      );
      String idOf(CompetitionStatus status, {CompetitionFormat? format}) =>
          issued.items
              .firstWhere(
                (item) =>
                    item.status == status &&
                    (format == null || item.format == format),
              )
              .id;

      final liveId = idOf(
        CompetitionStatus.live,
        format: CompetitionFormat.live,
      );
      final monitor = IssuerLiveBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        now: clock.now,
        competitionId: liveId,
      )..add(const IssuerLiveOpened());
      final monitored = await monitor.stream.firstWhere(
        (state) => state is IssuerLiveLoaded && state.snapshot != null,
      );
      final snapshot = (monitored as IssuerLiveLoaded).snapshot!;
      log(
        'Live monitor v${snapshot.v}: leader ${snapshot.leader?.aliasNo}, '
        '${snapshot.ranking.length} ranked, online ${snapshot.onlineParticipantsCount}',
      );
      await monitor.close();

      final offersLog = OffersLogBloc(
        competitions: competitions,
        live: live,
        hub: hub,
        competitionId: liveId,
      )..add(const OffersLogStarted());
      final logged = await offersLog.stream.firstWhere(
        (state) => state is OffersLogLoaded || state is OffersLogFailure,
      );
      expect(logged, isA<OffersLogLoaded>());
      log('Offers log: ${(logged as OffersLogLoaded).entries.length} entries');
      await offersLog.close();

      final awardedId = idOf(CompetitionStatus.awarded);
      final award = AwardCubit(
        competitions: competitions,
        live: live,
        hub: hub,
        competitionId: awardedId,
      );
      await award.load();
      final awardState = award.state as AwardLoaded;
      expect(awardState.award, isNotNull);
      log(
        'Award: participant ${awardState.award!.aliasNo}, '
        '${awardState.standings.length} standings',
      );
      await award.close();

      final downloadsDir = await Directory.systemTemp.createTemp('bafo-report');
      final report = ResultReportCubit(
        reports: ApiIssuerReportRepository(api),
        downloads: ApiFileDownloadService(
          api,
          cacheDirectory: () async => downloadsDir,
          opener: (path, mimeType) async {
            expect(File(path).readAsBytesSync().take(4), '%PDF'.codeUnits);
            return FileOpenResult.opened;
          },
        ),
        competitionId: awardedId,
      );
      await report.open('ar');
      log('Report: ${report.state}');
      expect(
        report.state,
        const ResultReportOpened(locale: 'ar', result: FileOpenResult.opened),
      );
      await report.close();
      await downloadsDir.delete(recursive: true);

      await hub.dispose();
      await realtime.dispose();
    },
    skip: skip,
    timeout: const Timeout(Duration(minutes: 4)),
  );
}
