// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'BAFO';

  @override
  String get appTagline => 'Best and Final Offer';

  @override
  String get navHome => 'Home';

  @override
  String get navCompetitions => 'Participating';

  @override
  String get navMyCompetitions => 'My competitions';

  @override
  String get navMyCompetitionsTab => 'Issuing';

  @override
  String get navNotifications => 'Notifications';

  @override
  String get navAccount => 'Account';

  @override
  String get commonLanguageArabic => 'العربية';

  @override
  String get commonLanguageEnglish => 'English';

  @override
  String get commonLanguageSwitch => 'Change language';

  @override
  String get authLoginTitle => 'Sign in';

  @override
  String get authLoginSubtitle =>
      'Sign in to your organisation\'s BAFO account.';

  @override
  String get authFieldsEmailLabel => 'Email';

  @override
  String get authFieldsEmailHint => 'name@company.sa';

  @override
  String get authFieldsPasswordLabel => 'Password';

  @override
  String get commonPasswordShow => 'Show password';

  @override
  String get commonPasswordHide => 'Hide password';

  @override
  String get authLoginSubmit => 'Sign in';

  @override
  String get validationRequired => 'This field is required.';

  @override
  String get validationEmail => 'Enter a valid email address.';

  @override
  String get competitionsParticipatingEmptyTitle => 'No invitations yet';

  @override
  String get competitionsParticipatingEmptyMessage =>
      'When an issuer invites you to a competition, the invitation will appear here.';

  @override
  String get notificationsEmptyTitle => 'No notifications';

  @override
  String get notificationsEmptyMessage =>
      'Updates about competitions and offers will appear here.';

  @override
  String get profileLanguageTitle => 'App language';

  @override
  String get authLogoutAction => 'Sign out';

  @override
  String get authLogoutConfirmTitle => 'Sign out?';

  @override
  String get authLogoutConfirmMessage =>
      'You will need to sign in again to access your account.';

  @override
  String get commonActionsRetry => 'Try again';

  @override
  String get commonActionsCancel => 'Cancel';

  @override
  String get commonActionsConfirm => 'Confirm';

  @override
  String get commonActionsClose => 'Close';

  @override
  String get commonLoading => 'Loading';

  @override
  String get commonErrorTitle => 'The request could not be completed';

  @override
  String get errorsServerError => 'Something went wrong. Please try again.';

  @override
  String get errorsNetworkError =>
      'Cannot reach the server. Check your internet connection.';

  @override
  String get errorsTimeout =>
      'The request took longer than expected. Please try again.';

  @override
  String get errorsUnauthenticated =>
      'Your session has ended. Please sign in again.';

  @override
  String get commonUpdateRequiredTitle => 'Update required';

  @override
  String get commonUpdateRequiredMessage =>
      'This version of BAFO is no longer supported. Update the app from the store to continue.';

  @override
  String get commonMaintenanceTitle => 'BAFO is under maintenance';

  @override
  String get commonMaintenanceMessage =>
      'We are improving the service. Please try again shortly.';

  @override
  String commonCountdownDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days',
      one: '1 day',
    );
    return '$_temp0';
  }

  @override
  String get commonCountdownEnded => 'Time is up';

  @override
  String commonCountdownRemaining(String time) {
    return 'Time remaining $time';
  }

  @override
  String navNotificationsUnread(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count unread notifications',
      one: '1 unread notification',
      zero: 'No unread notifications',
    );
    return '$_temp0';
  }

  @override
  String get commonActionsNext => 'Next';

  @override
  String get commonActionsBack => 'Back';

  @override
  String get commonActionsSkip => 'Skip';

  @override
  String get commonActionsSave => 'Save';

  @override
  String get commonActionsDownload => 'Download';

  @override
  String get commonActionsOpen => 'Open';

  @override
  String get commonActionsDone => 'Done';

  @override
  String get commonActionsClear => 'Clear';

  @override
  String get commonActionsSearch => 'Search';

  @override
  String get commonActionsSelect => 'Choose';

  @override
  String get commonActionsRemove => 'Remove';

  @override
  String get commonActionsCamera => 'Take a photo';

  @override
  String get commonActionsGallery => 'Choose from photos';

  @override
  String get commonFieldRequired => 'required';

  @override
  String get commonFieldOptional => 'optional';

  @override
  String commonStepOf(int current, int total) {
    return 'Step $current of $total';
  }

  @override
  String commonPageOf(int current, int total) {
    return 'Page $current of $total';
  }

  @override
  String commonRetryIn(int seconds) {
    return 'Try again in ${seconds}s';
  }

  @override
  String get commonPricesExcludeVat => 'Prices exclude VAT';

  @override
  String get commonOfflineBanner => 'You are offline.';

  @override
  String get commonOfflineActionsDisabled =>
      'This action needs an internet connection.';

  @override
  String get commonDownloading => 'Downloading';

  @override
  String get commonDownloadFailed => 'The file could not be downloaded.';

  @override
  String get commonNoAppToOpen => 'No app on this device can open this file.';

  @override
  String get commonLinkOpenFailed => 'The link could not be opened.';

  @override
  String get commonExternalLink => 'External link';

  @override
  String get commonAddendum => 'Addendum';

  @override
  String commonFileSizeBytes(String size) {
    return '$size B';
  }

  @override
  String commonFileSizeKb(String size) {
    return '$size KB';
  }

  @override
  String commonFileSizeMb(String size) {
    return '$size MB';
  }

  @override
  String commonTimeRiyadh(String dateTime) {
    return '$dateTime Riyadh time';
  }

  @override
  String get commonTimeJustNow => 'Just now';

  @override
  String commonTimeMinutesAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count minutes ago',
      one: '1 minute ago',
    );
    return '$_temp0';
  }

  @override
  String commonTimeHoursAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count hours ago',
      one: '1 hour ago',
    );
    return '$_temp0';
  }

  @override
  String commonTimeDaysAgo(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days ago',
      one: '1 day ago',
    );
    return '$_temp0';
  }

  @override
  String get commonForbiddenTitle => 'Access not allowed';

  @override
  String get commonNotFoundTitle => 'Not available';

  @override
  String get commonSupportTitle => 'Contact BAFO support';

  @override
  String get commonSupportEmail => 'E-mail';

  @override
  String get commonSupportPhone => 'Phone';

  @override
  String get commonSupportWhatsapp => 'WhatsApp';

  @override
  String get commonUpdateAction => 'Update the app';

  @override
  String get commonAccountBlockedTitle =>
      'This account cannot be used right now';

  @override
  String get commonRuleMet => 'met';

  @override
  String get commonRuleNotMet => 'not met';

  @override
  String commonSelectedCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count selected',
      one: '1 selected',
      zero: 'Nothing selected',
    );
    return '$_temp0';
  }

  @override
  String get commonDateTimePick => 'Choose date and time';

  @override
  String get validationPhone =>
      'Enter a Saudi mobile number: 9 digits starting with 5.';

  @override
  String get validationCr => 'The commercial registration number is 10 digits.';

  @override
  String get validationVat =>
      'The VAT number is 15 digits starting and ending with 3.';

  @override
  String get validationPasswordRules => 'The password does not meet the rules.';

  @override
  String get validationPasswordMismatch => 'The passwords do not match.';

  @override
  String get validationUrlHttps => 'Enter a link that starts with https://';

  @override
  String validationMaxLength(int max) {
    return 'At most $max characters.';
  }

  @override
  String validationExactDigits(int count) {
    return 'Enter $count digits.';
  }

  @override
  String get validationShortAddress =>
      'The short address is 4 capital letters then 4 digits, e.g. ABCD1234.';

  @override
  String get validationOtp => 'Enter the 6-digit code.';

  @override
  String get validationCategoriesMax => 'You can choose up to 20 categories.';

  @override
  String get validationAmount =>
      'Enter a valid amount with at most two decimals.';

  @override
  String get validationAmountPositive =>
      'The amount must be greater than zero.';

  @override
  String get validationAmountWholeRiyals =>
      'Enter the amount in whole riyals, without halalas.';

  @override
  String get authWelcomeLanguageTitle => 'Choose the app language';

  @override
  String get authWelcomeSlide1Title => 'Issue a competition';

  @override
  String get authWelcomeSlide1Body =>
      'Create a tender or an auction with clear rules and a set schedule.';

  @override
  String get authWelcomeSlide2Title => 'Invite participants';

  @override
  String get authWelcomeSlide2Body =>
      'Invite suppliers or bidders by e-mail or from suggestions, and follow who joins.';

  @override
  String get authWelcomeSlide3Title => 'Compete live, then award';

  @override
  String get authWelcomeSlide3Body =>
      'Offers arrive live, timed by the BAFO server, and the award follows clearly.';

  @override
  String get authWelcomeCreateAccount => 'Create a company account';

  @override
  String get authLoginForgot => 'Forgot your password?';

  @override
  String get authLoginNoAccount => 'No company account yet?';

  @override
  String get authLoginCreateAccount => 'Create one';

  @override
  String get authRegisterTitle => 'Create a company account';

  @override
  String get authRegisterStepAccount => 'Your account';

  @override
  String get authRegisterStepCompany => 'Company details';

  @override
  String get authRegisterStepAddress => 'Address and consent';

  @override
  String get authRegisterSubmit => 'Create account';

  @override
  String get authRegisterHaveAccount => 'Already have a company account?';

  @override
  String get authRegisterSignIn => 'Sign in';

  @override
  String get authRegisterFixErrors =>
      'Check the highlighted fields and try again.';

  @override
  String get authRegisterAcceptTerms => 'I accept the terms and conditions';

  @override
  String get authRegisterAcceptPrivacy => 'I accept the privacy policy';

  @override
  String get authRegisterConsentRequired =>
      'Your consent is required to continue.';

  @override
  String get authRegisterReadDocument => 'Read';

  @override
  String get authFieldsNameLabel => 'Full name';

  @override
  String get authFieldsPhoneLabel => 'Mobile number';

  @override
  String get authFieldsPhoneHint => '5XXXXXXXX';

  @override
  String get authFieldsPasswordConfirmLabel => 'Confirm password';

  @override
  String get authPasswordRulesTitle => 'Your password needs:';

  @override
  String get authPasswordRuleLength => 'At least 8 characters';

  @override
  String get authPasswordRuleLower => 'A lowercase letter';

  @override
  String get authPasswordRuleUpper => 'An uppercase letter';

  @override
  String get authPasswordRuleDigit => 'A number';

  @override
  String get authPasswordRuleSymbol => 'A symbol such as @ or #';

  @override
  String get organizationFieldsNameLabel => 'Company name';

  @override
  String get organizationFieldsCrLabel => 'Commercial registration number';

  @override
  String get organizationFieldsCrHelper => '10 digits';

  @override
  String get organizationFieldsRegionLabel => 'Region';

  @override
  String get organizationFieldsCityLabel => 'City';

  @override
  String get organizationFieldsVatRegisteredLabel =>
      'The company is VAT registered';

  @override
  String get organizationFieldsVatLabel => 'VAT number';

  @override
  String get organizationFieldsVatHelper =>
      '15 digits, starting and ending with 3';

  @override
  String get organizationFieldsLegalNameArLabel => 'Legal name in Arabic';

  @override
  String get organizationFieldsLegalNameEnLabel => 'Legal name in English';

  @override
  String get organizationFieldsWebsiteLabel => 'Website';

  @override
  String get organizationFieldsWebsiteHint => 'https://example.sa';

  @override
  String get organizationFieldsCategoriesLabel => 'Categories';

  @override
  String get organizationFieldsCategoriesHelper =>
      'Choose up to 20 categories your company works in.';

  @override
  String get organizationFieldsVisibleInSuggestions =>
      'Show my company in issuers\' suggestions';

  @override
  String get organizationAddressTitle => 'National address';

  @override
  String get organizationAddressHelper =>
      'Optional now; needed later for invoices.';

  @override
  String get organizationAddressBuildingNumber => 'Building number';

  @override
  String get organizationAddressStreet => 'Street';

  @override
  String get organizationAddressDistrict => 'District';

  @override
  String get organizationAddressPostalCode => 'Postal code';

  @override
  String get organizationAddressAdditionalNumber => 'Additional number';

  @override
  String get organizationAddressShortAddress => 'Short address';

  @override
  String get authVerifyTitle => 'Verify your e-mail';

  @override
  String authVerifyMessage(String email) {
    return 'We sent a 6-digit code to $email.';
  }

  @override
  String get authVerifySubmit => 'Verify';

  @override
  String get authOtpLabel => 'Verification code';

  @override
  String authOtpExpiresIn(String time) {
    return 'The code expires in $time';
  }

  @override
  String get authOtpExpired => 'The code has expired. Request a new one.';

  @override
  String get authOtpResend => 'Resend code';

  @override
  String authOtpResendIn(int seconds) {
    return 'Resend in ${seconds}s';
  }

  @override
  String get authOtpResent => 'We sent a new code.';

  @override
  String get authForgotTitle => 'Reset your password';

  @override
  String get authForgotMessage =>
      'Enter your registered e-mail and we will send you a verification code.';

  @override
  String get authForgotSubmit => 'Send code';

  @override
  String get authForgotSent =>
      'If this e-mail is registered, you will receive a message with a verification code.';

  @override
  String get authResetTitle => 'Set a new password';

  @override
  String authResetMessage(String email) {
    return 'Enter the code sent to $email, then choose a new password.';
  }

  @override
  String get authResetCodeValid => 'The code is valid.';

  @override
  String get authResetNewPasswordLabel => 'New password';

  @override
  String get authResetSubmit => 'Save password';

  @override
  String get authResetDone => 'Your password was changed. Sign in.';

  @override
  String get legalLinksTerms => 'Terms and conditions';

  @override
  String get legalLinksPrivacy => 'Privacy policy';

  @override
  String get legalLinksCompetitionRules => 'Competition rules';

  @override
  String legalVersion(String version) {
    return 'Version $version';
  }

  @override
  String legalPublishedOn(String date) {
    return 'Published on $date';
  }

  @override
  String get legalUnavailable => 'This document is not available right now.';

  @override
  String competitionsDirectionLabel(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Tender',
      'auction': 'Auction',
      'other': 'Competition',
    });
    return '$_temp0';
  }

  @override
  String competitionsDirectionRule(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Lowest offer wins',
      'auction': 'Highest offer wins',
      'other': 'Competition',
    });
    return '$_temp0';
  }

  @override
  String get competitionsFormatLive => 'Live';

  @override
  String get competitionsFormatSealed => 'Sealed';

  @override
  String get competitionsStatusDraft => 'Draft';

  @override
  String get competitionsStatusScheduled => 'Scheduled';

  @override
  String get competitionsStatusLive => 'Open for offers';

  @override
  String get competitionsStatusFinalWindow => 'Final pricing window';

  @override
  String get competitionsStatusLiveSealed => 'Open · sealed offers';

  @override
  String get competitionsStatusClosed => 'Evaluation';

  @override
  String get competitionsStatusBafoRound => 'BAFO round';

  @override
  String get competitionsStatusAwarded => 'Awarded';

  @override
  String get competitionsStatusNotAwarded => 'Closed without award';

  @override
  String get competitionsStatusCancelled => 'Cancelled';

  @override
  String get competitionsStatusUnknown => 'Unknown status';

  @override
  String get competitionsStatusClosingSoon => 'Closing soon';

  @override
  String get competitionsStatusExtended => 'Extended';

  @override
  String get competitionsCountdownOpensIn => 'Opens in';

  @override
  String get competitionsCountdownClosesIn => 'Closes in';

  @override
  String get competitionsCountdownJoinBefore => 'Join before';

  @override
  String get competitionsCountdownBafoEndsIn => 'The BAFO round ends in';

  @override
  String get competitionsCountdownClosing => 'Closing…';

  @override
  String competitionsCountdownAnnounceMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count minutes left before closing',
      one: '1 minute left before closing',
    );
    return '$_temp0';
  }

  @override
  String competitionsExtensionBanner(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Closing was extended $count times.',
      two: 'Closing was extended twice.',
      one: 'Closing was extended once.',
    );
    return '$_temp0';
  }

  @override
  String competitionsLatestPossibleClose(String time) {
    return 'Latest possible close: $time';
  }

  @override
  String get rulesSummaryTitle => 'Competition rules';

  @override
  String get liveStatusLeading => 'Your offer is the leading offer';

  @override
  String liveStatusNotLeading(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Your offer is not leading. Lower your offer to compete.',
      'auction': 'Your offer is not leading. Raise your offer to compete.',
      'other': 'Your offer is not leading.',
    });
    return '$_temp0';
  }

  @override
  String liveStatusRank(int rank, int count) {
    return 'You are ranked $rank of $count';
  }

  @override
  String get liveStatusHidden =>
      'Your offer was received. This competition does not show standings.';

  @override
  String get liveStatusNoOffer => 'You have not submitted an offer yet.';

  @override
  String get liveBadgeLeading => 'Leading';

  @override
  String get liveBadgeNotLeading => 'Not leading';

  @override
  String get invitationsAccessJoinRequired => 'Join required';

  @override
  String get invitationsAccessPlanRequired => 'Plan required';

  @override
  String get invitationsAccessFull => 'Joined';

  @override
  String get invitationsAccessReadOnly => 'Read only';

  @override
  String get invitationsAccessUnavailable => 'Unavailable';

  @override
  String get sponsorshipBadgeFeesCovered => 'Fees covered';

  @override
  String sponsorshipCoveredBy(String issuer) {
    return '$issuer covers your participation fees for this competition.';
  }

  @override
  String get sponsorshipManagedOnWeb =>
      'Participation fees for this competition are paid from the BAFO web dashboard. Your draft is saved.';

  @override
  String get awardOutcomeWon => 'Awarded to you';

  @override
  String get awardOutcomeNotSelected => 'Not selected';

  @override
  String get awardOutcomeNotAwarded => 'Closed without award';

  @override
  String get competitionsDetailTitle => 'Competition details';

  @override
  String get competitionsDetailIssuer => 'Issuer';

  @override
  String get competitionsDetailCategory => 'Category';

  @override
  String get competitionsDetailRegion => 'Region';

  @override
  String get competitionsDetailReference => 'Reference';

  @override
  String get competitionsDetailDescription => 'Description';

  @override
  String get competitionsDetailSchedule => 'Schedule';

  @override
  String get competitionsDetailDocuments => 'Documents';

  @override
  String get competitionsDetailNoDocuments => 'No documents.';

  @override
  String get competitionsDetailNotFound =>
      'This competition does not exist or you do not have access to it.';

  @override
  String competitionsDetailCancelled(String reason) {
    return 'Cancellation reason: $reason';
  }

  @override
  String competitionsDetailNotAwarded(String reason) {
    return 'Reason for closing without award: $reason';
  }

  @override
  String get competitionsScheduleOpensAt => 'Offers open';

  @override
  String get competitionsScheduleClosesAt => 'Closing time';

  @override
  String get competitionsScheduleJoinDeadline => 'Join deadline';

  @override
  String get competitionsScheduleFinalWindow => 'Final pricing window starts';

  @override
  String get competitionsScheduleOpensOnPublish => 'On publish';

  @override
  String get billingManagedOnWeb =>
      'Subscriptions and payments are managed from the BAFO web dashboard.';

  @override
  String get billingInvoicesOnWeb =>
      'Invoices are available in the BAFO web dashboard.';

  @override
  String get billingStatusTitle => 'Plan and subscription';

  @override
  String get billingStatusPlan => 'Plan';

  @override
  String get billingStatusSource => 'Subscription type';

  @override
  String get billingStatusState => 'Status';

  @override
  String get billingStatusEnds => 'Ends on';

  @override
  String get billingStatusSeats => 'Seats';

  @override
  String billingStatusSeatsValue(int used, int total) {
    return '$used of $total';
  }

  @override
  String billingStatusDaysLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days left',
      one: '1 day left',
      zero: 'Ends today',
    );
    return '$_temp0';
  }

  @override
  String get billingStatusNoPlan => 'Your organisation has no active plan.';

  @override
  String get billingStatusUpcoming => 'Next subscription';

  @override
  String get billingSourcePaid => 'Paid';

  @override
  String get billingSourceTrial => 'Free trial';

  @override
  String get billingSourceGrant => 'Granted by BAFO';

  @override
  String get billingSubscriptionActive => 'Active';

  @override
  String get billingSubscriptionPendingPayment => 'Awaiting payment';

  @override
  String get billingSubscriptionExpired => 'Expired';

  @override
  String get billingSubscriptionSuperseded => 'Replaced';

  @override
  String get billingSubscriptionCancelled => 'Cancelled';

  @override
  String get billingSubscriptionUnknown => 'Unknown';

  @override
  String get errorsBadRequest => 'The request could not be understood.';

  @override
  String get errorsForbidden =>
      'You do not have permission for this page. Contact your account owner.';

  @override
  String get errorsNotFound =>
      'This item does not exist or you do not have access to it.';

  @override
  String get errorsConflict =>
      'The request conflicts with the current state. Refresh and try again.';

  @override
  String get errorsPayloadTooLarge => 'The request is larger than allowed.';

  @override
  String get errorsUnsupportedMediaType =>
      'This content type is not supported.';

  @override
  String get errorsValidationFailed => 'Check the information you entered.';

  @override
  String get errorsAppVersionUnsupported =>
      'This version of the app is no longer supported. Update the app to continue.';

  @override
  String get errorsTooManyRequests => 'Too many attempts. Try again shortly.';

  @override
  String get errorsServiceUnavailable =>
      'The service is temporarily unavailable. Try again shortly.';

  @override
  String get errorsMaintenance =>
      'BAFO is under maintenance. Try again shortly.';

  @override
  String get errorsIdempotencyKeyRequired =>
      'The request could not be sent. Try again.';

  @override
  String get errorsIdempotencyKeyReused =>
      'The request could not be confirmed. Confirm it again.';

  @override
  String get errorsIdempotencyRequestInProgress =>
      'Your previous request is still being processed. Wait a moment.';

  @override
  String get errorsInvalidStateTransition =>
      'This action is no longer available in the current state.';

  @override
  String get errorsFileTypeNotAllowed => 'This file type is not allowed.';

  @override
  String get errorsFileTooLarge => 'The file is larger than allowed.';

  @override
  String get errorsBadResponse =>
      'The server sent an unexpected answer. Try again.';

  @override
  String get errorsOffline => 'You are offline.';

  @override
  String get errorsInvalidCredentials => 'The e-mail or password is incorrect.';

  @override
  String get errorsEmailNotVerified =>
      'Verify your e-mail to continue. We sent you a verification code.';

  @override
  String get errorsAccountInactive =>
      'Your account is not active in this organisation. Contact your account owner.';

  @override
  String get errorsOrganizationSuspended =>
      'Your organisation\'s account is suspended. Contact BAFO support.';

  @override
  String get errorsOtpInvalid => 'The code is incorrect.';

  @override
  String get errorsOtpExpired => 'The code has expired. Request a new one.';

  @override
  String get errorsOtpTooManyAttempts =>
      'The code was cancelled after too many attempts. Request a new one.';

  @override
  String get errorsOtpResendCooldown =>
      'Wait a moment before requesting a new code.';

  @override
  String get errorsPasswordIncorrect => 'The current password is incorrect.';

  @override
  String get errorsTeamInvitationInvalid =>
      'This invitation link is invalid or has expired. Ask your account admin to resend it.';

  @override
  String get errorsSeatLimitReached => 'All seats of your plan are in use.';

  @override
  String get errorsCannotModifyOwner =>
      'The account owner cannot be changed or removed.';

  @override
  String get errorsCannotModifySelf =>
      'You cannot change or remove your own membership.';

  @override
  String get errorsAccountDeletionBlocked =>
      'The account cannot be deleted while competitions or participations are open.';

  @override
  String get errorsAccountDeletionPending =>
      'A deletion request is already pending.';

  @override
  String get errorsInvitationEmailMismatch =>
      'Register with the invited e-mail address.';

  @override
  String get errorsCompetitionNotEditable =>
      'This cannot be changed in the current status of the competition.';

  @override
  String get errorsIssuerPlanRequired =>
      'Your organisation needs an active plan to issue competitions.';

  @override
  String get errorsAuctionNotEnabled =>
      'Auctions are not enabled for your organisation. Contact BAFO.';

  @override
  String get errorsMinParticipantsNotMet =>
      'Fewer invitations than the minimum number of participants.';

  @override
  String get errorsMaxParticipantsExceeded =>
      'The maximum number of participants was exceeded.';

  @override
  String get errorsLiveEventCapacityReached =>
      'The number of live competitions at this time has reached the limit. Choose another time.';

  @override
  String get errorsExtendInvalid => 'The new closing time is not allowed.';

  @override
  String get errorsInvitationCutoffPassed =>
      'The invitation deadline of this competition has passed.';

  @override
  String get errorsInvitationInvalid =>
      'This invitation link is invalid or has expired.';

  @override
  String get errorsInvitationBelongsToAnotherOrganization =>
      'This invitation belongs to another organisation.';

  @override
  String get errorsJoinDeadlinePassed =>
      'The join deadline of this competition has passed.';

  @override
  String get errorsAlreadyParticipating =>
      'Your organisation already takes part in this competition.';

  @override
  String get errorsTermsNotAccepted => 'Accept the competition rules to join.';

  @override
  String get errorsNotAParticipant =>
      'This action is only for organisations that joined the competition.';

  @override
  String get errorsCommentsClosed =>
      'Questions are closed for this competition.';

  @override
  String get errorsReportNotAvailable =>
      'The report is not available before the competition closes.';

  @override
  String get errorsOfferAmountInvalid =>
      'Enter a valid amount greater than zero.';

  @override
  String get errorsOfferAmountTooLarge =>
      'The amount is above the allowed maximum.';

  @override
  String get errorsOfferGranularity =>
      'The amount does not match the amount precision of this competition.';

  @override
  String get errorsOfferNotAccepting =>
      'The competition is not accepting offers right now.';

  @override
  String get errorsOfferClosed => 'Offers are closed.';

  @override
  String get errorsOfferNotShortlisted =>
      'Your organisation is not on the shortlist of the best-and-final-offer round.';

  @override
  String get errorsOfferBafoAlreadySubmitted =>
      'You have already submitted your final offer.';

  @override
  String get errorsOfferStartPrice =>
      'The offer does not meet the starting price.';

  @override
  String get errorsOfferStepNotMet => 'The offer does not improve enough.';

  @override
  String get errorsOfferBafoWorseThanReference =>
      'Your final offer cannot be worse than your last offer.';

  @override
  String get errorsOfferOutlierConfirmRequired =>
      'This offer differs a lot from your current offer. Confirm it to continue.';

  @override
  String get errorsBafoNotEnabled =>
      'The best-and-final-offer round is not enabled for this competition.';

  @override
  String get errorsBafoAlreadyUsed =>
      'This competition already had a best-and-final-offer round.';

  @override
  String get errorsAwardParticipantHasNoOffer =>
      'This participant has no current offer.';

  @override
  String get errorsAwardJustificationRequired =>
      'This award needs a justification.';

  @override
  String get errorsAwardReserveConfirmationRequired =>
      'Confirm the award although the target or reserve price is not met.';

  @override
  String get errorsPlanRequired =>
      'Your organisation needs an active plan to join.';

  @override
  String get errorsPurchaseNotAvailableOnPlatform =>
      'Subscriptions and payments are managed from the BAFO web dashboard.';

  @override
  String get errorsBillingProfileIncomplete =>
      'Your organisation\'s billing details are incomplete.';

  @override
  String get errorsSponsorshipNotEnabled =>
      'Covering participation fees is not enabled for your organisation.';

  @override
  String get errorsSponsorshipPaymentRequired =>
      'Participation fees for this competition are paid from the BAFO web dashboard.';

  @override
  String get errorsInvoicePdfNotReady => 'The invoice is not ready yet.';

  @override
  String get commonNoteLabel => 'Note';

  @override
  String homeGreeting(String name) {
    return 'Hello, $name';
  }

  @override
  String get homeGreetingFallback => 'Hello';

  @override
  String get homeIssuerSectionTitle => 'Competitions you issue';

  @override
  String get homeParticipantSectionTitle => 'Your participation';

  @override
  String get homeStatsActiveCompetitions => 'Active competitions';

  @override
  String get homeStatsDraftCompetitions => 'Drafts';

  @override
  String get homeStatsLiveNow => 'Open for offers now';

  @override
  String get homeStatsAwaitingAward => 'Awaiting award';

  @override
  String get homeStatsOffersReceived30d => 'Offers received, last 30 days';

  @override
  String get homeStatsPendingInvitations => 'Invitations awaiting your reply';

  @override
  String get homeStatsActiveParticipations => 'Active participations';

  @override
  String get homeStatsOffersSubmitted30d => 'Offers submitted, last 30 days';

  @override
  String get homeStatsAwardsWon => 'Awards won';

  @override
  String get homeQuickActionsTitle => 'Quick actions';

  @override
  String get homeActionCreateCompetition => 'Create a competition';

  @override
  String get homeActionParticipating => 'View my participations';

  @override
  String get homeActionMyCompetitions => 'View my competitions';

  @override
  String get homeCreateNeedsPlan =>
      'An active plan is required to issue competitions.';

  @override
  String get homeSubscriptionTitle => 'Plan';

  @override
  String homeSubscriptionEnds(String date) {
    return 'Ends on $date';
  }

  @override
  String get homeSubscriptionOpen => 'Plan details';

  @override
  String homeAlertSubscriptionExpiring(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Your organisation\'s subscription ends in $count days.',
      one: 'Your organisation\'s subscription ends tomorrow.',
      zero: 'Your organisation\'s subscription ends today.',
    );
    return '$_temp0';
  }

  @override
  String get homeAlertSubscriptionExpiringSoon =>
      'Your organisation\'s subscription ends soon.';

  @override
  String get homeAlertSubscriptionExpired =>
      'Your organisation\'s subscription has expired.';

  @override
  String get homeAlertPlanRequired =>
      'Your organisation needs an active plan to issue and join competitions.';

  @override
  String get homeAlertTrialAvailable =>
      'A free trial is available for your organisation.';

  @override
  String get homeAlertBillingProfileIncomplete =>
      'Your organisation\'s billing details are incomplete.';

  @override
  String get homeAlertBillingProfileAction =>
      'Complete them on the organisation details page.';

  @override
  String get homeActivityTitle => 'Recent activity';

  @override
  String get homeActivityEmpty => 'No activity yet.';

  @override
  String homeActivityCompetitionCreated(String title) {
    return 'Draft “$title” created.';
  }

  @override
  String homeActivityCompetitionPublished(String title) {
    return '“$title” published.';
  }

  @override
  String homeActivityCompetitionCancelled(String title) {
    return '“$title” cancelled.';
  }

  @override
  String homeActivityCompetitionClosed(String title) {
    return 'Offers closed for “$title”.';
  }

  @override
  String homeActivityAwardIssued(String title) {
    return '“$title” awarded.';
  }

  @override
  String homeActivityInvitationJoined(String title) {
    return 'You joined “$title”.';
  }

  @override
  String get homeActivityMemberAdded => 'A team member was added.';

  @override
  String get homeActivitySubscriptionActivated =>
      'Your organisation\'s subscription was activated.';

  @override
  String get homeActivityOther => 'Your organisation\'s account was updated.';

  @override
  String get homeActivityActorSystem => 'System';

  @override
  String get homeRefreshFailed =>
      'Could not refresh. Showing the last loaded data.';

  @override
  String get notificationsFilterAll => 'All';

  @override
  String get notificationsFilterUnread => 'Unread';

  @override
  String get notificationsUnreadEmptyTitle => 'No unread notifications';

  @override
  String get notificationsUnreadEmptyMessage =>
      'You have read all your notifications.';

  @override
  String get notificationsMarkAllRead => 'Mark all as read';

  @override
  String get notificationsMarkRead => 'Mark as read';

  @override
  String get notificationsDelete => 'Delete notification';

  @override
  String get notificationsDeleteAll => 'Delete all notifications';

  @override
  String get notificationsDeleteAllConfirmTitle => 'Delete all notifications?';

  @override
  String get notificationsDeleteAllConfirmMessage =>
      'All your notifications will be deleted permanently.';

  @override
  String get notificationsDeleted => 'Notification deleted.';

  @override
  String get notificationsAllMarkedRead =>
      'All notifications are marked as read.';

  @override
  String get notificationsAllDeleted => 'All notifications were deleted.';

  @override
  String get notificationsUnreadLabel => 'Unread';

  @override
  String get notificationsMoreActions => 'More actions';

  @override
  String get notificationsLoadMoreFailed => 'Could not load more.';

  @override
  String get notificationsPushTitle => 'Turn on push notifications';

  @override
  String get notificationsPushBody =>
      'We alert you when a new invitation arrives, when offers open or closing is near, and when the result is out. Alerts never include amounts.';

  @override
  String get notificationsPushAllow => 'Turn on notifications';

  @override
  String get notificationsPushNotNow => 'Not now';

  @override
  String get notificationsPushEnabled =>
      'Push notifications are on for this device.';

  @override
  String get notificationsPushDenied =>
      'Push notifications are blocked. You can allow them in your device settings.';

  @override
  String get notificationsPushUnavailable =>
      'Push notifications are not available on this device yet. You still get notifications in the app.';

  @override
  String get notificationsPushOff => 'Push notifications are off.';

  @override
  String get notificationsPushSettingTitle => 'Push notifications';

  @override
  String get notificationsPushTurnOn => 'Turn on';

  @override
  String get accountSectionAccount => 'My account';

  @override
  String get accountSectionOrganization => 'Organisation';

  @override
  String get accountSectionApp => 'App';

  @override
  String get accountRoleOwner => 'Owner';

  @override
  String get accountRoleAdmin => 'Admin';

  @override
  String get accountRoleMember => 'Member';

  @override
  String get accountVerified => 'Verified organisation';

  @override
  String accountVersion(String version) {
    return 'Version $version';
  }

  @override
  String get accountMeUnavailable =>
      'Your account details could not be loaded. Pull to refresh when you are online.';

  @override
  String get accountSaved => 'Changes saved.';

  @override
  String get accountDiscardTitle => 'Discard changes?';

  @override
  String get accountDiscardMessage => 'Your changes are not saved yet.';

  @override
  String get accountDiscardAction => 'Discard';

  @override
  String get accountNotSet => 'Not set';

  @override
  String get accountImagePick => 'Choose an image';

  @override
  String get accountImageRemove => 'Remove image';

  @override
  String get accountProfileTitle => 'Profile';

  @override
  String get accountProfileEmailHelper =>
      'Used to sign in. It cannot be changed.';

  @override
  String get accountProfileAvatarChange => 'Change profile photo';

  @override
  String get accountProfileAvatarHint => 'PNG, JPG or WEBP, up to 2 MB.';

  @override
  String get accountProfileAvatarUpdated => 'Profile photo updated.';

  @override
  String get accountProfileAvatarRemoved => 'Profile photo removed.';

  @override
  String get accountPasswordTitle => 'Change password';

  @override
  String get accountPasswordCurrent => 'Current password';

  @override
  String get accountPasswordNew => 'New password';

  @override
  String get accountPasswordConfirm => 'Confirm the new password';

  @override
  String get accountPasswordOtherDevices =>
      'You will be signed out on your other devices.';

  @override
  String get accountPasswordChanged =>
      'Your password was changed, and you were signed out on your other devices.';

  @override
  String get accountOrganizationTitle => 'Organisation details';

  @override
  String get accountOrganizationEdit => 'Edit details';

  @override
  String get accountOrganizationReadOnly =>
      'The account owner or an admin can edit the organisation details.';

  @override
  String get accountOrganizationIdentity => 'Identity';

  @override
  String get accountOrganizationTax => 'Tax';

  @override
  String get accountOrganizationLocation => 'Location';

  @override
  String get accountOrganizationContact => 'Contact';

  @override
  String get accountOrganizationActivity => 'Activity';

  @override
  String get accountOrganizationEmail => 'Organisation e-mail';

  @override
  String get accountOrganizationPhone => 'Organisation phone';

  @override
  String get accountOrganizationCrReadOnly =>
      'The CR number cannot be changed.';

  @override
  String get accountOrganizationVatRegistered => 'Registered';

  @override
  String get accountOrganizationVatNotRegistered => 'Not registered';

  @override
  String get accountOrganizationSuggestionsOn => 'Shown in issuer suggestions';

  @override
  String get accountOrganizationSuggestionsOff =>
      'Hidden from issuer suggestions';

  @override
  String get accountOrganizationFeatures => 'Features';

  @override
  String get accountOrganizationFeatureApi => 'API access';

  @override
  String get accountOrganizationFeatureAuction => 'Auctions';

  @override
  String get accountOrganizationFeatureSponsorship =>
      'Covering participation fees';

  @override
  String get accountOrganizationFeatureOn => 'On';

  @override
  String get accountOrganizationFeatureOff => 'Off';

  @override
  String get accountOrganizationFeaturesNote =>
      'Contact BAFO to turn these on.';

  @override
  String accountOrganizationBillingIncomplete(String fields) {
    return 'Billing details are incomplete. Missing: $fields.';
  }

  @override
  String get accountOrganizationOtherFields => 'other fields';

  @override
  String get accountOrganizationComplete => 'Complete details';

  @override
  String get accountOrganizationLogoChange => 'Change logo';

  @override
  String get accountOrganizationLogoHint => 'An image up to 2 MB.';

  @override
  String get accountOrganizationLogoUpdated => 'Logo updated.';

  @override
  String get accountOrganizationLogoRemoved => 'Logo removed.';

  @override
  String get accountOrganizationProfileDocument => 'Company profile';

  @override
  String get accountOrganizationProfileDocumentNone =>
      'No company profile uploaded yet.';

  @override
  String get accountOrganizationProfileDocumentWeb =>
      'The company profile is uploaded from the BAFO web dashboard.';

  @override
  String get accountTeamTitle => 'Team';

  @override
  String get accountTeamSeats => 'Seats in use';

  @override
  String accountTeamSeatsFull(int used, int total) {
    return 'All seats of your plan are in use ($used/$total).';
  }

  @override
  String get accountTeamInvite => 'Invite a member';

  @override
  String get accountTeamEmptyTitle => 'No team members yet';

  @override
  String get accountTeamEmptyMessage =>
      'Invite your colleagues to manage competitions and offers with you.';

  @override
  String get accountTeamStatusInvited => 'Invitation pending';

  @override
  String get accountTeamStatusActive => 'Active';

  @override
  String get accountTeamStatusInactive => 'Deactivated';

  @override
  String get accountTeamCanAward => 'Can award';

  @override
  String get accountTeamCanPurchase => 'Can purchase';

  @override
  String get accountTeamCanAwardHint => 'Can award competitions.';

  @override
  String get accountTeamCanPurchaseHint =>
      'Can make purchases in the BAFO web dashboard.';

  @override
  String get accountTeamYou => 'You';

  @override
  String get accountTeamLocked => 'This member cannot be changed.';

  @override
  String accountTeamInvitedOn(String date) {
    return 'Invited on $date';
  }

  @override
  String accountTeamJoinedOn(String date) {
    return 'Joined on $date';
  }

  @override
  String get accountTeamMemberTitle => 'Team member';

  @override
  String get accountTeamRole => 'Role';

  @override
  String get accountTeamRoleAdminHint =>
      'Manages the organisation details, the team and every competition.';

  @override
  String get accountTeamRoleMemberHint =>
      'Creates competitions, manages their own, and submits offers.';

  @override
  String get accountTeamSendInvite => 'Send invitation';

  @override
  String accountTeamInviteSent(String email) {
    return 'Invitation sent to $email.';
  }

  @override
  String get accountTeamResend => 'Resend invitation';

  @override
  String get accountTeamResent => 'Invitation resent.';

  @override
  String get accountTeamDeactivate => 'Deactivate member';

  @override
  String get accountTeamDeactivateConfirmMessage =>
      'The member cannot sign in until reactivated.';

  @override
  String get accountTeamDeactivated => 'Member deactivated.';

  @override
  String get accountTeamReactivate => 'Reactivate member';

  @override
  String get accountTeamReactivated => 'Member reactivated.';

  @override
  String get accountTeamRemove => 'Remove member';

  @override
  String accountTeamRemoveConfirmTitle(String name) {
    return 'Remove $name from the team?';
  }

  @override
  String get accountTeamRemoveConfirmMessage =>
      'The member loses access to the organisation account. Their past work stays on record.';

  @override
  String get accountTeamRemoved => 'Member removed from the team.';

  @override
  String get accountTeamMemberNotFound => 'This member is not in your team.';

  @override
  String get accountInvoicesTitle => 'Invoices';

  @override
  String get accountInvoicesEmptyTitle => 'No invoices yet';

  @override
  String get accountInvoicesTypeTax => 'Tax invoice';

  @override
  String get accountInvoicesTypeCredit => 'Credit note';

  @override
  String get accountInvoicesStatusCleared => 'Cleared';

  @override
  String get accountInvoicesStatusReported => 'Reported';

  @override
  String get accountInvoicesStatusPending => 'Pending';

  @override
  String get accountInvoicesStatusRejected => 'Rejected';

  @override
  String get accountInvoicesStatusFailed => 'Failed';

  @override
  String accountInvoicesIssued(String date) {
    return 'Issued on $date';
  }

  @override
  String get accountSettingsTitle => 'Settings';

  @override
  String get accountSettingsAbout => 'About';

  @override
  String get accountSettingsVersion => 'App version';

  @override
  String get accountSettingsLegal => 'Legal';

  @override
  String get accountSettingsLicenses => 'Open-source licences';

  @override
  String get accountHelpTitle => 'Help and contact';

  @override
  String get accountHelpIntro =>
      'We are glad to help. Contact us directly or send a message.';

  @override
  String get accountHelpNoContacts =>
      'Contact details are not available right now. You can write to us with the form.';

  @override
  String get accountHelpFormTitle => 'Send us a message';

  @override
  String get accountHelpSubject => 'Subject';

  @override
  String get accountHelpMessage => 'Message';

  @override
  String get accountHelpSend => 'Send';

  @override
  String get accountHelpSentTitle => 'Message received';

  @override
  String get accountHelpSentMessage =>
      'The BAFO team will contact you by e-mail soon.';

  @override
  String get accountHelpSendAnother => 'Send another message';

  @override
  String get accountDeleteTitle => 'Delete account';

  @override
  String get accountDeleteScopeOrganization =>
      'The organisation account and all its members will be deleted after 14 days.';

  @override
  String get accountDeleteScopeUser =>
      'Only your personal account will be deleted, after 14 days. The organisation and its other members stay.';

  @override
  String get accountDeleteCancelWindow =>
      'You can cancel the request during this period.';

  @override
  String get accountDeleteKeptTitle => 'What is kept';

  @override
  String get accountDeleteKept =>
      'We keep legal and financial records: invoices, payments, competitions and the offer ledger.';

  @override
  String get accountDeleteReason => 'Reason for deleting';

  @override
  String get accountDeleteConfirmTitle => 'Delete the account?';

  @override
  String accountDeletePending(String date) {
    return 'The account will be deleted on $date.';
  }

  @override
  String get accountDeleteCancel => 'Cancel the deletion';

  @override
  String get accountDeleteCancelled => 'The deletion was cancelled.';

  @override
  String get accountDeleteRequested => 'Your deletion request is recorded.';

  @override
  String get accountDeleteBlockedTitle => 'The account cannot be deleted yet';

  @override
  String get accountDeleteBlockedMessage =>
      'First finish these open competitions and participations.';

  @override
  String get accountDeleteBlockerIssued => 'A competition you issue';

  @override
  String get accountDeleteBlockerParticipation => 'A participation';

  @override
  String get competitionsParticipatingFilterActive => 'Active';

  @override
  String get competitionsParticipatingFilterEnded => 'Ended';

  @override
  String get competitionsParticipatingFilterAll => 'All';

  @override
  String get competitionsParticipatingDirectionAll => 'All types';

  @override
  String get competitionsParticipatingSearchHint => 'Search competition titles';

  @override
  String get competitionsParticipatingNoResultsTitle => 'No results';

  @override
  String get competitionsParticipatingNoResultsMessage =>
      'Try other search words or filters.';

  @override
  String competitionsParticipatingNeedsAction(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count invitations await your response',
      one: '1 invitation awaits your response',
    );
    return '$_temp0';
  }

  @override
  String competitionsParticipatingMyOffer(String amount) {
    return 'Your offer: $amount';
  }

  @override
  String get competitionsParticipatingLoadMoreFailed => 'Could not load more.';

  @override
  String competitionsParticipatingCardSemantics(String title, String status) {
    return '$title, $status';
  }

  @override
  String get invitationsJoinAction => 'Join';

  @override
  String get invitationsDeclineAction => 'Decline';

  @override
  String get invitationsCardTitle => 'Invitation to take part';

  @override
  String invitationsInvitedBy(String issuer) {
    return '$issuer invited you to take part in this competition.';
  }

  @override
  String invitationsSentAt(String date) {
    return 'Invitation sent on $date';
  }

  @override
  String get invitationsOwnPlan => 'You join with your current plan.';

  @override
  String invitationsSponsoredOnly(String sponsor) {
    return '$sponsor covers your participation fees for this competition only.';
  }

  @override
  String get invitationsPlanRequiredBody =>
      'You need an active plan to join this competition.';

  @override
  String get invitationsPlanRequiredMore => 'Why?';

  @override
  String get invitationsUnavailableDeclined => 'You declined this invitation.';

  @override
  String get invitationsUnavailableExpired => 'This invitation has expired.';

  @override
  String get invitationsUnavailableRevoked =>
      'The issuer revoked this invitation.';

  @override
  String get invitationsUnavailableDeadline =>
      'The join deadline of this competition has passed.';

  @override
  String get invitationsUnavailableClosed =>
      'This competition no longer accepts new participants.';

  @override
  String get invitationsDocumentsTitle => 'Invitation documents';

  @override
  String get invitationsJoinTitle => 'Join the competition';

  @override
  String get invitationsJoinIntro =>
      'Review the competition rules before joining. Once you join, your organisation becomes a participant and can submit offers.';

  @override
  String get invitationsJoinAcceptTerms => 'I accept the competition terms';

  @override
  String get invitationsJoinTermsRequired =>
      'Accept the competition terms to continue.';

  @override
  String get invitationsJoinConfirm => 'Join now';

  @override
  String get invitationsJoinSucceeded => 'You joined the competition';

  @override
  String get invitationsDeclineTitle => 'Decline the invitation';

  @override
  String get invitationsDeclineMessage =>
      'You will not be able to join this competition after declining.';

  @override
  String get invitationsDeclineReasonLabel => 'Reason for declining';

  @override
  String get invitationsDeclineReasonHelper =>
      'Optional. The issuer can see it.';

  @override
  String get invitationsDeclineConfirm => 'Decline invitation';

  @override
  String get invitationsDeclineSucceeded =>
      'Your decline was sent to the issuer.';

  @override
  String get invitationsAccessInfoTitle => 'An active plan is required to join';

  @override
  String get invitationsAccessInfoPlanRequired =>
      'Your organisation needs an active plan to join.';

  @override
  String get invitationsAccessInfoUnavailableTitle =>
      'Joining is not available';

  @override
  String get invitationsAccessInfoDeclineHint =>
      'You can still decline the invitation if you do not want to take part.';

  @override
  String get competitionsParticipantLiveRoom => 'Live room';

  @override
  String get competitionsParticipantQa => 'Questions and answers';

  @override
  String get competitionsParticipantMyOffers => 'My offers';

  @override
  String get competitionsParticipantStandingTitle => 'Your standing';

  @override
  String get competitionsParticipantCurrentOffer => 'Your current offer';

  @override
  String competitionsParticipantAlias(int alias) {
    return 'Your number in this competition: Participant $alias';
  }

  @override
  String get competitionsParticipantVerified => 'Verified organisation';

  @override
  String get competitionsParticipantEvaluation =>
      'Offers are closed and the competition is under evaluation.';

  @override
  String get competitionsParticipantScheduled =>
      'Offers open at the start time. You can follow the questions until then.';

  @override
  String get competitionsParticipantResultTitle => 'Result';

  @override
  String get competitionsParticipantResultWon =>
      'This competition was awarded to you.';

  @override
  String competitionsParticipantResultWinningAmount(String amount) {
    return 'Winning offer: $amount';
  }

  @override
  String get competitionsParticipantResultNotSelected =>
      'The competition was awarded to another participant.';

  @override
  String get competitionsParticipantResultNotAwarded =>
      'The competition was closed without an award.';

  @override
  String get competitionsParticipantResultHidden =>
      'This competition does not publish its results to participants.';

  @override
  String get competitionsParticipantResultMessage => 'Message from the issuer';

  @override
  String get liveRoomTitle => 'Live room';

  @override
  String liveLeadingAmount(String amount) {
    return 'Leading offer: $amount';
  }

  @override
  String liveExtended(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other:
          'Closing was extended by $minutes minutes because of an offer in the final minutes.',
      one: 'Closing was extended by 1 minute because of an offer in the final minutes.',
    );
    return '$_temp0';
  }

  @override
  String liveExtendedSeconds(int seconds) {
    String _temp0 = intl.Intl.pluralLogic(
      seconds,
      locale: localeName,
      other:
          'Closing was extended by $seconds seconds because of an offer in the final minutes.',
      one: 'Closing was extended by 1 second because of an offer in the final minutes.',
    );
    return '$_temp0';
  }

  @override
  String get liveExtendedGeneric =>
      'Closing was extended because of an offer in the final minutes.';

  @override
  String get liveExtendedManual => 'The issuer extended the closing time.';

  @override
  String get liveHintServerTiming =>
      'Offers are timed on receipt by the BAFO server.';

  @override
  String get liveHintSlowConnection =>
      'Your connection is slow. Allow extra time.';

  @override
  String get liveConnectionConnecting => 'Connecting…';

  @override
  String get liveConnectionLive => 'Live';

  @override
  String get liveConnectionReconnecting => 'Reconnecting…';

  @override
  String liveConnectionPolling(int seconds) {
    String _temp0 = intl.Intl.pluralLogic(
      seconds,
      locale: localeName,
      other: 'Live updates are delayed. Refreshing every $seconds seconds.',
      one: 'Live updates are delayed. Refreshing every second.',
    );
    return '$_temp0';
  }

  @override
  String get liveConnectionOffline => 'You are offline.';

  @override
  String get liveConnectionSubmitPaused =>
      'Submitting is paused until the connection is back.';

  @override
  String get liveInitialPhaseNote =>
      'Before the final pricing window only your own offer is shown.';

  @override
  String get liveLadderTitle => 'Offer ranking';

  @override
  String liveLadderParticipant(int alias) {
    return 'Participant $alias';
  }

  @override
  String get liveLadderYou => 'You';

  @override
  String liveLadderRowSemantics(int rank, String name, String amount) {
    return 'Rank $rank, $name, $amount';
  }

  @override
  String get liveMyOfferTitle => 'Your current offer';

  @override
  String liveMyOfferReceivedAt(String time) {
    return 'Received at $time';
  }

  @override
  String liveMyOfferCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count offers submitted',
      one: '1 offer submitted',
    );
    return '$_temp0';
  }

  @override
  String get liveMyOffersLink => 'My offer history';

  @override
  String get liveComposerLabel => 'Your offer amount';

  @override
  String get liveComposerSubmitFirst => 'Submit offer';

  @override
  String get liveComposerSubmitImprove => 'Improve offer';

  @override
  String get liveComposerSubmitRevise => 'Revise offer';

  @override
  String get liveComposerSubmitBafo => 'Submit final offer';

  @override
  String liveComposerUseAmount(String amount) {
    return 'Use $amount';
  }

  @override
  String get liveComposerWholeRiyals => 'Whole riyals only';

  @override
  String get liveComposerDecimalsAllowed => 'Up to two decimals allowed';

  @override
  String liveComposerOpensIn(String time) {
    return 'Offers open in $time';
  }

  @override
  String liveComposerOpensAt(String time) {
    return 'Offers open at $time';
  }

  @override
  String get liveComposerClosed => 'Offers are closed.';

  @override
  String get liveComposerNotShortlisted =>
      'The issuer is running a best-and-final-offer round with a shortlist. Your last offer still stands.';

  @override
  String get liveSealedTitle => 'Sealed offers';

  @override
  String get liveSealedBody =>
      'Your offer is sealed. Offers are opened at closing.';

  @override
  String get liveSealedNoOffer =>
      'Submit your sealed offer before closing. You can revise it until then.';

  @override
  String get liveNotLiveYet => 'Offers have not opened yet.';

  @override
  String offersHintRequiredNext(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Your next offer must be $amount or lower.',
      'auction': 'Your next offer must be $amount or higher.',
      'other': 'Your next offer bound: $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersHintStartPrice(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Must not exceed the ceiling price of $amount.',
      'auction': 'Must not be below the opening price of $amount.',
      'other': 'Starting price: $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersErrorStartPrice(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Your offer cannot exceed the ceiling price of $amount.',
      'auction': 'Your offer cannot be below the opening price of $amount.',
      'other': 'The offer does not meet the starting price of $amount.',
    });
    return '$_temp0';
  }

  @override
  String offersErrorStepNotMet(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Your offer must be $amount or lower.',
      'auction': 'Your offer must be $amount or higher.',
      'other': 'The offer does not improve enough ($amount).',
    });
    return '$_temp0';
  }

  @override
  String offersErrorGranularity(String amount) {
    return 'The amount must be a multiple of $amount.';
  }

  @override
  String offersErrorAmountTooLarge(String amount) {
    return 'The amount must not exceed $amount.';
  }

  @override
  String offersErrorBafoReference(String direction, String amount) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender':
          'Your final offer cannot be higher than your last offer of $amount.',
      'auction':
          'Your final offer cannot be lower than your last offer of $amount.',
      'other':
          'Your final offer cannot be worse than your last offer of $amount.',
    });
    return '$_temp0';
  }

  @override
  String get offersUseThisAmount => 'Use this amount';

  @override
  String get offersConfirmTitle => 'Confirm your offer';

  @override
  String get offersConfirmBafoTitle => 'Confirm your final offer';

  @override
  String get offersConfirmExclVat => 'Excluding VAT';

  @override
  String get offersConfirmSealed =>
      'Your offer is sealed. Nobody else can see it, and you can revise it until closing.';

  @override
  String get offersConfirmBafo =>
      'This is your only final offer. It cannot be changed.';

  @override
  String get offersConfirmAction => 'Confirm and send';

  @override
  String get offersConfirmOutlierTitle => 'Check the amount';

  @override
  String offersConfirmOutlierLower(String pct) {
    return 'This offer is $pct lower than your current offer.';
  }

  @override
  String offersConfirmOutlierHigher(String pct) {
    return 'This offer is $pct higher than your current offer.';
  }

  @override
  String get offersConfirmOutlierSend => 'Send the offer at this amount';

  @override
  String get offersConfirmOutlierEdit => 'Edit the amount';

  @override
  String offersSubmitted(String time) {
    return 'Offer received at $time';
  }

  @override
  String get offersUnconfirmed => 'We could not confirm your offer. Try again.';

  @override
  String get offersKeyReused =>
      'The offer could not be confirmed. Check the amount and confirm it again.';

  @override
  String get offersRateLimited =>
      'Wait a moment before submitting another offer.';

  @override
  String get offersSealedReceived => 'Your sealed offer was received';

  @override
  String get offersReceiptSeq => 'Receipt number';

  @override
  String get offersReceiptTime => 'Received at';

  @override
  String get offersReceiptAmount => 'Amount';

  @override
  String get offersStageSealed => 'Sealed';

  @override
  String get offersStageInitial => 'Initial';

  @override
  String get offersStageLive => 'Live';

  @override
  String get offersStageBafo => 'Final';

  @override
  String get offersVoided => 'Voided';

  @override
  String get offersMyEmptyTitle => 'You have not submitted offers yet';

  @override
  String get offersMyEmptyMessage => 'Submit your offer from the live room.';

  @override
  String offersMySeq(int seq) {
    return 'Offer $seq';
  }

  @override
  String offersMyChangeDown(String pct) {
    return '$pct lower than your previous offer';
  }

  @override
  String offersMyChangeUp(String pct) {
    return '$pct higher than your previous offer';
  }

  @override
  String bafoInvite(String cutoff) {
    return 'You are invited to submit your best and final offer before $cutoff.';
  }

  @override
  String bafoReferenceAmount(String amount) {
    return 'Your last offer: $amount';
  }

  @override
  String bafoRule(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Your final offer cannot be higher than your last offer.',
      'auction': 'Your final offer cannot be lower than your last offer.',
      'other': 'Your final offer cannot be worse than your last offer.',
    });
    return '$_temp0';
  }

  @override
  String get bafoSubmitted => 'Your final offer was received.';

  @override
  String get qaTitle => 'Questions and answers';

  @override
  String get qaAskAction => 'Ask a question';

  @override
  String get qaAnnounceAction => 'Post an announcement to all participants';

  @override
  String get qaReplyAction => 'Reply';

  @override
  String get qaReplyTitle => 'Reply to the question';

  @override
  String get qaQuestionLabel => 'Your question';

  @override
  String get qaAnnouncementLabel => 'Announcement';

  @override
  String get qaReplyLabel => 'Your reply';

  @override
  String get qaQuestionHelper =>
      'Every participant sees the question without your organisation\'s name.';

  @override
  String get qaSend => 'Send';

  @override
  String get qaPosted => 'Your question was posted.';

  @override
  String get qaAnnouncementPosted => 'Your announcement was posted.';

  @override
  String get qaReplyPosted => 'Your reply was posted.';

  @override
  String get qaClosedTitle => 'Questions are closed';

  @override
  String get qaClosedBody =>
      'New questions cannot be posted in the current status of the competition.';

  @override
  String get qaEmptyTitle => 'No questions yet';

  @override
  String get qaEmptyMessage =>
      'Questions, replies and the issuer\'s announcements appear here.';

  @override
  String qaAuthorParticipant(int alias) {
    return 'Participant $alias';
  }

  @override
  String qaAuthorParticipantNamed(int alias, String organization) {
    return 'Participant $alias · $organization';
  }

  @override
  String get qaAuthorMe => 'You';

  @override
  String get qaAuthorUnknown => 'Participant';

  @override
  String get qaAnnouncement => 'Announcement';

  @override
  String qaNewMessages(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count new messages',
      one: '1 new message',
    );
    return '$_temp0';
  }

  @override
  String qaRepliesCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count replies',
      one: '1 reply',
    );
    return '$_temp0';
  }

  @override
  String get issuerWebOnly => 'Available in the web dashboard.';

  @override
  String get issuerWebOnlyActions =>
      'Extending the closing time, the BAFO round, awarding, revoking an award and closing without award are available in the web dashboard.';

  @override
  String get issuerPlanRequired =>
      'Your organisation needs an active plan to issue competitions.';

  @override
  String get issuerNoValue => '—';

  @override
  String get issuerYes => 'Yes';

  @override
  String get issuerNo => 'No';

  @override
  String get issuerDiscard => 'Discard';

  @override
  String issuerParticipantAlias(int alias) {
    return 'Participant $alias';
  }

  @override
  String issuerParticipantLabel(int alias, String name) {
    return 'Participant $alias · $name';
  }

  @override
  String get issuerListCreate => 'New competition';

  @override
  String get issuerListSearchHint => 'Search by title';

  @override
  String get issuerListSegmentActive => 'Active';

  @override
  String get issuerListSegmentDrafts => 'Drafts';

  @override
  String get issuerListSegmentEnded => 'Ended';

  @override
  String get issuerListEmptyActiveTitle => 'No active competitions';

  @override
  String get issuerListEmptyActiveMessage =>
      'Scheduled competitions, those open for offers and those in evaluation appear here.';

  @override
  String get issuerListEmptyDraftsTitle => 'No drafts';

  @override
  String get issuerListEmptyDraftsMessage =>
      'A new competition is saved as a draft until you publish it.';

  @override
  String get issuerListEmptyEndedTitle => 'No ended competitions';

  @override
  String get issuerListEmptyEndedMessage =>
      'Competitions that were awarded, closed without award or cancelled appear here.';

  @override
  String get issuerListNoResultsTitle => 'No matching competitions';

  @override
  String get issuerListNoResultsMessage => 'Try other search words.';

  @override
  String issuerListOpensAt(String time) {
    return 'Offers open on $time';
  }

  @override
  String issuerListUpdatedAt(String date) {
    return 'Last updated on $date';
  }

  @override
  String issuerListCounts(int invited, int joined, int withOffers) {
    return 'Invited $invited · Joined $joined · With offers $withOffers';
  }

  @override
  String get issuerCreateTitle => 'New competition';

  @override
  String get issuerCreateStepType => 'Type and rules level';

  @override
  String get issuerCreateStepBasics => 'Basic details';

  @override
  String get issuerCreateStepSchedule => 'Prices and schedule';

  @override
  String get issuerCreateStepReview => 'Review and save';

  @override
  String get issuerCreateDirectionTitle => 'Competition type';

  @override
  String issuerCreateIssuerRole(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'You are buying',
      'auction': 'You are selling',
      'other': 'Issuer',
    });
    return '$_temp0';
  }

  @override
  String get issuerCreateAuctionDisabled =>
      'Auctions are not enabled for your organisation. Contact BAFO.';

  @override
  String get issuerCreateFormatTitle => 'Offer format';

  @override
  String get issuerCreateFormatLiveHint =>
      'Live offers that can be improved until closing';

  @override
  String get issuerCreateFormatSealedHint =>
      'One sealed offer per participant, opened at closing';

  @override
  String get issuerCreatePresetTitle => 'Rules preset';

  @override
  String get issuerCreatePresetRequired => 'Choose a preset to continue.';

  @override
  String get issuerCreateNoPreset =>
      'There is no preset for this choice in the app. You can create this competition from the web dashboard.';

  @override
  String get issuerCreateAdvancedOnWeb =>
      'Advanced settings are available in the web dashboard.';

  @override
  String get issuerCreatePricesTitle => 'Prices';

  @override
  String get issuerCreateScheduleTitle => 'Schedule';

  @override
  String get issuerCreateDiscardTitle => 'Discard this competition?';

  @override
  String get issuerCreateDiscardMessage =>
      'What you entered will not be saved.';

  @override
  String get issuerCreateSaved =>
      'Draft saved. Invite participants and add documents, then publish it.';

  @override
  String issuerPresetChipStep(String step) {
    return 'Minimum step $step';
  }

  @override
  String get issuerPresetChipRankFull => 'Rank shown';

  @override
  String get issuerPresetChipLeadingFlag => 'Leading-offer flag';

  @override
  String get issuerPresetChipRankHidden => 'Rank hidden';

  @override
  String get issuerPresetChipPricesShown => 'Prices shown';

  @override
  String get issuerPresetChipAutoExtend => 'Auto-extend';

  @override
  String get issuerPresetChipBafo => 'BAFO round';

  @override
  String get issuerFieldTitle => 'Competition title';

  @override
  String get issuerFieldDescription => 'Description and scope';

  @override
  String get issuerFieldDescriptionHelper => 'Required before publishing.';

  @override
  String get issuerFieldCategoryOther => 'Specify the category';

  @override
  String get issuerFieldCategoryNoAuction =>
      'This category does not allow auctions.';

  @override
  String issuerFieldStartPrice(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Ceiling price',
      'auction': 'Opening price',
      'other': 'Start price',
    });
    return '$_temp0';
  }

  @override
  String issuerFieldStartPriceHelper(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Optional. No offer above it is accepted.',
      'auction': 'Required before publishing. No offer below it is accepted.',
      'other': 'Optional.',
    });
    return '$_temp0';
  }

  @override
  String issuerFieldReservePrice(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Target price',
      'auction': 'Reserve price',
      'other': 'Target or reserve price',
    });
    return '$_temp0';
  }

  @override
  String get issuerFieldReserveHelper =>
      'Optional. Hidden from participants; it only affects the award.';

  @override
  String issuerFieldReserveVersusStart(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'The target price cannot be above the ceiling price.',
      'auction': 'The reserve price cannot be below the opening price.',
      'other': 'The target or reserve price does not fit the start price.',
    });
    return '$_temp0';
  }

  @override
  String get issuerFieldOpensOnPublish => 'When published';

  @override
  String get issuerFieldOpensAtTime => 'At a set time';

  @override
  String get issuerFieldRiyadhTime => 'Times are in Riyadh time.';

  @override
  String get issuerFieldCloseBeforeOpen =>
      'The closing time must be after offers open.';

  @override
  String get issuerFieldOpensInPast => 'Choose a time that has not passed.';

  @override
  String issuerFieldDurationTooShort(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes minutes',
      one: '1 minute',
    );
    return 'Offers must stay open for at least $_temp0.';
  }

  @override
  String issuerFieldDurationTooLong(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days',
      one: '1 day',
    );
    return 'The duration cannot exceed $_temp0.';
  }

  @override
  String get issuerSchedulePreviewTitle => 'Expected timeline';

  @override
  String get issuerSchedulePreviewNote => 'Estimated; fixed when published';

  @override
  String get issuerReviewEdit => 'Edit';

  @override
  String get issuerReviewSave => 'Save draft';

  @override
  String get issuerReviewDraftNote =>
      'The competition is saved as a draft. You can then add documents, invite participants and publish it.';

  @override
  String get issuerReviewNoDescription =>
      'Not added yet (required before publishing)';

  @override
  String get issuerReviewDescriptionAdded => 'Added';

  @override
  String get issuerActionsMenu => 'Actions';

  @override
  String get issuerActionPublish => 'Publish';

  @override
  String get issuerActionEdit => 'Edit details';

  @override
  String get issuerActionInvite => 'Invite participants';

  @override
  String get issuerActionInviteMore => 'Invite more';

  @override
  String get issuerActionDocuments => 'Documents';

  @override
  String get issuerActionExtend => 'Extend closing';

  @override
  String get issuerActionStartBafo => 'Start a BAFO round';

  @override
  String get issuerActionAward => 'Award';

  @override
  String get issuerActionRevokeAward => 'Revoke the award';

  @override
  String get issuerActionCloseWithoutAward => 'Close without award';

  @override
  String get issuerActionCancel => 'Cancel competition';

  @override
  String get issuerActionDeleteDraft => 'Delete draft';

  @override
  String get issuerDetailCreatedViaApi => 'Created through the API';

  @override
  String get issuerDetailLeadingOffer => 'Leading offer';

  @override
  String get issuerDetailCounts => 'Participation summary';

  @override
  String get issuerDetailCreatedBy => 'Created by';

  @override
  String get issuerCountInvitations => 'Invited';

  @override
  String get issuerCountJoined => 'Joined';

  @override
  String get issuerCountDeclined => 'Declined';

  @override
  String get issuerCountWithOffers => 'With offers';

  @override
  String get issuerCountOffers => 'Offers';

  @override
  String get issuerCountComments => 'Questions';

  @override
  String get issuerNavLive => 'Live monitor';

  @override
  String get issuerNavOffers => 'Offers log';

  @override
  String get issuerNavParticipants => 'Participants and invitations';

  @override
  String get issuerNavQa => 'Q&A';

  @override
  String get issuerNavAward => 'Evaluation and award';

  @override
  String get issuerNavDocuments => 'Documents';

  @override
  String get issuerDocumentsManage => 'Manage';

  @override
  String issuerAwardSummary(String participant) {
    return 'Awarded to $participant';
  }

  @override
  String get issuerTimelinePublished => 'Published';

  @override
  String get issuerTimelineScheduledClose => 'Scheduled closing';

  @override
  String get issuerTimelineEffectiveClose => 'Current closing time';

  @override
  String get issuerTimelineHardStop => 'Latest possible close';

  @override
  String get issuerTimelineExtensions => 'Extensions';

  @override
  String get issuerTimelineClosed => 'Closed';

  @override
  String get issuerTimelineAwarded => 'Awarded';

  @override
  String get issuerTimelineNotAwarded => 'Closed without award';

  @override
  String get issuerTimelineCancelled => 'Cancelled';

  @override
  String get issuerChecklistTitle => 'Before publishing';

  @override
  String get issuerChecklistDescription => 'Add the description and scope';

  @override
  String get issuerChecklistSchedule => 'Set the closing time';

  @override
  String get issuerChecklistStartPrice => 'Set the opening price';

  @override
  String get issuerChecklistOtherText => 'Specify the category';

  @override
  String issuerChecklistInvitations(int min, int current) {
    String _temp0 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: '$min participants',
      one: '1 participant',
    );
    return 'Invite at least $_temp0 (invited so far: $current)';
  }

  @override
  String get issuerPublishTitle => 'Publish the competition';

  @override
  String get issuerPublishNote =>
      'Publishing sends the invitations and fixes the rules.';

  @override
  String get issuerPublishConfirm => 'Publish';

  @override
  String get issuerPublishDone => 'Competition published. Invitations sent.';

  @override
  String get issuerPublishFixFields =>
      'Complete the following, then try again:';

  @override
  String issuerPublishMissingInvitations(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more participants',
      one: '1 more participant',
    );
    return 'Invite at least $_temp0.';
  }

  @override
  String get issuerPublishCapacity =>
      'The number of simultaneous live events has reached its limit for this period. Choose another time.';

  @override
  String get issuerCancelTitle => 'Cancel the competition';

  @override
  String get issuerCancelDone => 'The competition was cancelled.';

  @override
  String get issuerDeleteTitle => 'Delete this draft?';

  @override
  String get issuerDeleteMessage =>
      'The draft and its invitations are deleted and cannot be restored.';

  @override
  String get issuerDeleteDone => 'The draft was deleted.';

  @override
  String get issuerSponsorshipTitle => 'Participation fees';

  @override
  String issuerSponsorshipMode(String mode) {
    String _temp0 = intl.Intl.selectLogic(mode, {
      'all': 'Fees covered for all invitees',
      'selected': 'Fees covered for selected invitees',
      'other': 'No fees covered',
    });
    return '$_temp0';
  }

  @override
  String get issuerSponsorshipCap => 'Maximum covered participants';

  @override
  String get issuerSponsorshipFunded => 'Funded passes';

  @override
  String get issuerSponsorshipJoined => 'Passes used';

  @override
  String get issuerSponsorshipReserved => 'Passes reserved';

  @override
  String get issuerSponsorshipFreeSlots => 'Passes available';

  @override
  String get issuerSponsorshipPending => 'Awaiting payment';

  @override
  String get issuerSponsorshipUnused => 'Unused passes';

  @override
  String get issuerSponsorshipOnWeb =>
      'Participation fees and their payment are managed from the BAFO web dashboard.';

  @override
  String get issuerEditTitle => 'Edit competition';

  @override
  String get issuerEditSaved => 'Changes saved.';

  @override
  String get issuerEditNotifyNote =>
      'Participants will be notified of the update.';

  @override
  String get issuerEditRulesFixed =>
      'Rules and prices are fixed after publishing.';

  @override
  String get issuerEditLiveScope =>
      'While offers are open, only the title and description can change.';

  @override
  String get issuerEditTypeOnWeb =>
      'The type, offer format and rules are changed from the web dashboard.';

  @override
  String get issuerEditDiscardTitle => 'Discard your changes?';

  @override
  String get issuerEditDiscardMessage => 'Your changes will not be saved.';

  @override
  String get issuerInviteTitle => 'Invite participants';

  @override
  String get issuerInviteTabSuggestions => 'Suggested';

  @override
  String get issuerInviteTabEmail => 'By e-mail';

  @override
  String get issuerInviteTabVendors => 'Vendor directory';

  @override
  String get issuerInviteSearchSuggestions => 'Search by organisation name';

  @override
  String get issuerInviteSearchVendors => 'Search the vendor directory';

  @override
  String get issuerInviteNoSuggestions => 'No matching suggestions.';

  @override
  String get issuerInviteNoVendors => 'No matching vendors.';

  @override
  String get issuerInviteVerified => 'Verified organisation';

  @override
  String get issuerInviteMatchCategory => 'Same category';

  @override
  String get issuerInviteMatchRegion => 'Same region';

  @override
  String get issuerInviteHasPlan => 'Has an active plan';

  @override
  String get issuerInviteVendorRegistered => 'Registered on BAFO';

  @override
  String get issuerInviteEmailsLabel => 'E-mail addresses';

  @override
  String get issuerInviteEmailsHelper =>
      'Separate addresses with commas, spaces or new lines.';

  @override
  String get issuerInviteEmailsAdd => 'Add';

  @override
  String issuerInviteEmailsAdded(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count addresses added.',
      one: '1 address added.',
    );
    return '$_temp0';
  }

  @override
  String issuerInviteEmailsInvalid(String emails) {
    return 'Invalid addresses: $emails';
  }

  @override
  String issuerInviteStaged(int count) {
    return 'Selected ($count)';
  }

  @override
  String get issuerInviteStagedEmpty =>
      'Choose from the suggestions or the vendor directory, or add e-mail addresses.';

  @override
  String issuerInviteRemove(String name) {
    return 'Remove $name';
  }

  @override
  String get issuerInviteCoverFees => 'Cover the participation fee';

  @override
  String issuerInviteFreeSlots(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count covered passes available.',
      one: '1 covered pass available.',
      zero: 'No covered passes available.',
    );
    return '$_temp0';
  }

  @override
  String get issuerInviteNothingSent =>
      'No invitation was sent. Fix the marked rows, then try again.';

  @override
  String get issuerInviteFeesOnWeb =>
      'The participation fees for these invitations are paid from the BAFO web dashboard. The invitations were not sent.';

  @override
  String get issuerInviteSendWithoutFees => 'Send without covering fees';

  @override
  String get issuerInviteChooseFirst => 'Choose invitees first';

  @override
  String issuerInviteSend(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Send $count invitations',
      one: 'Send 1 invitation',
    );
    return '$_temp0';
  }

  @override
  String issuerInviteAdd(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Add $count invitees',
      one: 'Add 1 invitee',
    );
    return '$_temp0';
  }

  @override
  String get issuerInviteDone => 'Invitations sent.';

  @override
  String get issuerInviteAddedToDraft =>
      'Invitees added to the draft. The invitations are sent when you publish.';

  @override
  String get issuerInviteRowDuplicate => 'Already invited to this competition.';

  @override
  String get issuerInviteRowOwnOrganization =>
      'You cannot invite your own organisation.';

  @override
  String get issuerInviteRowVendorBlocked =>
      'This vendor is blocked in your directory.';

  @override
  String get issuerInviteRowVendorNotFound => 'This vendor was not found.';

  @override
  String get issuerInviteDiscardTitle => 'Discard the selection?';

  @override
  String get issuerInviteDiscardMessage =>
      'The invitations were not sent, and your selection will not be kept.';

  @override
  String get issuerParticipantsTitle => 'Participants and invitations';

  @override
  String issuerParticipantsFilterAll(int count) {
    return 'All ($count)';
  }

  @override
  String issuerParticipantsFilterStatus(String status, int count) {
    return '$status ($count)';
  }

  @override
  String get issuerParticipantsFilterEmpty =>
      'No invitations with this status.';

  @override
  String get issuerParticipantsEmptyTitle => 'No invitations yet';

  @override
  String get issuerParticipantsEmptyMessage =>
      'Invite participants from the suggestions, by e-mail or from the vendor directory.';

  @override
  String issuerInvitationStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'draft': 'Draft',
      'sent': 'Sent',
      'viewed': 'Viewed',
      'joined': 'Joined',
      'declined': 'Declined',
      'revoked': 'Revoked',
      'expired': 'Expired',
      'other': 'Unknown',
    });
    return '$_temp0';
  }

  @override
  String issuerCoverage(String coverage) {
    String _temp0 = intl.Intl.selectLogic(coverage, {
      'sponsored': 'Covered by you',
      'own_plan': 'Own plan',
      'none': 'Not covered',
      'other': 'Unknown',
    });
    return '$_temp0';
  }

  @override
  String issuerPassStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'pending': 'Pass awaiting payment',
      'reserved': 'Pass reserved',
      'joined': 'Pass used',
      'released': 'Pass released',
      'unused': 'Pass unused',
      'void': 'Pass void',
      'other': 'Pass',
    });
    return '$_temp0';
  }

  @override
  String issuerInvitationSentAt(String time) {
    return 'Sent $time';
  }

  @override
  String issuerInvitationViewedAt(String time) {
    return 'Viewed $time';
  }

  @override
  String issuerInvitationJoinedAt(String time) {
    return 'Joined $time';
  }

  @override
  String issuerInvitationDeclinedAt(String time) {
    return 'Declined $time';
  }

  @override
  String issuerInvitationRevokedAt(String time) {
    return 'Revoked $time';
  }

  @override
  String issuerInvitationReason(String reason) {
    return 'Reason: $reason';
  }

  @override
  String get issuerInvitationResend => 'Resend';

  @override
  String get issuerInvitationRevoke => 'Revoke invitation';

  @override
  String get issuerInvitationRemove => 'Remove invitee';

  @override
  String get issuerInvitationResent => 'The invitation was sent again.';

  @override
  String get issuerInvitationRevoked => 'The invitation was revoked.';

  @override
  String get issuerInvitationRemoved =>
      'The invitee was removed from the draft.';

  @override
  String get issuerInvitationResendLimit =>
      'You have reached the daily resend limit for this invitation.';

  @override
  String get issuerRevokeTitle => 'Revoke this invitation?';

  @override
  String get issuerRevokeMessage =>
      'The invitee will not be able to join with this invitation.';

  @override
  String get issuerRemoveInviteeTitle => 'Remove this invitee?';

  @override
  String get issuerRemoveInviteeMessage =>
      'This invitee is removed from the draft.';

  @override
  String get issuerDocumentsTitle => 'Competition documents';

  @override
  String get issuerDocumentsUpload => 'Upload a document';

  @override
  String get issuerDocumentsLimits =>
      'PDF, Word, Excel, images or ZIP, up to 100 MB per file.';

  @override
  String get issuerDocumentsAddendumNote =>
      'Documents added after publishing are announced to participants as addenda.';

  @override
  String get issuerDocumentsDeleteClosed =>
      'Documents cannot be deleted once offers open.';

  @override
  String get issuerDocumentsWebOnly =>
      'External links and invitation documents are added from the web dashboard.';

  @override
  String get issuerDocumentsEmptyTitle => 'No documents';

  @override
  String get issuerDocumentsEmptyMessage =>
      'Upload the terms and specifications for participants to read after they join.';

  @override
  String get issuerDocumentsUploadFailed => 'The file could not be uploaded.';

  @override
  String issuerDocumentsUploading(String name) {
    return 'Uploading $name';
  }

  @override
  String get issuerDocumentDeleteTitle => 'Delete this document?';

  @override
  String issuerDocumentDeleteMessage(String name) {
    return '$name is removed from the competition.';
  }

  @override
  String get issuerDocumentDelete => 'Delete document';

  @override
  String get issuerLiveTitle => 'Live monitor';

  @override
  String get issuerLiveConnected => 'Live';

  @override
  String get issuerLiveReconnecting => 'Reconnecting…';

  @override
  String issuerLivePolling(int seconds) {
    return 'Live updates are delayed. Refreshing every $seconds seconds.';
  }

  @override
  String get issuerLiveDraftTitle => 'Not published yet';

  @override
  String get issuerLiveDraftMessage =>
      'Live monitoring starts once the competition is published.';

  @override
  String issuerLiveOnline(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count participants online now',
      one: '1 participant online now',
      zero: 'No participants online now',
    );
    return '$_temp0';
  }

  @override
  String issuerLiveExtended(String reason) {
    String _temp0 = intl.Intl.selectLogic(reason, {
      'auto': 'Closing was extended automatically after an offer in the last minutes.',
      'manual': 'The issuer extended the closing time.',
      'admin': 'BAFO extended the closing time.',
      'other': 'The closing time was extended.',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveBafoProgress(int submitted, int shortlist) {
    return '$submitted of $shortlist have submitted their final offer';
  }

  @override
  String get issuerLiveNoOffers => 'No offers yet';

  @override
  String get issuerLiveSealedLock => 'Offers are opened at closing';

  @override
  String issuerLiveReceivedAt(String time) {
    return 'Received at $time';
  }

  @override
  String issuerLiveReserveMet(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Target price met',
      'auction': 'Reserve price met',
      'other': 'Target or reserve price met',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveReserveNotMet(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Target price not met',
      'auction': 'Reserve price not met',
      'other': 'Target or reserve price not met',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveImprovement(String direction) {
    String _temp0 = intl.Intl.selectLogic(direction, {
      'tender': 'Savings against the ceiling price',
      'auction': 'Uplift against the opening price',
      'other': 'Improvement against the start price',
    });
    return '$_temp0';
  }

  @override
  String issuerLiveLeaderAnnouncement(String participant, String amount) {
    return 'The leading offer is now from $participant: $amount';
  }

  @override
  String get issuerLiveRankingTitle => 'Participant ranking';

  @override
  String get issuerLiveNoParticipants => 'No participants have joined yet.';

  @override
  String issuerLiveRank(int rank) {
    return 'Rank $rank';
  }

  @override
  String get issuerLiveSubmitted => 'Offer submitted';

  @override
  String get issuerLiveNotSubmitted => 'No offer yet';

  @override
  String issuerLiveFirstOffer(String amount) {
    return 'First offer: $amount';
  }

  @override
  String issuerLiveOffersCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count offers',
      one: '1 offer',
      zero: 'No offers',
    );
    return '$_temp0';
  }

  @override
  String issuerLiveLastOffer(String time) {
    return 'Last offer $time';
  }

  @override
  String get issuerLiveBafoShortlisted => 'Shortlisted';

  @override
  String get issuerLiveBafoSubmitted => 'Final offer submitted';

  @override
  String get issuerLiveExtendOnWeb =>
      'Extending the closing time is available in the web dashboard.';

  @override
  String get issuerOffersTitle => 'Offers log';

  @override
  String get issuerOffersEmptyTitle => 'No offers yet';

  @override
  String get issuerOffersEmptyMessage =>
      'Offers appear here as soon as they are received.';

  @override
  String issuerOfferSeq(int seq) {
    return 'Offer number $seq';
  }

  @override
  String get issuerOfferSealed => 'Sealed';

  @override
  String issuerOfferStage(String stage) {
    String _temp0 = intl.Intl.selectLogic(stage, {
      'sealed': 'Sealed',
      'initial': 'Initial',
      'live': 'Live',
      'bafo': 'Final',
      'other': '—',
    });
    return '$_temp0';
  }

  @override
  String issuerOfferChannel(String channel) {
    String _temp0 = intl.Intl.selectLogic(channel, {
      'web': 'Web',
      'ios': 'iOS',
      'android': 'Android',
      'api': 'API',
      'other': 'Other',
    });
    return '$_temp0';
  }

  @override
  String get issuerOfferVoided => 'Voided';

  @override
  String get issuerAwardTitle => 'Evaluation and award';

  @override
  String get issuerAwardOnWeb => 'Awarding is done from the web dashboard.';

  @override
  String get issuerAwardChangesOnWeb =>
      'Revoking the award is available in the web dashboard.';

  @override
  String get issuerAwardWinner => 'Award';

  @override
  String get issuerAwardRevokedTitle => 'Revoked award';

  @override
  String issuerAwardRevoked(String date, String reason) {
    return 'This award was revoked on $date. Reason: $reason';
  }

  @override
  String get issuerAwardCr => 'Commercial registration';

  @override
  String get issuerAwardVat => 'VAT number';

  @override
  String get issuerAwardLeading => 'Leading offer at award';

  @override
  String get issuerAwardRank => 'Rank at award';

  @override
  String get issuerAwardJustification => 'Award justification';

  @override
  String get issuerAwardMessage => 'Message to the winner';

  @override
  String get issuerAwardNotes => 'Internal notes';

  @override
  String get issuerAwardBy => 'Awarded by';

  @override
  String get issuerAwardAt => 'Awarded on';

  @override
  String get issuerAwardOfferAt => 'Offer received at';

  @override
  String get issuerAwardErpSync => 'ERP sync';

  @override
  String issuerAwardErpStatus(String status) {
    String _temp0 = intl.Intl.selectLogic(status, {
      'pending': 'Pending',
      'synced': 'Synced',
      'failed': 'Failed',
      'not_required': 'Not required',
      'other': '—',
    });
    return '$_temp0';
  }

  @override
  String get issuerAwardLedgerHash => 'Ledger hash';

  @override
  String get issuerAwardStandings => 'Final standings';

  @override
  String issuerAwardChange(String value) {
    return 'Improvement $value';
  }

  @override
  String get issuerReportTitle => 'Results report';

  @override
  String get issuerReportShareHint =>
      'The report opens in your file viewer, where you can share it.';

  @override
  String get issuerReportArabic => 'Arabic';

  @override
  String get issuerReportEnglish => 'English';

  @override
  String get issuerReportGenerating => 'Preparing the report…';

  @override
  String get issuerReportFailed =>
      'The report could not be prepared. Try again.';

  @override
  String get issuerReportTimeout =>
      'The report is still being prepared. Try again shortly.';

  @override
  String invitationsJoinBy(String date) {
    return 'Join by $date';
  }

  @override
  String get errorsFeatureDisabled =>
      'This feature is not available in this release.';

  @override
  String get errorsFeatureDisabledField =>
      'This option is not available in this release.';

  @override
  String get commonFeatureUnavailableTitle => 'Not available in this release';

  @override
  String get commonFeatureUnavailableHint =>
      'This feature will arrive in a later BAFO release.';

  @override
  String get accountInvoicesOnWeb =>
      'Invoices are available in the BAFO web dashboard.';

  @override
  String get issuerPresetTierTitle => 'Rules level';

  @override
  String get issuerPresetTierHint =>
      'Pick one level and BAFO explains its effect in one sentence. You can adjust the details later from the web dashboard.';

  @override
  String get issuerPresetTierRecommended => 'Recommended';

  @override
  String get issuerPresetTierOther => 'Other templates';

  @override
  String get issuerScheduleQuickTitle => 'Offer window';

  @override
  String get issuerScheduleQuickHour => '1 hour';

  @override
  String get issuerScheduleQuickHours3 => '3 hours';

  @override
  String get issuerScheduleQuickDay => '1 day';

  @override
  String get issuerScheduleQuickDays3 => '3 days';

  @override
  String get issuerScheduleQuickWeek => '1 week';

  @override
  String get issuerScheduleQuickCustom => 'Custom';

  @override
  String get issuerScheduleQuickFromPublish =>
      'The duration counts from the moment of publishing.';

  @override
  String issuerScheduleRelativeClose(String relative, String date) {
    return 'Closes $relative: $date';
  }

  @override
  String issuerScheduleInMinutes(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'in $count minutes',
      one: 'in 1 minute',
    );
    return '$_temp0';
  }

  @override
  String issuerScheduleInHours(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'in $count hours',
      one: 'in 1 hour',
    );
    return '$_temp0';
  }

  @override
  String issuerScheduleInDays(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'in $count days',
      one: 'in 1 day',
    );
    return '$_temp0';
  }

  @override
  String issuerFieldDurationTooShortDetail(num minutes, num min) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes minutes',
      one: '1 minute',
    );
    String _temp1 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: '$min minutes',
      one: '1 minute',
    );
    return 'The duration is only $_temp0; the minimum is $_temp1.';
  }

  @override
  String issuerFieldDurationTooLongDetail(num days, num max) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days',
      one: '1 day',
    );
    String _temp1 = intl.Intl.pluralLogic(
      max,
      locale: localeName,
      other: '$max days',
      one: '1 day',
    );
    return 'The duration is $_temp0; the maximum is $_temp1.';
  }

  @override
  String get issuerFieldTitleHelper =>
      'A clear name invitees understand. Example: Supplying laptops for the head office';

  @override
  String get issuerFieldDescriptionExample =>
      'Required before publishing. State the quantity, specifications, delivery location and payment terms.';

  @override
  String get commonAmountExample => 'Example: 125,000.00';

  @override
  String get issuerCreateDraftRestored =>
      'What you entered earlier for this competition was restored.';

  @override
  String get issuerCreateStartOver => 'Start over';

  @override
  String commonErrorSummaryTitle(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Please correct the following $count fields:',
      one: 'Please correct the following field:',
    );
    return '$_temp0';
  }

  @override
  String commonErrorSummaryOtherStep(int step) {
    return 'In step $step';
  }
}
