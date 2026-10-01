import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/rules.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:equatable/equatable.dart';

/// A display-only preview of the times the server derives at publish
/// (ARCHITECTURE.md §7.2). Drafts have none yet (SCREENS.md G3), so the
/// wizard shows these labelled «تقديري، يُثبَّت عند النشر».
final class SchedulePreview extends Equatable {
  const SchedulePreview({
    required this.opensAt,
    required this.closesAt,
    this.finalWindowStartsAt,
    this.invitationCutoffAt,
    this.hardStopAt,
  });

  /// [opensAt] null means "on publish": [publishAt] (server now) stands in.
  factory SchedulePreview.of({
    required DateTime? opensAt,
    required DateTime closesAt,
    required Rules rules,
    required DateTime publishAt,
  }) {
    final opens = opensAt ?? publishAt;
    final finalWindowMinutes = rules.finalWindowMinutes;
    final finalWindow = finalWindowMinutes == null
        ? null
        : closesAt.subtract(Duration(minutes: finalWindowMinutes));
    var cutoff =
        finalWindow ??
        closesAt.subtract(
          const Duration(minutes: DraftRules.inviteCutoffMinutes),
        );
    if (cutoff.isBefore(opens)) cutoff = opens;
    final autoExtend = rules.autoExtend;
    final hardStop =
        autoExtend.enabled &&
            autoExtend.bySeconds != null &&
            autoExtend.maxExtensions != null
        ? closesAt.add(
            Duration(
              seconds: autoExtend.bySeconds! * autoExtend.maxExtensions!,
            ),
          )
        : null;
    return SchedulePreview(
      opensAt: opensAt,
      closesAt: closesAt,
      finalWindowStartsAt: finalWindow,
      invitationCutoffAt: cutoff,
      hardStopAt: hardStop,
    );
  }

  /// Null: when the competition is published.
  final DateTime? opensAt;
  final DateTime closesAt;
  final DateTime? finalWindowStartsAt;
  final DateTime? invitationCutoffAt;
  final DateTime? hardStopAt;

  @override
  List<Object?> get props => [
    opensAt,
    closesAt,
    finalWindowStartsAt,
    invitationCutoffAt,
    hardStopAt,
  ];
}

/// One item of the pre-publish checklist (SCREENS.md M43, W15 step 8).
enum SetupItem {
  /// R18: the description is required at publish.
  description,

  /// R16: a closing time.
  schedule,

  /// R5: auctions need an opening price.
  startPrice,

  /// R15: an "Other" category needs its text.
  categoryOtherText,

  /// R17: at least `min_participants` invitations.
  invitations,
}

final class SetupCheck extends Equatable {
  const SetupCheck(this.item, {required this.done});

  final SetupItem item;
  final bool done;

  @override
  List<Object?> get props => [item, done];
}

/// Hints only: the server runs the real publish checks and its errors win.
List<SetupCheck> setupChecklist(Competition competition) {
  final category = competition.category;
  return [
    SetupCheck(
      SetupItem.description,
      done: (competition.description ?? '').trim().isNotEmpty,
    ),
    SetupCheck(
      SetupItem.schedule,
      done: competition.schedule.scheduledCloseAt != null,
    ),
    if (competition.direction == Direction.auction)
      SetupCheck(
        SetupItem.startPrice,
        done: competition.rules.startPriceMinor != null,
      ),
    if (category != null && category.isOther)
      SetupCheck(
        SetupItem.categoryOtherText,
        done: (competition.categoryOtherText ?? '').trim().isNotEmpty,
      ),
    SetupCheck(
      SetupItem.invitations,
      done:
          (competition.counts?.invitations ?? 0) >=
          competition.rules.minParticipants,
    ),
  ];
}

/// The issuer screens a competition offers, from its status (SCREENS.md
/// W13 tabs: Q&A, live and the offers log from `scheduled`; evaluation and
/// award from `closed`).
extension IssuerSections on Competition {
  bool get isPublished => status != CompetitionStatus.draft;

  /// Q&A and the live monitor exist once published (cancelled ones keep a
  /// read-only history).
  bool get hasLiveSections =>
      status != CompetitionStatus.draft && status != CompetitionStatus.unknown;

  /// Evaluation and award exist from the first close.
  bool get hasAwardSection => switch (status) {
    CompetitionStatus.closed ||
    CompetitionStatus.bafoRound ||
    CompetitionStatus.awarded ||
    CompetitionStatus.notAwarded => true,
    _ => false,
  };

  /// Documents can be added while draft, scheduled or live (API.md §1.4).
  // CONTRACT-GAP: `permissions` has no attachments flag; `can_edit` covers
  // the same statuses and the same `competitions.manage` permission.
  bool get acceptsAttachments =>
      permissions.canEdit &&
      (status == CompetitionStatus.draft ||
          status == CompetitionStatus.scheduled ||
          status == CompetitionStatus.live);

  /// Documents can be removed in draft or scheduled only.
  bool get allowsAttachmentRemoval =>
      permissions.canEdit &&
      (status == CompetitionStatus.draft ||
          status == CompetitionStatus.scheduled);

  /// Actions that are web-only on mobile (SCREENS.md CD5) but open to this
  /// user now: shown as «متاح في لوحة التحكم على الويب.».
  bool get hasWebOnlyActions =>
      permissions.canExtend ||
      permissions.canStartBafo ||
      permissions.canAward ||
      permissions.canRevokeAward ||
      permissions.canCloseWithoutAward;
}
