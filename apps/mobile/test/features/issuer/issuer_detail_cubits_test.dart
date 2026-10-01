import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/realtime/competition_channel_hub.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_action_cubits.dart';
import 'package:bafo/features/issuer/presentation/detail/issuer_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/edit/edit_competition_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import 'issuer_test_helpers.dart';

void main() {
  setUpAll(registerIssuerFallbacks);

  late MockCompetitions competitions;
  late MockAttachments attachments;
  late MockLookups lookups;
  late FakeRealtimeClient realtime;
  late CompetitionChannelHub hub;

  setUp(() {
    competitions = MockCompetitions();
    attachments = MockAttachments();
    lookups = MockLookups();
    realtime = FakeRealtimeClient();
    hub = CompetitionChannelHub(realtime);
  });

  tearDown(() async {
    await hub.dispose();
    await realtime.dispose();
  });

  group('IssuerCompetitionCubit (M38)', () {
    final live = issuerCompetition('competition_issuer_live_final_window');
    final sponsored = issuerCompetition('competition_issuer_sponsored_live');
    final draft = issuerCompetition('competition_issuer_draft');
    final participantView = Competition.fromJson(
      fixtureData('competition_supplier_a_live_final_window'),
    );

    IssuerCompetitionCubit build(String id) => IssuerCompetitionCubit(
      competitions: competitions,
      attachments: attachments,
      hub: hub,
      competitionId: id,
      invitationRefetchWindow: const Duration(milliseconds: 20),
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'loads the issuer projection, its documents and the live snapshot, on the issuer channel',
      setUp: () {
        when(() => competitions.show(live.id)).thenAnswer((_) async => live);
        when(() => attachments.list(live.id))
            .thenAnswer((_) async => fixtureAttachments());
      },
      build: () => build(live.id),
      act: (cubit) => cubit.load(),
      expect: () => [
        const IssuerCompetitionLoading(),
        isA<IssuerCompetitionLoaded>()
            .having((s) => s.attachments, 'attachments', hasLength(2))
            .having((s) => s.live?.v, 'live v', 5)
            .having((s) => s.leadingAmountMinor, 'leading', 23800000)
            .having((s) => s.sponsorship, 'sponsorship', isNull),
      ],
      verify: (_) {
        expect(realtime.subscribed, ['competition.${live.id}']);
        verifyNever(() => competitions.sponsorship(any()));
      },
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'reads the sponsorship counters when a sponsorship exists',
      setUp: () {
        when(() => competitions.show(sponsored.id))
            .thenAnswer((_) async => sponsored);
        when(() => attachments.list(sponsored.id)).thenAnswer((_) async => []);
        when(() => competitions.sponsorship(sponsored.id)).thenAnswer(
          (_) async =>
              Sponsorship.fromJson(fixtureData('sponsorship_issuer_sponsored')),
        );
      },
      build: () => build(sponsored.id),
      act: (cubit) => cubit.load(),
      verify: (cubit) {
        final state = cubit.state as IssuerCompetitionLoaded;
        expect(state.sponsorship?.mode, 'selected');
        expect(state.sponsorship?.fundedPasses, 1);
      },
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'channel: newer snapshots apply, older ones are dropped, a status change refetches',
      setUp: () {
        when(() => competitions.show(live.id)).thenAnswer((_) async => live);
        when(() => attachments.list(live.id)).thenAnswer((_) async => []);
      },
      build: () => build(live.id),
      act: (cubit) async {
        await cubit.load();
        final channel = 'competition.${live.id}';
        final older = copyOf(fixtureData('live_issuer_final_window'))
          ..['v'] = 4;
        realtime.emit(channel, RealtimeEvents.liveUpdated, older);
        final newer = copyOf(fixtureData('live_issuer_final_window'))
          ..['v'] = 6
          ..['extension_count'] = 1
          ..['last_change'] = {'kind': 'extension', 'reason': 'auto'};
        realtime.emit(channel, RealtimeEvents.liveUpdated, newer);
        await Future<void>.delayed(Duration.zero);
        final closed = copyOf(fixtureData('live_issuer_final_window'))
          ..['v'] = 7
          ..['status'] = 'closed'
          ..['last_change'] = {'kind': 'status', 'reason': null};
        realtime.emit(channel, RealtimeEvents.liveUpdated, closed);
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (cubit) {
        final state = cubit.state as IssuerCompetitionLoaded;
        expect(state.live?.v, 7);
        // Initial load + the status-change refetch (v 6 was an extension).
        verify(() => competitions.show(live.id)).called(2);
      },
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'invitation.updated bursts refetch once per window; competition.updated refetches',
      setUp: () {
        when(() => competitions.show(live.id)).thenAnswer((_) async => live);
        when(() => attachments.list(live.id)).thenAnswer((_) async => []);
      },
      build: () => build(live.id),
      act: (cubit) async {
        await cubit.load();
        final channel = 'competition.${live.id}';
        for (var i = 0; i < 3; i++) {
          realtime.emit(channel, RealtimeEvents.invitationUpdated, {
            'id': 'x$i',
          });
        }
        await Future<void>.delayed(const Duration(milliseconds: 60));
        realtime.emit(channel, RealtimeEvents.competitionUpdated, {
          'competition_id': live.id,
          'fields': ['attachments'],
        });
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) => verify(() => competitions.show(live.id)).called(3),
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'a non-issuer projection falls back (role guard); 404 is not found',
      setUp: () {
        when(() => competitions.show('p'))
            .thenAnswer((_) async => participantView);
        when(() => competitions.show('missing'))
            .thenThrow(apiError('not_found', status: 404));
      },
      build: () => build('p'),
      act: (cubit) => cubit.load(),
      expect: () => [
        const IssuerCompetitionLoading(),
        IssuerCompetitionNotIssuer(participantView),
      ],
      verify: (_) async {
        final missing = build('missing');
        await missing.load();
        expect(missing.state, const IssuerCompetitionNotFound());
        await missing.close();
      },
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'delete draft: busy, then deleted; a failure keeps the page with the error',
      setUp: () {
        when(() => competitions.show(draft.id)).thenAnswer((_) async => draft);
        when(() => attachments.list(draft.id)).thenAnswer((_) async => []);
        var calls = 0;
        when(() => competitions.deleteDraft(draft.id)).thenAnswer((_) async {
          calls++;
          if (calls == 1) {
            throw apiError('competition_not_editable', status: 409);
          }
        });
      },
      build: () => build(draft.id),
      act: (cubit) async {
        await cubit.load();
        await cubit.deleteDraft();
        await cubit.deleteDraft();
      },
      skip: 2,
      expect: () => [
        isA<IssuerCompetitionLoaded>().having(
          (s) => s.deleting,
          'deleting',
          isTrue,
        ),
        isA<IssuerCompetitionLoaded>()
            .having((s) => s.deleting, 'deleting', isFalse)
            .having(
              (s) => s.actionError?.code,
              'error',
              'competition_not_editable',
            ),
        isA<IssuerCompetitionLoaded>().having(
          (s) => s.deleting,
          'deleting',
          isTrue,
        ),
        const IssuerCompetitionDeleted(),
      ],
    );

    blocTest<IssuerCompetitionCubit, IssuerCompetitionState>(
      'the channel is released on close',
      setUp: () {
        when(() => competitions.show(live.id)).thenAnswer((_) async => live);
        when(() => attachments.list(live.id)).thenAnswer((_) async => []);
      },
      build: () => build(live.id),
      act: (cubit) => cubit.load(),
      verify: (_) {},
      tearDown: () => expect(realtime.unsubscribed, ['competition.${live.id}']),
    );
  });

  group('PublishCubit (M43)', () {
    final published = issuerCompetition('competition_issuer_scheduled');

    blocTest<PublishCubit, PublishState>(
      'publishes and waits for the server (no optimistic state)',
      setUp: () =>
          when(() => competitions.publish('d'))
              .thenAnswer((_) async => published),
      build: () => PublishCubit(competitions: competitions, competitionId: 'd'),
      act: (cubit) => cubit.publish(),
      expect: () => [const PublishInProgress(), PublishSucceeded(published)],
    );

    blocTest<PublishCubit, PublishState>(
      'min_participants_not_met exposes how many invitations are missing',
      setUp: () => when(() => competitions.publish('d')).thenThrow(
        apiError(
          'min_participants_not_met',
          status: 422,
          details: {'required': 3, 'current': 1},
        ),
      ),
      build: () => PublishCubit(competitions: competitions, competitionId: 'd'),
      act: (cubit) => cubit.publish(),
      verify: (cubit) {
        final state = cubit.state as PublishFailed;
        expect(state.missingInvitations, 2);
      },
    );

    blocTest<PublishCubit, PublishState>(
      'sponsorship_payment_required is a failure the sheet turns into the web notice',
      setUp: () =>
          when(() => competitions.publish('d'))
              .thenThrow(apiError('sponsorship_payment_required', status: 409)),
      build: () => PublishCubit(competitions: competitions, competitionId: 'd'),
      act: (cubit) => cubit.publish(),
      verify: (cubit) {
        final state = cubit.state as PublishFailed;
        expect(state.error.code, 'sponsorship_payment_required');
        expect(state.missingInvitations, isNull);
      },
    );
  });

  group('CancelCompetitionCubit (M44)', () {
    final reasons = fixtureList('lookups_close_reasons_cancel')
        .map(CloseReason.fromJson)
        .toList();
    final cancelled = issuerCompetition('competition_issuer_cancelled');

    CancelCompetitionCubit build() => CancelCompetitionCubit(
      competitions: competitions,
      lookups: lookups,
      competitionId: 'c',
    );

    blocTest<CancelCompetitionCubit, CancelCompetitionState>(
      'loads cancel reasons, then cancels with the reason and note',
      setUp: () {
        when(() => lookups.closeReasons(CloseReasonKind.cancel))
            .thenAnswer((_) async => reasons);
        when(
          () => competitions.cancel(
            'c',
            closeReasonId: any(named: 'closeReasonId'),
            note: any(named: 'note'),
          ),
        ).thenAnswer((_) async => cancelled);
      },
      build: build,
      act: (cubit) async {
        await cubit.loadReasons();
        await cubit.cancel(reason: reasons.last, note: 'تغيّر الاحتياج');
      },
      expect: () => [
        const CancelCompetitionLoading(),
        CancelCompetitionReady(reasons: reasons),
        CancelCompetitionReady(reasons: reasons, submitting: true),
        CancelCompetitionDone(cancelled),
      ],
      verify: (_) => verify(
        () => competitions.cancel(
          'c',
          closeReasonId: reasons.last.id,
          note: 'تغيّر الاحتياج',
        ),
      ).called(1),
    );

    blocTest<CancelCompetitionCubit, CancelCompetitionState>(
      'an invalid transition keeps the reasons with the error',
      setUp: () {
        when(() => lookups.closeReasons(CloseReasonKind.cancel))
            .thenAnswer((_) async => reasons);
        when(
          () => competitions.cancel(
            'c',
            closeReasonId: any(named: 'closeReasonId'),
            note: any(named: 'note'),
          ),
        ).thenThrow(apiError('invalid_state_transition', status: 409));
      },
      build: build,
      act: (cubit) async {
        await cubit.loadReasons();
        await cubit.cancel(reason: reasons.first);
      },
      verify: (cubit) {
        final state = cubit.state as CancelCompetitionReady;
        expect(state.error?.code, 'invalid_state_transition');
        expect(state.submitting, isFalse);
      },
    );
  });

  group('EditCompetitionCubit (M39)', () {
    final scheduled = issuerCompetition('competition_issuer_scheduled');
    final closed = issuerCompetition('competition_issuer_closed');
    final now = DateTime.utc(2026, 9, 29, 12);

    EditCompetitionCubit build(String id) => EditCompetitionCubit(
      competitions: competitions,
      lookups: lookups,
      now: () => now,
      competitionId: id,
    );

    setUp(
      () =>
          when(() => lookups.lookups())
              .thenAnswer((_) async => fixtureLookups()),
    );

    blocTest<EditCompetitionCubit, EditCompetitionState>(
      'a scheduled competition edits basics and schedule; PATCH sends only those',
      setUp: () {
        when(() => competitions.show(scheduled.id))
            .thenAnswer((_) async => scheduled);
        when(() => competitions.update(scheduled.id, any()))
            .thenAnswer((_) async => scheduled);
      },
      build: () => build(scheduled.id),
      act: (cubit) async {
        await cubit.load();
        final editing = cubit.state as EditCompetitionEditing;
        expect(editing.scope, EditScope.scheduled);
        expect(editing.isDirty, isFalse);
        cubit.update((form) => form.copyWith(title: 'عنوان محدث'));
        await cubit.save();
      },
      verify: (cubit) {
        expect(cubit.state, EditCompetitionSaved(scheduled));
        final body =
            verify(() => competitions.update(scheduled.id, captureAny()))
                    .captured
                    .single
                as Map<String, Object?>;
        expect(body['title'], 'عنوان محدث');
        expect(body.containsKey('rules'), isFalse);
        expect(body.containsKey('scheduled_close_at'), isTrue);
      },
    );

    blocTest<EditCompetitionCubit, EditCompetitionState>(
      'competition_not_editable is shown above the form',
      setUp: () {
        when(() => competitions.show(scheduled.id))
            .thenAnswer((_) async => scheduled);
        when(() => competitions.update(scheduled.id, any())).thenThrow(
          apiError(
            'competition_not_editable',
            status: 409,
            details: {
              'fields': ['region_id'],
            },
          ),
        );
      },
      build: () => build(scheduled.id),
      act: (cubit) async {
        await cubit.load();
        cubit.update((form) => form.copyWith(title: 'x'));
        await cubit.save();
      },
      verify: (cubit) {
        final state = cubit.state as EditCompetitionEditing;
        expect(state.saveError?.code, 'competition_not_editable');
        expect(state.saving, isFalse);
      },
    );

    blocTest<EditCompetitionCubit, EditCompetitionState>(
      'a closed competition is not editable (back to the detail)',
      setUp: () =>
          when(() => competitions.show(closed.id))
              .thenAnswer((_) async => closed),
      build: () => build(closed.id),
      act: (cubit) => cubit.load(),
      expect: () => [
        const EditCompetitionLoading(),
        EditCompetitionUnavailable(closed),
      ],
    );

    blocTest<EditCompetitionCubit, EditCompetitionState>(
      'client problems block the save and bind to their fields',
      setUp: () =>
          when(() => competitions.show(scheduled.id))
              .thenAnswer((_) async => scheduled),
      build: () => build(scheduled.id),
      act: (cubit) async {
        await cubit.load();
        cubit.update((form) => form.copyWith(title: ''));
        await cubit.save();
      },
      verify: (cubit) {
        final state = cubit.state as EditCompetitionEditing;
        expect(state.problems[DraftField.title], DraftProblem.required);
        verifyNever(() => competitions.update(any(), any()));
      },
    );
  });
}
