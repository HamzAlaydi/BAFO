import 'dart:io';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/models/models.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/award.dart';
import 'package:bafo/features/competitions/domain/comment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/live/domain/live_models.dart';
import 'package:bafo/features/notifications/domain/notification_models.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/fakes.dart';

/// Every model parses the payloads captured from the running API
/// (test/fixtures/api, demo seed, 2026-09-29), and the parsed values respect
/// the server projections.
void main() {
  group('identity', () {
    test('AuthTokenPayload = Me + token', () {
      final payload = fixturePayload('auth_login_issuer');
      expect(payload.token, 'test-token-redacted');
      expect(payload.me.user.email, 'issuer.owner@demo.bafo.test');
      expect(payload.me.membership.role, MembershipRole.owner);
      expect(payload.me.organization.features.auctionEnabled, isTrue);
      expect(payload.me.can(Permissions.competitionsAward), isTrue);
      expect(payload.me.canCreateCompetition, isTrue);
      expect(payload.toString(), isNot(contains('test-token')));
    });

    test('Me of a member, a trial and an organisation without a plan', () {
      final member = fixtureMe('me_member');
      expect(member.membership.role, MembershipRole.member);
      expect(member.can(Permissions.teamManage), isFalse);
      expect(member.can(Permissions.competitionsCreate), isTrue);

      final trial = fixtureMe('me_supplier_c');
      expect(trial.subscription?.source, SubscriptionSource.trial);
      expect(trial.entitlements.seatsTotal, 2);

      final none = fixtureMe('me_supplier_b');
      expect(none.subscription, isNull);
      expect(none.entitlements.canIssue, isFalse);
      expect(none.canCreateCompetition, isFalse);
    });

    test('Organization own view', () {
      final organization = Organization.fromJson(
        fixtureData('organization_issuer'),
      );
      expect(organization.crNumber, '1010000001');
      expect(organization.nationalAddress.shortAddress, isNotEmpty);
      expect(organization.region?.code, 'RIY');
      expect(organization.billingProfileComplete, isTrue);
    });

    test('a fresh registration: verified payload, billing profile gaps', () {
      final payload = fixturePayload('auth_otp_verify');
      expect(payload.me.subscription, isNull);
      expect(payload.me.organization.trialAvailable, isTrue);
      expect(payload.me.organization.billingProfileMissing, ['legal_name_ar']);
      final result = RegistrationResult.fromJson(fixtureData('auth_register'));
      expect(result.verificationRequired, isTrue);
      expect(result.otpExpiresAt, isNotNull);
    });

    test('team roster and the deletion request', () {
      final members = fixtureList(
        'team_members_issuer',
      ).map(TeamMember.fromJson).toList();
      expect(members, hasLength(3));
      expect(members.first.isOwner, isTrue);
      final meta = fixtureBody('team_members_issuer')['meta'] as Map;
      final seats = Seats.fromJson(meta['seats'] as Map<String, dynamic>);
      expect(seats.full, isTrue);
      expect(fixtureBody('account_deletion_none')['data'], isNull);
    });

    test('legal document', () {
      final document = LegalDocument.fromJson(fixtureData('legal_terms'));
      expect(document.code, 'terms');
      expect(document.version, '2026-10-01');
      expect(document.bodyMarkdown, contains('#'));
    });
  });

  group('platform and catalog', () {
    test('AppConfig', () {
      final config = AppConfig.fromJson(fixtureData('app_config'));
      expect(config.realtime.port, 8085);
      expect(config.realtime.key, isNotEmpty);
      expect(config.legalVersions['terms'], '2026-10-01');
      expect(config.vatRateBp, 1500);
      expect(config.requiresUpdate('android', '1.0.0'), isFalse);
      expect(config.requiresUpdate('android', '0.9.9'), isTrue);
    });

    test('Lookups: regions, categories, close reasons, presets', () {
      final lookups = Lookups.fromJson(fixtureData('lookups'));
      expect(lookups.regions, hasLength(13));
      expect(lookups.categories.where((c) => c.isOther), hasLength(1));
      expect(lookups.categories.where((c) => !c.auctionAllowed), isNotEmpty);
      expect(lookups.closeReasonsOf(CloseReasonKind.cancel), hasLength(4));
      expect(lookups.presets, hasLength(3));
      final preset = lookups.presets.first;
      expect(preset.rules.startPriceMinor, isNull);
      expect(preset.rules.autoExtend.enabled, isTrue);
    });
  });

  group('home', () {
    test('issuer stats and activities', () {
      final home = Home.fromJson(fixtureData('home_issuer'));
      expect(home.issuer.activeCompetitions, greaterThan(0));
      expect(home.subscription?.plan.code, 'pro');
      expect(home.activities, isNotEmpty);
      expect(home.activities.first.action, contains('.'));
    });

    test('alerts with PHP empty-array params', () {
      final home = Home.fromJson(fixtureData('home_supplier_b'));
      expect(home.subscription, isNull);
      expect(
        home.alerts.map((a) => a.code),
        containsAll([HomeAlertCode.planRequired, HomeAlertCode.trialAvailable]),
      );
      expect(home.alerts.first.params, isEmpty);
    });
  });

  group('competition lists', () {
    test('issuer variant', () {
      final page = Paged.fromResponse(
        _response('competitions_issuer'),
        CompetitionListItem.fromJson,
      );
      expect(page.items, hasLength(12));
      expect(page.meta.total, 12);
      expect(page.items.every((item) => item.counts != null), isTrue);
      expect(page.items.every((item) => item.access == null), isTrue);
    });

    test('participant variant: access, invitation, own offer only', () {
      final items = fixtureList(
        'competitions_participant_a',
      ).map(CompetitionListItem.fromJson).toList();
      expect(items.every((item) => item.issuer != null), isTrue);
      expect(items.every((item) => item.counts == null), isTrue);
      expect(items.every((item) => item.leadingAmountMinor == null), isTrue);
      final scheduled = items.firstWhere(
        (item) => item.status == CompetitionStatus.scheduled,
      );
      expect(scheduled.needsAction, isTrue);
      expect(scheduled.invitationStatus, InvitationStatus.viewed);
      final awarded = items.firstWhere(
        (item) => item.status == CompetitionStatus.awarded,
      );
      expect(awarded.resultOutcome, AwardOutcome.notSelected);
      expect(awarded.access?.state, AccessState.readOnly);
    });
  });

  group('competition projections', () {
    final issuerFixtures = Directory('test/fixtures/api')
        .listSync()
        .map((f) => f.uri.pathSegments.last.replaceAll('.json', ''))
        .where((name) => name.startsWith('competition_issuer_'))
        .toList();

    for (final name in issuerFixtures) {
      test('issuer: $name', () {
        final competition = Competition.fromJson(fixtureData(name));
        expect(competition.viewerRole, ViewerRole.issuer);
        expect(competition.counts, isNotNull);
        expect(competition.permissions.canJoin, isFalse);
        if (competition.status == CompetitionStatus.live) {
          expect(competition.issuerLive, isNotNull);
          expect(competition.phase, isNotNull);
        }
      });
    }

    test('issuer: award, cancellation, not awarded, BAFO, sponsorship', () {
      final awarded = Competition.fromJson(
        fixtureData('competition_issuer_awarded'),
      );
      expect(awarded.award?.organization.name, 'Supplier C');
      final cancelled = Competition.fromJson(
        fixtureData('competition_issuer_cancelled'),
      );
      expect(cancelled.cancellation?.reason.code, 'cancel_budget_withdrawn');
      final notAwarded = Competition.fromJson(
        fixtureData('competition_issuer_not_awarded'),
      );
      expect(notAwarded.notAwarded?.note, isNotEmpty);
      final bafo = Competition.fromJson(
        fixtureData('competition_issuer_bafo_round'),
      );
      expect(bafo.bafoRound?.shortlistCount, 2);
      final sponsored = Competition.fromJson(
        fixtureData('competition_issuer_sponsored_live'),
      );
      expect(sponsored.sponsorship?.mode, 'selected');
      final live = Competition.fromJson(
        fixtureData('competition_issuer_live_final_window'),
      );
      expect(live.rules.reservePriceMinor, 22000000);
      expect(live.issuerLive?.ranking, hasLength(3));
      expect(live.issuerLive?.metrics.improvementVsStartBps, 480);
    });

    test('participant: no reserve, no counts, own standing only', () {
      final competition = Competition.fromJson(
        fixtureData('competition_supplier_a_live_final_window'),
      );
      expect(competition.viewerRole, ViewerRole.participant);
      expect(competition.rules.reservePriceMinor, isNull);
      expect(competition.counts, isNull);
      expect(competition.leadingAmountMinor, isNull);
      expect(competition.award, isNull);
      expect(competition.participation?.aliasNo, isPositive);
      expect(competition.permissions.canSubmitOffer, isTrue);
      final live = competition.participantLive!;
      expect(live.isLeading, isFalse);
      expect(live.rank, isNull);
      expect(live.leadingAmountMinor, isNull);
      expect(live.ladder, isNull);
      expect(live.requiredNextAmountMinor, 23880000);
      expect(competition.liveJson?['v'], live.v);
      // Nothing about other participants reaches the participant.
      final raw = fixtureBody('competition_supplier_a_live_final_window')
          .toString();
      expect(raw, isNot(contains('reserve_price_minor')));
      expect(raw, isNot(contains('Supplier C')));
      expect(raw, isNot(contains('Buyer D')));
    });

    test('participant: sponsored coverage and results', () {
      final sponsored = Competition.fromJson(
        fixtureData('competition_supplier_b_sponsored_live'),
      );
      expect(sponsored.access?.feesCovered, isTrue);
      expect(sponsored.access?.sponsorName, 'Issuer Co');
      final awarded = Competition.fromJson(
        fixtureData('competition_supplier_a_awarded'),
      );
      expect(awarded.result?.outcome, AwardOutcome.notSelected);
      final bafo = Competition.fromJson(
        fixtureData('competition_supplier_a_bafo_round'),
      );
      expect(bafo.bafoRound?.shortlisted, isFalse);
      expect(bafo.participantLive?.acceptingOffers, isFalse);
    });

    test('invitee teaser: invitation, access, documents, no live block', () {
      final teaser = Competition.fromJson(
        fixtureData('competition_supplier_c_scheduled_invitee'),
      );
      expect(teaser.viewerRole, ViewerRole.invitee);
      expect(teaser.invitation?.status, InvitationStatus.viewed);
      expect(teaser.access?.state, AccessState.joinRequired);
      expect(teaser.permissions.canJoin, isTrue);
      expect(teaser.invitationDocuments.single.kind, AttachmentKind.invitationDocument);
      expect(teaser.description, isNull);
      expect(teaser.liveJson, isNull);
      expect(teaser.rules.reservePriceMinor, isNull);
    });
  });

  group('attachments and Q&A', () {
    test('files and links', () {
      final attachments = fixtureList(
        'attachments_issuer_live_initial',
      ).map(Attachment.fromJson).toList();
      final file = attachments.firstWhere((a) => !a.isLink);
      expect(file.file?.downloadPath, startsWith('/api/app/v1/files/'));
      final link = attachments.firstWhere((a) => a.isLink);
      expect(link.url, startsWith('https://'));
      expect(link.file, isNull);
    });

    test('comment authors follow the audience projection', () {
      final issuer = fixtureList(
        'comments_issuer_live_initial',
      ).map(Comment.fromJson).toList();
      expect(
        issuer.where((c) => c.author.kind == CommentAuthorKind.participant),
        everyElement(
          predicate<Comment>((c) => c.author.organizationName != null),
        ),
      );
      expect(issuer.last.replies.single.author.kind, CommentAuthorKind.issuer);

      final supplierA = fixtureList(
        'comments_supplier_a_live_initial',
      ).map(Comment.fromJson).toList();
      final own = supplierA.firstWhere(
        (c) => c.author.kind == CommentAuthorKind.me,
      );
      expect(own.author.organizationName, isNull);
      final other = supplierA.firstWhere(
        (c) => c.author.kind == CommentAuthorKind.participant,
      );
      // Other participants appear by alias only.
      expect(other.author.organizationName, isNull);
      expect(other.author.aliasNo, isNotNull);
    });
  });

  group('invitations and sponsorship (issuer)', () {
    test('invitation rows and counts', () {
      final rows = fixtureList(
        'invitations_issuer_live_initial',
      ).map(Invitation.fromJson).toList();
      expect(rows.first.status, InvitationStatus.joined);
      expect(rows.first.aliasNo, isNotNull);
      final sponsored = fixtureList(
        'invitations_issuer_sponsored',
      ).map(Invitation.fromJson).toList();
      expect(sponsored.first.passStatus, PassStatus.joined);
      expect(sponsored.first.coverage, Coverage.sponsored);
    });

    test('sponsorship, suggestions, vendors', () {
      final sponsorship = Sponsorship.fromJson(
        fixtureData('sponsorship_issuer_sponsored'),
      );
      expect(sponsorship.counts.joined, 1);
      expect(Sponsorship.fromJson(fixtureData('sponsorship_issuer_none')).isNone, isTrue);
      final suggestion = Suggestion.fromJson(
        fixtureList('suggestions_issuer_draft').first,
      );
      expect(suggestion.matchesCategory, isTrue);
      final vendor = Vendor.fromJson(fixtureList('vendors_issuer').first);
      expect(vendor.externalRefs.single.system, 'sap_s4');
    });
  });

  group('live snapshots and offers', () {
    test('issuer snapshots: live, sealed (hidden amounts), BAFO', () {
      final live = IssuerLiveSnapshot.fromJson(
        fixtureData('live_issuer_final_window'),
      );
      expect(live.leader?.organization.name, 'Supplier C');
      expect(live.ranking.first.isLeader, isTrue);
      final sealed = IssuerLiveSnapshot.fromJson(
        fixtureData('live_issuer_sealed'),
      );
      expect(sealed.leader, isNull);
      expect(sealed.reserveMet, isNull);
      expect(sealed.ranking.every((r) => r.currentAmountMinor == null), isTrue);
      expect(sealed.ranking.any((r) => r.submitted), isTrue);
      final bafo = IssuerLiveSnapshot.fromJson(fixtureData('live_issuer_bafo'));
      expect(bafo.bafo?.shortlistCount, 2);
    });

    test('participant snapshots follow the visibility rules', () {
      final auction = ParticipantLiveSnapshot.fromJson(
        fixtureData('live_supplier_a_auction'),
      );
      expect(auction.direction, Direction.auction);
      expect(auction.isLeading, isTrue);
      expect(auction.leadingAmountMinor, 5100000);
      expect(auction.minStep.minor, 50000);
      final sealed = ParticipantLiveSnapshot.fromJson(
        fixtureData('live_supplier_a_sealed'),
      );
      expect(sealed.myOffer?.stage, OfferStage.sealed);
      expect(sealed.isLeading, isNull);
      expect(sealed.requiredNextAmountMinor, isNull);
      final initial = ParticipantLiveSnapshot.fromJson(
        fixtureData('live_supplier_a_initial'),
      );
      expect(initial.myOffer, isNull);
      expect(initial.phase, CompetitionPhase.initial);
      final bafo = ParticipantLiveSnapshot.fromJson(
        fixtureData('live_supplier_c_bafo'),
      );
      expect(bafo.bafo?.shortlisted, isTrue);
      expect(bafo.bafo?.referenceAmountMinor, 24100000);
      expect(bafo.acceptingOffers, isTrue);
      final won = ParticipantLiveSnapshot.fromJson(
        fixtureData('live_supplier_c_awarded'),
      );
      expect(won.result?.outcome, AwardOutcome.won);
      expect(won.lastChange.kind, LastChangeKind.snapshot);
    });

    test('offer rows, log and my offers', () {
      final standings = fixtureList(
        'offers_issuer_final_window',
      ).map(ParticipantStandingRow.fromJson).toList();
      expect(standings.first.rank, 1);
      expect(standings.first.organization.crNumber, isNotNull);
      final sealed = fixtureList(
        'offers_issuer_sealed',
      ).map(ParticipantStandingRow.fromJson).toList();
      expect(sealed.every((row) => row.currentAmountMinor == null), isTrue);
      final log = fixtureList(
        'offers_log_issuer_final_window',
      ).map(OfferLogEntry.fromJson).toList();
      expect(log.map((e) => e.seq), [1, 2, 3]);
      final sealedLog = OfferLogEntry.fromJson(
        fixtureList('offers_log_issuer_sealed').first,
      );
      expect(sealedLog.amountMinor, isNull);
      final mine = MyOffer.fromJson(
        fixtureList('my_offers_supplier_a_final_window').first,
      );
      expect(mine.voided, isFalse);
    });

    test('awards: issuer detail and participant outcome', () {
      final award = Award.fromJson(fixtureData('award_issuer_awarded'));
      expect(award.isLeadingOffer, isTrue);
      expect(award.reserveMet, isTrue);
      expect(award.messageToWinner, isNotEmpty);
      final won = ParticipantAwardView.fromJson(
        fixtureData('award_supplier_c_awarded'),
      );
      expect(won.outcome, AwardOutcome.won);
      expect(won.messageToWinner, isNotEmpty);
      final lost = ParticipantAwardView.fromJson(
        fixtureData('award_supplier_a_awarded'),
      );
      expect(lost.outcome, AwardOutcome.notSelected);
      expect(lost.messageToWinner, isNull);
    });
  });

  group('billing and notifications', () {
    test('subscription overview without prices, invoices list', () {
      final issuer = SubscriptionOverview.fromJson(
        fixtureData('billing_subscription_issuer'),
      );
      expect(issuer.current?.source, SubscriptionSource.paid);
      expect(issuer.current?.interval, 'monthly');
      final none = SubscriptionOverview.fromJson(
        fixtureData('billing_subscription_supplier_b'),
      );
      expect(none.current, isNull);
      expect(none.trialAvailable, isTrue);
      final invoice = InvoiceSummary.fromJson(
        fixtureList('billing_invoices_issuer').first,
      );
      expect(invoice.number, startsWith('BAFO-INV-'));
    });

    test('notifications carry locale-free routes and no amounts', () {
      final page = Paged.fromResponse(
        _response('notifications_supplier_a'),
        AppNotification.fromJson,
      );
      expect(page.hasMore, isTrue);
      expect(
        page.items.map((n) => n.route),
        everyElement(startsWith('/competitions/')),
      );
      final meta = fixtureBody('notifications_supplier_a')['meta'] as Map;
      expect(meta['unread_count'], 32);
    });
  });

  group('errors', () {
    test('envelopes map to ApiException with details', () {
      final cooldown = ApiException.fromResponse(
        statusCode: 429,
        body: fixtureBody('error_otp_resend_cooldown'),
      );
      expect(cooldown.code, 'otp_resend_cooldown');
      expect(cooldown.details['retry_after_seconds'], isA<int>());
      final validation = ApiException.fromResponse(
        statusCode: 422,
        body: fixtureBody('error_register_validation'),
      );
      expect(validation.fieldError('organization.cr_number'), isNotNull);
      expect(validation.fieldErrors['password'], hasLength(5));
      final blocked = ApiException.fromResponse(
        statusCode: 403,
        body: fixtureBody('error_not_a_participant_live'),
      );
      expect(blocked.code, 'not_a_participant');
    });

    test('deletion blockers from details', () {
      expect(
        DeletionBlocker.fromDetails({
          'blockers': [
            {'type': 'participation', 'competition_id': '01j', 'title': 'T'},
          ],
        }).single.type,
        'participation',
      );
    });
  });

  test('unknown enum values never crash', () {
    expect(CompetitionStatus.parse('frozen'), CompetitionStatus.unknown);
    expect(Direction.parse(null), Direction.unknown);
    expect(CompetitionPhase.parse(null), isNull);
    expect(LastChangeKind.parse('teleport'), LastChangeKind.unknown);
  });
}

/// A fixture as an `ApiResponse` (data + meta).
ApiResponse _response(String name) => ApiResponse.fromBody(fixtureBody(name));
