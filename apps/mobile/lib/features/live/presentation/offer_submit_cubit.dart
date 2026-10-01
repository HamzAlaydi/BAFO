import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/idempotency.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// A server answer the composer shows (SCREENS.md S5 "Responses").
enum OfferIssueKind {
  /// `offer_step_not_met`: [OfferIssue.amountMinor] = the required amount.
  stepNotMet,

  /// `offer_start_price`: [OfferIssue.amountMinor] = the start price.
  startPrice,

  /// `offer_granularity`: [OfferIssue.amountMinor] = the granularity.
  granularity,
  amountInvalid,

  /// `offer_amount_too_large`: [OfferIssue.amountMinor] = the maximum.
  amountTooLarge,

  /// `offer_bafo_worse_than_reference`: the reference amount.
  bafoReference,

  /// `offer_not_accepting`: [OfferIssue.at] = `opens_at` when not open yet.
  notAccepting,
  closed,
  notShortlisted,
  bafoAlreadySubmitted,
  notParticipant,

  /// `too_many_requests`: submit is disabled until the retry time.
  rateLimited,

  /// `idempotency_key_reused`: confirm again (a new key was made).
  keyReused,

  /// Anything else: [OfferIssue.error] carries the message.
  other,
}

final class OfferIssue extends Equatable {
  const OfferIssue(this.kind, {this.amountMinor, this.at, this.error});

  /// Maps a rejected submit. `details` amounts come from the server
  /// (API.md §1.6): the composer never invents a bound.
  factory OfferIssue.fromError(ApiException error) {
    int? amount(String key) {
      final value = error.details[key];
      return value is int ? value : null;
    }

    return switch (error.code) {
      'offer_step_not_met' => OfferIssue(
        OfferIssueKind.stepNotMet,
        amountMinor: amount('required_amount_minor'),
        error: error,
      ),
      'offer_start_price' => OfferIssue(
        OfferIssueKind.startPrice,
        amountMinor: amount('start_price_minor'),
        error: error,
      ),
      'offer_granularity' => OfferIssue(
        OfferIssueKind.granularity,
        amountMinor: amount('granularity_minor'),
        error: error,
      ),
      'offer_amount_invalid' => OfferIssue(
        OfferIssueKind.amountInvalid,
        error: error,
      ),
      'offer_amount_too_large' => OfferIssue(
        OfferIssueKind.amountTooLarge,
        amountMinor: amount('max_amount_minor'),
        error: error,
      ),
      'offer_bafo_worse_than_reference' => OfferIssue(
        OfferIssueKind.bafoReference,
        amountMinor: amount('reference_amount_minor'),
        error: error,
      ),
      'offer_not_accepting' => OfferIssue(
        OfferIssueKind.notAccepting,
        at: DateTime.tryParse('${error.details['opens_at'] ?? ''}')?.toUtc(),
        error: error,
      ),
      'offer_closed' => OfferIssue(OfferIssueKind.closed, error: error),
      'offer_not_shortlisted' => OfferIssue(
        OfferIssueKind.notShortlisted,
        error: error,
      ),
      'offer_bafo_already_submitted' => OfferIssue(
        OfferIssueKind.bafoAlreadySubmitted,
        error: error,
      ),
      'not_a_participant' => OfferIssue(
        OfferIssueKind.notParticipant,
        error: error,
      ),
      'too_many_requests' => OfferIssue(
        OfferIssueKind.rateLimited,
        error: error,
      ),
      'idempotency_key_reused' => OfferIssue(
        OfferIssueKind.keyReused,
        error: error,
      ),
      _ => OfferIssue(OfferIssueKind.other, error: error),
    };
  }

  final OfferIssueKind kind;
  final int? amountMinor;
  final DateTime? at;
  final ApiException? error;

  /// Shown under the amount field (the rest are banners).
  bool get isInline => switch (kind) {
    OfferIssueKind.stepNotMet ||
    OfferIssueKind.startPrice ||
    OfferIssueKind.granularity ||
    OfferIssueKind.amountInvalid ||
    OfferIssueKind.amountTooLarge ||
    OfferIssueKind.bafoReference => true,
    _ => false,
  };

