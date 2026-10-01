import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/live/domain/offer_rules.dart';
import 'package:bafo/features/live/presentation/offer_submit_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockLive extends Mock implements LiveRepository {}

void main() {
  const competitionId = 'c1';
  final liveJson = fixtureData('live_supplier_a_auction');
  final submission = OfferSubmission(
    offer: MyOffer.fromJson(
      fixtureList('my_offers_supplier_a_final_window').first,
    ),
    live: ParticipantLiveSnapshot.fromJson(liveJson),
    liveJson: liveJson,
  );
  final now = DateTime.utc(2026, 10, 1, 12);

  late _MockLive live;
  late List<String> keys;
  late List<OfferSubmission> accepted;
  late int stale;

  setUp(() {
    live = _MockLive();
    keys = [];
    accepted = [];
    stale = 0;
  });

  OfferSubmitCubit build() => OfferSubmitCubit(
    live: live,
    competitionId: competitionId,
    onAccepted: accepted.add,
    onStale: () => stale++,
    retryDelay: Duration.zero,
    keyFactory: () {
      final key = 'key-${keys.length + 1}';
      keys.add(key);
      return key;
    },
    deviceNow: () => now,
  );

  void answer(Object Function(String key, bool outlier) respond) {
    when(
      () => live.submitOffer(
        competitionId,
        amountMinor: any(named: 'amountMinor'),
        idempotencyKey: any(named: 'idempotencyKey'),
        confirmOutlier: any(named: 'confirmOutlier'),
      ),
    ).thenAnswer((invocation) async {
      final result = respond(
        invocation.namedArguments[#idempotencyKey] as String,
        invocation.namedArguments[#confirmOutlier] as bool,
      );
      if (result is ApiException) throw result;
      return result as OfferSubmission;
    });
  }

  List<String> sentKeys() => verify(
    () => live.submitOffer(
      competitionId,
      amountMinor: any(named: 'amountMinor'),
      idempotencyKey: captureAny(named: 'idempotencyKey'),
      confirmOutlier: any(named: 'confirmOutlier'),
    ),
  ).captured.cast<String>();

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'the confirm step makes one key; a success hands the snapshot to the room',
    setUp: () => answer((_, _) => submission),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
    },
    expect: () => [
      const OfferConfirming(amountMinor: 5150000, key: 'key-1'),
      const OfferSubmitting(amountMinor: 5150000, key: 'key-1'),
      OfferAccepted(submission),
    ],
    verify: (_) {
      expect(sentKeys(), ['key-1']);
      expect(accepted, [submission]);
    },
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a network failure is retried once with the same key',
    setUp: () {
      var calls = 0;
      answer(
        (_, _) => calls++ == 0
            ? const ApiException(code: ApiErrorCode.network)
            : submission,
      );
    },
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
    },
    expect: () => [
      const OfferConfirming(amountMinor: 5150000, key: 'key-1'),
      const OfferSubmitting(amountMinor: 5150000, key: 'key-1'),
      OfferAccepted(submission),
    ],
    verify: (_) => expect(sentKeys(), ['key-1', 'key-1']),
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'still failing: "could not confirm"; the manual retry keeps the key',
    setUp: () {
      var calls = 0;
      answer(
        (_, _) => calls++ < 2
            ? const ApiException(code: 'server_error', statusCode: 502)
            : submission,
      );
    },
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
      await cubit.retry();
    },
    expect: () => [
      const OfferConfirming(amountMinor: 5150000, key: 'key-1'),
      const OfferSubmitting(amountMinor: 5150000, key: 'key-1'),
      const OfferUnconfirmed(amountMinor: 5150000, key: 'key-1'),
      const OfferSubmitting(amountMinor: 5150000, key: 'key-1'),
      OfferAccepted(submission),
    ],
    verify: (_) => expect(sentKeys(), ['key-1', 'key-1', 'key-1']),
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'an outlier needs a confirmation, re-sent with a new key and confirm_outlier',
    setUp: () => answer(
      (key, outlier) => outlier
          ? submission
          : const ApiException(
              code: 'offer_outlier_confirm_required',
              statusCode: 422,
              details: {'change_bps': -2500, 'reference_amount_minor': 5100000},
            ),
    ),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(3825000);
      await cubit.submit();
      await cubit.confirmOutlier();
    },
    expect: () => [
      const OfferConfirming(amountMinor: 3825000, key: 'key-1'),
      const OfferSubmitting(amountMinor: 3825000, key: 'key-1'),
      const OfferOutlierConfirm(
        amountMinor: 3825000,
        changeBps: -2500,
        referenceAmountMinor: 5100000,
      ),
      const OfferSubmitting(
        amountMinor: 3825000,
        key: 'key-2',
        confirmOutlier: true,
      ),
      OfferAccepted(submission),
    ],
    verify: (cubit) {
      final captured = verify(
        () => live.submitOffer(
          competitionId,
          amountMinor: 3825000,
          idempotencyKey: captureAny(named: 'idempotencyKey'),
          confirmOutlier: captureAny(named: 'confirmOutlier'),
        ),
      ).captured;
      expect(captured, ['key-1', false, 'key-2', true]);
    },
  );

  test('the outlier wording follows the sign of the change', () {
    const lower = OfferOutlierConfirm(
      amountMinor: 3825000,
      changeBps: -2500,
      referenceAmountMinor: 5100000,
    );
    const higher = OfferOutlierConfirm(
      amountMinor: 6500000,
      changeBps: 2745,
      referenceAmountMinor: 5100000,
    );
    expect(lower.isLower, isTrue);
    expect(higher.isLower, isFalse);
    expect(formatBps(lower.changeBps), '25%');
    expect(formatBps(higher.changeBps), '27.45%');
  });

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a step error comes back inline with the server amount',
    setUp: () => answer(
      (_, _) => const ApiException(
        code: 'offer_step_not_met',
        statusCode: 422,
        details: {'required_amount_minor': 5150000},
      ),
    ),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5100000);
      await cubit.submit();
    },
    skip: 2,
    expect: () => [
      isA<OfferComposing>()
          .having((s) => s.issue?.kind, 'kind', OfferIssueKind.stepNotMet)
          .having((s) => s.issue?.amountMinor, 'amount', 5150000)
          .having((s) => s.issue?.isInline, 'inline', isTrue),
    ],
    verify: (_) => expect(stale, 0),
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a closed competition means the room is stale',
    setUp: () => answer(
      (_, _) => const ApiException(code: 'offer_closed', statusCode: 409),
    ),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
    },
    skip: 2,
    expect: () => [
      isA<OfferComposing>().having(
        (s) => s.issue?.kind,
        'kind',
        OfferIssueKind.closed,
      ),
    ],
    verify: (_) => expect(stale, 1),
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a 429 disables submit for the server wait',
    setUp: () => answer(
      (_, _) => const ApiException(
        code: 'too_many_requests',
        statusCode: 429,
        details: {'retry_after_seconds': 3},
      ),
    ),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
    },
    skip: 2,
    expect: () => [
      isA<OfferComposing>()
          .having((s) => s.issue?.kind, 'kind', OfferIssueKind.rateLimited)
          .having(
            (s) => s.retryAt,
            'retryAt',
            now.add(const Duration(seconds: 3)),
          ),
    ],
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a reused key asks for a new confirmation with a fresh key',
    setUp: () => answer(
      (_, _) =>
          const ApiException(code: 'idempotency_key_reused', statusCode: 422),
    ),
    build: build,
    act: (cubit) async {
      cubit.openConfirm(5150000);
      await cubit.submit();
    },
    skip: 2,
    expect: () => [
      isA<OfferConfirming>()
          .having((s) => s.key, 'key', 'key-2')
          .having((s) => s.issue?.kind, 'issue', OfferIssueKind.keyReused),
    ],
  );

  blocTest<OfferSubmitCubit, OfferSubmitState>(
    'a new intent after a cancel gets a new key',
    build: build,
    act: (cubit) {
      cubit
        ..openConfirm(5150000)
        ..cancel()
        ..openConfirm(5200000);
    },
    expect: () => const [
      OfferConfirming(amountMinor: 5150000, key: 'key-1'),
      OfferComposing(),
      OfferConfirming(amountMinor: 5200000, key: 'key-2'),
    ],
  );

  group('OfferIssue.fromError', () {
    test('maps the documented details', () {
      final notOpen = OfferIssue.fromError(
        const ApiException(
          code: 'offer_not_accepting',
          statusCode: 409,
          details: {'status': 'live', 'opens_at': '2026-10-02T06:00:00.000Z'},
        ),
      );
      expect(notOpen.kind, OfferIssueKind.notAccepting);
      expect(notOpen.at, DateTime.utc(2026, 10, 2, 6));
      expect(notOpen.meansStale, isTrue);
      final bafo = OfferIssue.fromError(
        const ApiException(
          code: 'offer_bafo_worse_than_reference',
          statusCode: 422,
          details: {'reference_amount_minor': 24100000},
        ),
      );
      expect(bafo.kind, OfferIssueKind.bafoReference);
      expect(bafo.amountMinor, 24100000);
      expect(bafo.isInline, isTrue);
      expect(
        OfferIssue.fromError(const ApiException(code: 'not_a_participant'))
            .meansStale,
        isTrue,
      );
    });
  });

  group('OfferBounds (CD13 pre-checks from the snapshot)', () {
    test(
      'auction: at least the required next amount and the opening price',
      () {
        final bounds = OfferBounds.of(
          ParticipantLiveSnapshot.fromJson(liveJson),
          format: CompetitionFormat.live,
        );
        expect(bounds.mode, OfferMode.live);
        expect(bounds.requiredNextMinor, 5150000);
        expect(bounds.check(5150000), isNull);
        expect(bounds.check(5200000), isNull);
        expect(
          bounds.check(5140000),
          const OfferBoundViolation(OfferBoundIssue.stepNotMet, 5150000),
        );
        expect(
          bounds.check(4900000),
          const OfferBoundViolation(OfferBoundIssue.startPrice, 5000000),
        );
        expect(
          bounds.check(5150050),
          const OfferBoundViolation(OfferBoundIssue.granularity),
        );
      },
    );

    test('tender: at most the required next amount and the ceiling', () {
      final bounds = OfferBounds.of(
        ParticipantLiveSnapshot.fromJson(
          fixtureData('live_supplier_a_final_window'),
        ),
        format: CompetitionFormat.live,
      );
      final next = bounds.requiredNextMinor!;
      expect(bounds.check(next), isNull);
      expect(bounds.check(next - 100), isNull);
      expect(bounds.check(next + 100)?.issue, OfferBoundIssue.stepNotMet);
      expect(bounds.check(25000100)?.issue, OfferBoundIssue.startPrice);
    });

    test('sealed: the start price only, either direction below it', () {
      final bounds = OfferBounds.of(
        ParticipantLiveSnapshot.fromJson(fixtureData('live_supplier_a_sealed')),
        format: CompetitionFormat.sealed,
      );
      expect(bounds.mode, OfferMode.sealed);
      expect(bounds.requiredNextMinor, isNull);
      expect(bounds.check(17600000), isNull, reason: 'a revision may go up');
      expect(bounds.check(18000100)?.issue, OfferBoundIssue.startPrice);
    });

    test('BAFO: not worse than the reference', () {
      final bounds = OfferBounds.of(
        ParticipantLiveSnapshot.fromJson(fixtureData('live_supplier_c_bafo')),
        format: CompetitionFormat.live,
      );
      expect(bounds.mode, OfferMode.bafo);
      expect(bounds.bafoReferenceMinor, 24100000);
      expect(bounds.check(24000000), isNull);
      expect(
        bounds.check(24100100),
        const OfferBoundViolation(OfferBoundIssue.bafoReference, 24100000),
      );
    });

    test('percentages trim trailing zeros', () {
      expect(formatBps(50), '0.5%');
      expect(formatBps(6080), '60.8%');
      expect(formatBps(1), '0.01%');
      expect(formatBps(-1090), '10.9%');
      expect(changeBps(9800000, 10000000), -200);
      expect(changeBps(100, null), isNull);
    });
  });
}
