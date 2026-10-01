import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/l10n/generated/app_localizations.dart';
import 'package:flutter/widgets.dart';

export 'package:bafo/l10n/generated/app_localizations.dart';

extension L10nContext on BuildContext {
  /// Localised strings for the current app language.
  AppLocalizations get l10n => AppLocalizations.of(this);

  /// `ar` or `en`.
  String get languageCode => Localizations.localeOf(this).languageCode;
}

/// A user-facing message for [error] (SCREENS.md S7): the app's own
/// `errors.<code>` text when it has one, otherwise the server's localised
/// `message`, otherwise a generic text.
String errorMessage(AppLocalizations l10n, Object? error) {
  if (error is ApiException) {
    final local = errorText(l10n, error.code);
    if (local != null) return local;
    if (error.message.isNotEmpty) return error.message;
  }
  return l10n.errorsServerError;
}

/// The ARB text of `errors.<code>` (CONVENTIONS.md §8), or null for codes
/// the app has no text for.
String? errorText(AppLocalizations l10n, String code) => switch (code) {
  ApiErrorCode.network => l10n.errorsNetworkError,
  ApiErrorCode.timeout => l10n.errorsTimeout,
  ApiErrorCode.badResponse => l10n.errorsBadResponse,
  ApiErrorCode.unknown => l10n.errorsServerError,
  'offline' => l10n.errorsOffline,
  'server_error' => l10n.errorsServerError,
  'http_error' => l10n.errorsServerError,
  'bad_request' => l10n.errorsBadRequest,
  'unauthenticated' => l10n.errorsUnauthenticated,
  'forbidden' => l10n.errorsForbidden,
  'not_found' => l10n.errorsNotFound,
  'conflict' => l10n.errorsConflict,
  'payload_too_large' => l10n.errorsPayloadTooLarge,
  'unsupported_media_type' => l10n.errorsUnsupportedMediaType,
  'validation_failed' => l10n.errorsValidationFailed,
  'app_version_unsupported' => l10n.errorsAppVersionUnsupported,
  'too_many_requests' => l10n.errorsTooManyRequests,
  'service_unavailable' => l10n.errorsServiceUnavailable,
  'maintenance' => l10n.errorsMaintenance,
  'idempotency_key_required' => l10n.errorsIdempotencyKeyRequired,
  'idempotency_key_reused' => l10n.errorsIdempotencyKeyReused,
  'idempotency_request_in_progress' =>
    l10n.errorsIdempotencyRequestInProgress,
  'invalid_state_transition' => l10n.errorsInvalidStateTransition,
  'file_type_not_allowed' => l10n.errorsFileTypeNotAllowed,
  'file_too_large' => l10n.errorsFileTooLarge,
  // Identity
  'invalid_credentials' => l10n.errorsInvalidCredentials,
  'email_not_verified' => l10n.errorsEmailNotVerified,
  'account_inactive' => l10n.errorsAccountInactive,
  'organization_suspended' => l10n.errorsOrganizationSuspended,
  'otp_invalid' => l10n.errorsOtpInvalid,
  'otp_expired' => l10n.errorsOtpExpired,
  'otp_too_many_attempts' => l10n.errorsOtpTooManyAttempts,
  'otp_resend_cooldown' => l10n.errorsOtpResendCooldown,
  'password_incorrect' => l10n.errorsPasswordIncorrect,
  'team_invitation_invalid' => l10n.errorsTeamInvitationInvalid,
  'seat_limit_reached' => l10n.errorsSeatLimitReached,
  'cannot_modify_owner' => l10n.errorsCannotModifyOwner,
  'cannot_modify_self' => l10n.errorsCannotModifySelf,
  'account_deletion_blocked' => l10n.errorsAccountDeletionBlocked,
  'account_deletion_pending' => l10n.errorsAccountDeletionPending,
  'invitation_email_mismatch' => l10n.errorsInvitationEmailMismatch,
  // Competitions
  'competition_not_editable' => l10n.errorsCompetitionNotEditable,
  'issuer_plan_required' => l10n.errorsIssuerPlanRequired,
  'auction_not_enabled' => l10n.errorsAuctionNotEnabled,
  'min_participants_not_met' => l10n.errorsMinParticipantsNotMet,
  'max_participants_exceeded' => l10n.errorsMaxParticipantsExceeded,
  'live_event_capacity_reached' => l10n.errorsLiveEventCapacityReached,
  'extend_invalid' => l10n.errorsExtendInvalid,
  'invitation_cutoff_passed' => l10n.errorsInvitationCutoffPassed,
  'invitation_invalid' => l10n.errorsInvitationInvalid,
  'invitation_belongs_to_another_organization' =>
    l10n.errorsInvitationBelongsToAnotherOrganization,
  'join_deadline_passed' => l10n.errorsJoinDeadlinePassed,
  'already_participating' => l10n.errorsAlreadyParticipating,
  'terms_not_accepted' => l10n.errorsTermsNotAccepted,
  'not_a_participant' => l10n.errorsNotAParticipant,
  'comments_closed' => l10n.errorsCommentsClosed,
  'report_not_available' => l10n.errorsReportNotAvailable,
  // Bidding
  'offer_amount_invalid' => l10n.errorsOfferAmountInvalid,
  'offer_amount_too_large' => l10n.errorsOfferAmountTooLarge,
  'offer_granularity' => l10n.errorsOfferGranularity,
  'offer_not_accepting' => l10n.errorsOfferNotAccepting,
  'offer_closed' => l10n.errorsOfferClosed,
  'offer_not_shortlisted' => l10n.errorsOfferNotShortlisted,
  'offer_bafo_already_submitted' => l10n.errorsOfferBafoAlreadySubmitted,
  'offer_start_price' => l10n.errorsOfferStartPrice,
  'offer_step_not_met' => l10n.errorsOfferStepNotMet,
  'offer_bafo_worse_than_reference' => l10n.errorsOfferBafoWorseThanReference,
  'offer_outlier_confirm_required' => l10n.errorsOfferOutlierConfirmRequired,
  'bafo_not_enabled' => l10n.errorsBafoNotEnabled,
  'bafo_already_used' => l10n.errorsBafoAlreadyUsed,
  'award_participant_has_no_offer' => l10n.errorsAwardParticipantHasNoOffer,
  'award_justification_required' => l10n.errorsAwardJustificationRequired,
  'award_reserve_confirmation_required' =>
    l10n.errorsAwardReserveConfirmationRequired,
  // Billing (read-only app)
  'plan_required' => l10n.errorsPlanRequired,
  'purchase_not_available_on_platform' =>
    l10n.errorsPurchaseNotAvailableOnPlatform,
  'billing_profile_incomplete' => l10n.errorsBillingProfileIncomplete,
  'sponsorship_not_enabled' => l10n.errorsSponsorshipNotEnabled,
  'sponsorship_payment_required' => l10n.errorsSponsorshipPaymentRequired,
  'invoice_pdf_not_ready' => l10n.errorsInvoicePdfNotReady,
  _ => null,
};