  /// The competition or its state changed under the participant: refetch.
  bool get meansStale => switch (kind) {
    OfferIssueKind.notAccepting ||
    OfferIssueKind.closed ||
    OfferIssueKind.notShortlisted ||
    OfferIssueKind.bafoAlreadySubmitted ||
    OfferIssueKind.notParticipant => true,
    _ => false,
  };

  @override
  List<Object?> get props => [kind, amountMinor, at, error?.code];
}

sealed class OfferSubmitState extends Equatable {
  const OfferSubmitState();

  @override
  List<Object?> get props => [];
}

/// Editing the amount. [issue] is the last server answer to show;
/// [retryAt] disables submit after a 429 (device time, for the button).
final class OfferComposing extends OfferSubmitState {
  const OfferComposing({this.issue, this.retryAt});

  final OfferIssue? issue;
  final DateTime? retryAt;

  @override
  List<Object?> get props => [issue, retryAt];
}

/// The confirm sheet is open (M24). [key] was created for this intent.
final class OfferConfirming extends OfferSubmitState {
  const OfferConfirming({
    required this.amountMinor,
    required this.key,
    this.issue,
  });

  final int amountMinor;
  final String key;

  /// Why the user is asked again (`idempotency_key_reused`).
  final OfferIssue? issue;

  @override
  List<Object?> get props => [amountMinor, key, issue];
}

final class OfferSubmitting extends OfferSubmitState {
  const OfferSubmitting({
    required this.amountMinor,
    required this.key,
    this.confirmOutlier = false,
  });

  final int amountMinor;
  final String key;
  final bool confirmOutlier;

  @override
  List<Object?> get props => [amountMinor, key, confirmOutlier];
}

/// `offer_outlier_confirm_required` (M25): confirming re-sends with a new
/// key and `confirm_outlier: true`.
final class OfferOutlierConfirm extends OfferSubmitState {
  const OfferOutlierConfirm({
    required this.amountMinor,
    required this.changeBps,
    this.referenceAmountMinor,
  });

  final int amountMinor;
  final int changeBps;
  final int? referenceAmountMinor;

  /// Lower or higher than the current offer, from the two amounts (the
  /// wording varies by the sign of the change, not by direction).
  bool get isLower {
    final reference = referenceAmountMinor;
    return reference != null ? amountMinor < reference : changeBps < 0;
  }

  @override
  List<Object?> get props => [amountMinor, changeBps, referenceAmountMinor];
}

/// The outcome is unknown (network failure after one automatic retry):
/// «لم نتأكد من استلام عرضك. أعد المحاولة.». A retry reuses [key], so the
/// server answers with the original offer if it was accepted.
final class OfferUnconfirmed extends OfferSubmitState {
  const OfferUnconfirmed({
    required this.amountMinor,
    required this.key,
    this.confirmOutlier = false,
  });

  final int amountMinor;
  final String key;
  final bool confirmOutlier;

  @override
  List<Object?> get props => [amountMinor, key, confirmOutlier];
}

/// 201, or 200 replayed: the room applies [submission]'s snapshot.
final class OfferAccepted extends OfferSubmitState {
  const OfferAccepted(this.submission);

  final OfferSubmission submission;

  @override
  List<Object?> get props => [submission];
}

/// Offer submission (SCREENS.md S5, M24–M26). No optimistic state: the
/// room changes only from the server snapshot of the response.
///
/// * One `Idempotency-Key` per intent: made when the confirm step opens,
///   reused for every retry of that intent; a new intent (another amount,
///   a reopened sheet, the outlier re-send) gets a new key.
/// * A network failure or 5xx retries once after [retryDelay] with the
///   same key, then asks the user to retry (still the same key).
class OfferSubmitCubit extends Cubit<OfferSubmitState> {
  OfferSubmitCubit({
    required this._live,
    required this.competitionId,
    this.onAccepted,
    this.onStale,
    this.retryDelay = const Duration(seconds: 1),
    String Function()? keyFactory,
    DateTime Function()? deviceNow,
  }) : _newKey = keyFactory ?? IdempotencyKey.generate,
       _deviceNow = deviceNow ?? DateTime.now,
       super(const OfferComposing());

  final LiveRepository _live;
  final String competitionId;

  /// Feeds the accepted snapshot to the live room (`v` guard).
  final void Function(OfferSubmission submission)? onAccepted;

  /// The view is stale (closed, not accepting, not shortlisted, …).
  final void Function()? onStale;
  final Duration retryDelay;
  final String Function() _newKey;
  final DateTime Function() _deviceNow;

