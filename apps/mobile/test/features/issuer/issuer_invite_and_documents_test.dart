import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/issuer/presentation/attachments/manage_attachments_cubit.dart';
import 'package:bafo/features/issuer/presentation/invite/invite_participants_cubit.dart';
import 'package:bafo/features/issuer/presentation/participants/invitations_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:dio/dio.dart' show ProgressCallback;
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import 'issuer_test_helpers.dart';

void main() {
  setUpAll(registerIssuerFallbacks);

  late MockCompetitions competitions;
  late MockInvitations invitations;
  late MockVendors vendors;
  late MockAttachments attachments;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;

  setUp(() {
    competitions = MockCompetitions();
    invitations = MockInvitations();
    vendors = MockVendors();
    attachments = MockAttachments();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
  });

  tearDown(() async {
    await hub.dispose();
    await realtime.dispose();
  });

  final suggestions = fixtureList('suggestions_issuer_draft')
      .map(Suggestion.fromJson)
      .toList();
  final vendorRows = fixtureList('vendors_issuer')
      .map(Vendor.fromJson)
      .toList();

  group('InviteParticipantsCubit (M40)', () {
    final draft = issuerCompetition('competition_issuer_draft');
    final sponsoredLive = issuerCompetition(
      'competition_issuer_sponsored_live',
      (json) => (json['sponsorship'] as Map<String, dynamic>)['free_slots'] = 1,
    );
    final sponsorship = Sponsorship.fromJson(
      copyOf(fixtureData('sponsorship_issuer_sponsored'))
        ..['counts'] = {
          'pending': 0,
          'reserved': 0,
          'joined': 1,
          'released': 0,
          'unused': 0,
          'void': 0,
          'free_slots': 1,
        },
    );

    InviteParticipantsCubit build(String id) => InviteParticipantsCubit(
      competitions: competitions,
      invitations: invitations,
      vendors: vendors,
      competitionId: id,
    );

    void stubDraft() {
      when(() => competitions.show(draft.id)).thenAnswer((_) async => draft);
      when(() => competitions.suggestions(draft.id, query: any(named: 'query')))
          .thenAnswer((_) async => suggestions);
      when(() => vendors.search(query: any(named: 'query'))).thenAnswer(
        (_) async =>
            Paged(items: vendorRows, meta: PageMeta.single(vendorRows.length)),
      );
    }

    blocTest<InviteParticipantsCubit, InviteParticipantsState>(
      'loads the competition and the suggestions; vendors load on their tab',
      setUp: stubDraft,
      build: () => build(draft.id),
      act: (cubit) async {
        await cubit.load();
        cubit.selectTab(InviteTab.vendors);
        await Future<void>.delayed(Duration.zero);
      },
      verify: (cubit) {
        final state = cubit.state as InviteReady;
        expect(state.suggestions.items, hasLength(1));
        expect(state.vendors.items, hasLength(vendorRows.length));
        expect(state.selectedMode, isFalse);
      },
    );

    blocTest<InviteParticipantsCubit, InviteParticipantsState>(
      'stages suggestions, vendors and pasted e-mails, then sends them in one request',
      setUp: () {
        stubDraft();
        when(() => invitations.invite(draft.id, any())).thenAnswer(
          (_) async =>
              fixtureList('invitations_issuer_live_initial')
                  .take(2)
                  .map(Invitation.fromJson)
                  .toList(),
        );
      },
      build: () => build(draft.id),
      act: (cubit) async {
        await cubit.load();
        cubit
          ..toggleSuggestion(suggestions.first)
          ..toggleVendor(vendorRows.first)
          ..addEmails('a@x.sa, bad, A@x.sa')
          ..toggleVendor(vendorRows.first)
          ..toggleVendor(vendorRows[1]);
        final ready = cubit.state as InviteReady;
        expect(ready.staged, hasLength(3));
        expect(ready.invalidEmails, ['bad']);
        await cubit.submit();
      },
      verify: (cubit) {
        expect(cubit.state, isA<InviteSent>());
        final rows =
            verify(() => invitations.invite(draft.id, captureAny()))
                    .captured
                    .single
                as List<InvitationInput>;
        expect(rows.map((row) => row.toJson()).toList(), [
          {
            'organization_id': suggestions.first.organization.id,
            'sponsored': false,
          },
          {'email': 'a@x.sa', 'sponsored': false},
          {'vendor_id': vendorRows[1].id, 'sponsored': false},
        ]);
      },
    );

    blocTest<InviteParticipantsCubit, InviteParticipantsState>(
      'all-or-nothing: row errors mark the staged rows and nothing is sent',
      setUp: () {
        stubDraft();
        when(() => invitations.invite(draft.id, any())).thenThrow(
          apiError(
            'validation_failed',
            status: 422,
            fieldErrors: {
              'invitations.1.email': ['مدعو من قبل'],
            },
            details: {
              'item_codes': {'invitations.1.email': 'invitation_duplicate'},
            },
          ),
        );
      },
      build: () => build(draft.id),
      act: (cubit) async {
        await cubit.load();
        cubit.addEmails('a@x.sa b@x.sa');
        await cubit.submit();
      },
      verify: (cubit) {
        final state = cubit.state as InviteReady;
        expect(state.submitting, isFalse);
        expect(state.submitError, isNull);
        expect(state.rowErrors.keys, ['email:b@x.sa']);
        expect(state.rowErrors['email:b@x.sa']?.code, 'invitation_duplicate');
        expect(state.staged, hasLength(2));
      },
    );

    blocTest<InviteParticipantsCubit, InviteParticipantsState>(
      'selected mode: covered rows within free slots; payment required → send without fees',
      setUp: () {
        when(() => competitions.show(sponsoredLive.id))
            .thenAnswer((_) async => sponsoredLive);
        when(() => competitions.sponsorship(sponsoredLive.id))
            .thenAnswer((_) async => sponsorship);
        when(
          () => competitions.suggestions(
            sponsoredLive.id,
            query: any(named: 'query'),
          ),
        ).thenAnswer((_) async => const []);
        var calls = 0;
        when(() => invitations.invite(sponsoredLive.id, any()))
            .thenAnswer((_) async {
              calls++;
              if (calls == 1) {
                throw apiError('sponsorship_payment_required', status: 409);
              }
              return const [];
            });
      },
      build: () => build(sponsoredLive.id),
      act: (cubit) async {
        await cubit.load();
        cubit
          ..addEmails('a@x.sa b@x.sa')
          ..setSponsored('email:a@x.sa', sponsored: true)
          // Only one free slot: the second switch is refused.
          ..setSponsored('email:b@x.sa', sponsored: true);
        final ready = cubit.state as InviteReady;
        expect(ready.sponsoredCount, 1);
        await cubit.submit();
        final failed = cubit.state as InviteReady;
        expect(failed.feesPaidOnWeb, isTrue);
        expect(failed.canSendWithoutFees, isTrue);
        await cubit.sendWithoutCoveringFees();
      },
      verify: (cubit) {
        expect(cubit.state, const InviteSent([], feesCovered: false));
        final calls = verify(
          () => invitations.invite(sponsoredLive.id, captureAny()),
        ).captured;
        final first = calls.first as List<InvitationInput>;
        final second = calls.last as List<InvitationInput>;
        expect(first.first.sponsored, isTrue);
        expect(second.every((row) => !row.sponsored), isTrue);
      },
    );

    blocTest<InviteParticipantsCubit, InviteParticipantsState>(
      'without can_invite the screen is unavailable (back to the detail)',
      setUp: () {
        final closed = issuerCompetition('competition_issuer_closed');
        when(() => competitions.show(closed.id))
            .thenAnswer((_) async => closed);
      },
      build: () => build(issuerCompetition('competition_issuer_closed').id),
      act: (cubit) => cubit.load(),
      verify: (cubit) => expect(cubit.state, isA<InviteUnavailable>()),
    );
  });

  group('InvitationsCubit (M41)', () {
    final live = issuerCompetition('competition_issuer_live_initial');
    final rows = fixtureList('invitations_issuer_live_initial')
        .map(Invitation.fromJson)
        .toList();
    final list = InvitationList(
      invitations: rows,
      counts: const {InvitationStatus.joined: 2, InvitationStatus.sent: 1},
    );
    final pending = rows.firstWhere((row) => row.isPending);

    InvitationsCubit build() => InvitationsCubit(
      competitions: competitions,
      invitations: invitations,
      hub: hub,
      competitionId: live.id,
      refetchWindow: const Duration(milliseconds: 20),
    );

    setUp(() {
      when(() => competitions.show(live.id)).thenAnswer((_) async => live);
      when(() => invitations.list(live.id)).thenAnswer((_) async => list);
    });

    blocTest<InvitationsCubit, InvitationsState>(
      'loads the invitations with counts and filters them locally',
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit.setFilter(InvitationStatus.sent);
      },
      verify: (cubit) {
        final state = cubit.state as InvitationsLoaded;
        expect(state.total, 3);
        expect(state.visible, [pending]);
      },
    );

    blocTest<InvitationsCubit, InvitationsState>(
      'revoke replaces the row with the server answer; resend reports it',
      setUp: () {
        final revoked = Invitation.fromJson(
          copyOf(fixtureList('invitations_issuer_live_initial')[2])
            ..['status'] = 'revoked',
        );
        when(() => invitations.remove(live.id, pending.id))
            .thenAnswer((_) async => revoked);
        when(() => invitations.resend(live.id, pending.id))
            .thenAnswer((_) async {});
      },
      build: build,
      act: (cubit) async {
        await cubit.load();
        await cubit.resend(pending);
        expect(
          (cubit.state as InvitationsLoaded).lastAction?.action,
          InvitationAction.resent,
        );
        await cubit.revoke(pending);
      },
      verify: (cubit) {
        final state = cubit.state as InvitationsLoaded;
        expect(
          state.invitations.firstWhere((row) => row.id == pending.id).status,
          InvitationStatus.revoked,
        );
        expect(state.lastAction?.action, InvitationAction.revoked);
        expect(state.busy, isEmpty);
      },
    );

    blocTest<InvitationsCubit, InvitationsState>(
      'a 429 on resend is an action error; the rows stay',
      setUp: () =>
          when(() => invitations.resend(live.id, pending.id))
              .thenThrow(apiError('too_many_requests', status: 429)),
      build: build,
      act: (cubit) async {
        await cubit.load();
        await cubit.resend(pending);
      },
      verify: (cubit) {
        final state = cubit.state as InvitationsLoaded;
        expect(state.actionError?.code, 'too_many_requests');
        expect(state.invitations, rows);
      },
    );

    blocTest<InvitationsCubit, InvitationsState>(
      'invitation.updated upserts the row at once and refetches the counts once',
      build: build,
      act: (cubit) async {
        await cubit.load();
        final joined = copyOf(fixtureList('invitations_issuer_live_initial')[2])
          ..['status'] = 'joined';
        realtime
          ..emit(
            'competition.${live.id}',
            RealtimeEvents.invitationUpdated,
            joined,
          )
          ..emit(
            'competition.${live.id}',
            RealtimeEvents.invitationUpdated,
            joined,
          );
        await Future<void>.delayed(Duration.zero);
        expect(
          (cubit.state as InvitationsLoaded).invitations
              .firstWhere((row) => row.id == pending.id)
              .status,
          InvitationStatus.joined,
        );
        await Future<void>.delayed(const Duration(milliseconds: 60));
      },
      verify: (_) => verify(() => invitations.list(live.id)).called(2),
    );

    blocTest<InvitationsCubit, InvitationsState>(
      'a participant projection is sent back to the detail',
      setUp: () {
        final participantView = Competition.fromJson(
          fixtureData('competition_supplier_a_live_initial'),
        );
        when(() => competitions.show(live.id))
            .thenAnswer((_) async => participantView);
      },
      build: build,
      act: (cubit) => cubit.load(),
      verify: (cubit) {
        expect(cubit.state, isA<InvitationsNotIssuer>());
        verifyNever(() => invitations.list(any()));
      },
    );
  });

  group('ManageAttachmentsCubit (M42)', () {
    final draft = issuerCompetition('competition_issuer_draft');
    final live = issuerCompetition('competition_issuer_live_initial');
    final uploaded = fixtureAttachments().first;

    ManageAttachmentsCubit build(Competition competition) =>
        ManageAttachmentsCubit(
          competitions: competitions,
          attachments: attachments,
          competitionId: competition.id,
        );

    void stub(Competition competition, List<Attachment> rows) {
      when(() => competitions.show(competition.id))
          .thenAnswer((_) async => competition);
      when(() => attachments.list(competition.id))
          .thenAnswer((_) async => rows);
    }

    blocTest<ManageAttachmentsCubit, ManageAttachmentsState>(
      'rejects a wrong type or size before sending; uploads with progress',
      setUp: () {
        stub(draft, const []);
        when(
          () => attachments.uploadFile(
            draft.id,
            filePath: any(named: 'filePath'),
            fileName: any(named: 'fileName'),
            title: any(named: 'title'),
            onSendProgress: any(named: 'onSendProgress'),
          ),
        ).thenAnswer((invocation) async {
          final progress =
              invocation.namedArguments[#onSendProgress] as ProgressCallback?;
          progress?.call(50, 100);
          return uploaded;
        });
      },
      build: () => build(draft),
      act: (cubit) async {
        await cubit.load();
        await cubit.upload(
          const PickedDocument(path: '/tmp/a.exe', name: 'a.exe'),
        );
        await cubit.upload(
          const PickedDocument(
            path: '/tmp/big.pdf',
            name: 'big.pdf',
            size: AttachmentRules.maxBytes + 1,
          ),
        );
        await cubit.upload(
          const PickedDocument(path: '/tmp/spec.pdf', name: 'spec.pdf'),
        );
      },
      verify: (cubit) {
        final state = cubit.state as ManageAttachmentsLoaded;
        expect(state.attachments, [uploaded]);
        expect(state.uploads.map((upload) => upload.errorCode), [
          'file_type_not_allowed',
          'file_too_large',
        ]);
        verify(
          () => attachments.uploadFile(
            draft.id,
            filePath: '/tmp/spec.pdf',
            fileName: 'spec.pdf',
            title: any(named: 'title'),
            onSendProgress: any(named: 'onSendProgress'),
          ),
        ).called(1);
      },
    );

    blocTest<ManageAttachmentsCubit, ManageAttachmentsState>(
      'a server upload error stays on its row',
      setUp: () {
        stub(draft, const []);
        when(
          () => attachments.uploadFile(
            draft.id,
            filePath: any(named: 'filePath'),
            fileName: any(named: 'fileName'),
            title: any(named: 'title'),
            onSendProgress: any(named: 'onSendProgress'),
          ),
        ).thenThrow(apiError('competition_not_editable', status: 409));
      },
      build: () => build(draft),
      act: (cubit) async {
        await cubit.load();
        await cubit.upload(
          const PickedDocument(path: '/tmp/a.pdf', name: 'a.pdf'),
        );
        final state = cubit.state as ManageAttachmentsLoaded;
        expect(state.uploads.single.errorCode, 'competition_not_editable');
        cubit.dismissUpload(state.uploads.single.id);
      },
      verify: (cubit) =>
          expect((cubit.state as ManageAttachmentsLoaded).uploads, isEmpty),
    );

    blocTest<ManageAttachmentsCubit, ManageAttachmentsState>(
      'deletes in draft; a live competition allows uploads but no deletes',
      setUp: () {
        stub(draft, [uploaded]);
        stub(live, [uploaded]);
        when(() => attachments.delete(draft.id, uploaded.id))
            .thenAnswer((_) async {});
      },
      build: () => build(draft),
      act: (cubit) async {
        await cubit.load();
        await cubit.delete(uploaded);
      },
      verify: (cubit) async {
        expect((cubit.state as ManageAttachmentsLoaded).attachments, isEmpty);
        final liveCubit = build(live);
        await liveCubit.load();
        final liveState = liveCubit.state as ManageAttachmentsLoaded;
        expect(liveState.canUpload, isTrue);
        expect(liveState.canDelete, isFalse);
        await liveCubit.delete(uploaded);
        verifyNever(() => attachments.delete(live.id, any()));
        await liveCubit.close();
      },
    );
  });
}