  /// Opens the confirm step for [amountMinor] with a new key (a new intent).
  void openConfirm(int amountMinor) {
    if (state is OfferSubmitting) return;
    emit(OfferConfirming(amountMinor: amountMinor, key: _newKey()));
  }

  /// Closes the confirm step without sending.
  void cancel() {
    if (state is OfferSubmitting) return;
    emit(const OfferComposing());
  }

  /// Sends the confirmed amount (from the confirm sheet).
  Future<void> submit() async {
    final current = state;
    if (current is! OfferConfirming) return;
    await _send(current.amountMinor, current.key, confirmOutlier: false);
  }

  /// After [OfferUnconfirmed]: the same intent, the same key.
  Future<void> retry() async {
    final current = state;
    if (current is! OfferUnconfirmed) return;
    await _send(
      current.amountMinor,
      current.key,
      confirmOutlier: current.confirmOutlier,
    );
  }

  /// The outlier dialog was confirmed: re-send with a **new** key.
  Future<void> confirmOutlier() async {
    final current = state;
    if (current is! OfferOutlierConfirm) return;
    await _send(current.amountMinor, _newKey(), confirmOutlier: true);
  }

  /// Back to editing (after a success, or when the user edits the amount).
  void acknowledge() {
    final current = state;
    if (current is OfferAccepted || current is OfferOutlierConfirm) {
      emit(const OfferComposing());
    }
  }

  /// Clears a shown issue once the user edits the amount (keeps a 429 wait).
  void clearIssue() {
    final current = state;
    if (current is OfferComposing && current.issue != null) {
      emit(OfferComposing(retryAt: current.retryAt));
    }
  }

  Future<void> _send(
    int amountMinor,
    String key, {
    required bool confirmOutlier,
  }) async {
    emit(
      OfferSubmitting(
        amountMinor: amountMinor,
        key: key,
        confirmOutlier: confirmOutlier,
      ),
    );
    var attempt = 0;
    while (true) {
      try {
        final submission = await _live.submitOffer(
          competitionId,
          amountMinor: amountMinor,
          idempotencyKey: key,
          confirmOutlier: confirmOutlier,
        );
        if (isClosed) return;
        emit(OfferAccepted(submission));
        onAccepted?.call(submission);
        return;
      } on ApiException catch (error) {
        if (isClosed) return;
        if (_outcomeUnknown(error)) {
          if (attempt == 0) {
            attempt++;
            await Future<void>.delayed(retryDelay);
            if (isClosed) return;
            continue;
          }
          emit(
            OfferUnconfirmed(
              amountMinor: amountMinor,
              key: key,
              confirmOutlier: confirmOutlier,
            ),
          );
          return;
        }
        _reject(error, amountMinor);
        return;
      }
    }
  }

  /// Timeouts, transport failures, 5xx and "still processing": the offer
  /// may or may not exist, so only the same key may be sent again.
  static bool _outcomeUnknown(ApiException error) =>
      error.isConnectivity ||
      error.code == 'idempotency_request_in_progress' ||
      (error.statusCode ?? 0) >= 500;

  void _reject(ApiException error, int amountMinor) {
    if (error.code == 'offer_outlier_confirm_required') {
      final change = error.details['change_bps'];
      final reference = error.details['reference_amount_minor'];
      emit(
        OfferOutlierConfirm(
          amountMinor: amountMinor,
          changeBps: change is int ? change : 0,
          referenceAmountMinor: reference is int ? reference : null,
        ),
      );
      return;
    }
    final issue = OfferIssue.fromError(error);
    switch (issue.kind) {
      case OfferIssueKind.keyReused:
        // Should not happen: a fresh key, and the user confirms again.
        emit(
          OfferConfirming(
            amountMinor: amountMinor,
            key: _newKey(),
            issue: issue,
          ),
        );
      case OfferIssueKind.rateLimited:
        final seconds = error.details['retry_after_seconds'];
        emit(
          OfferComposing(
            issue: issue,
            retryAt: _deviceNow().add(
              Duration(seconds: seconds is int && seconds > 0 ? seconds : 2),
            ),
          ),
        );
      default:
        emit(OfferComposing(issue: issue));
        if (issue.meansStale) onStale?.call();
    }
  }
}
