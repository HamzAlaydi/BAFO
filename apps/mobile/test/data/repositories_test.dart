import 'dart:convert';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/idempotency.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/models.dart';
import 'package:bafo/core/time/server_clock.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:bafo/features/notifications/data/notifications_repository.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:flutter_test/flutter_test.dart';

import '../helpers/fakes.dart';

void main() {
  late StubAdapter http;
  late ApiClient api;

  ApiClient client(StubAdapter adapter) => ApiClient.create(
    env: testEnv(),
    tokens: InMemoryTokenStore('t'),
    clock: ServerClock(),
    languageCode: () => 'ar',
    onUnauthorized: () {},
    httpClientAdapter: adapter,
  );

  void serve(Map<String, String> routes) {
    http = fixtureAdapter(routes);
    api = client(http);
  }

  Map<String, dynamic> bodyOf(RecordedRequest request) =>
      jsonDecode(request.body!) as Map<String, dynamic>;

  group('auth', () {
    test('login sends the device name and returns Me with the token', () async {
      serve({'POST /auth/login': 'auth_login_supplier_a'});
      final repo = ApiAuthRepository(api, deviceName: 'Pixel 8 · android');
      final payload = await repo.login(
        email: ' supplier-a.owner@demo.bafo.test ',
        password: 'pw',
      );
      expect(payload.me.organization.name, 'Supplier A');
      expect(bodyOf(http.requests.single), {
        'email': 'supplier-a.owner@demo.bafo.test',
        'password': 'pw',
        'device_name': 'Pixel 8 · android',
      });
    });

    test('register posts the nested organisation and an empty honeypot', () async {
      serve({'POST /auth/register': 'auth_register'});
      final repo = ApiAuthRepository(api, deviceName: 'x');
      final result = await repo.register(
        const RegistrationRequest(
          name: ' أحمد ',
          email: 'a@b.sa',
          phone: '+966512345678',
          password: 'Abcdef1!',
          passwordConfirmation: 'Abcdef1!',
          locale: 'ar',
          acceptTerms: true,
          acceptPrivacy: true,
          organization: OrganizationRegistration(
            name: 'شركة',
            crNumber: '1234567890',
            regionId: 'r1',
            city: 'الرياض',
            vatNumber: '300000000000003',
            website: '  ',
            nationalAddress: NationalAddress(buildingNumber: '1234', street: ''),
            categoryIds: ['c1'],
          ),
        ),
      );
      expect(result.email, 'mobile.capture.1790704523@demo.bafo.test');
      final body = bodyOf(http.requests.single);
      expect(body['name'], 'أحمد');
      expect(body['website_url'], '');
      final organization = body['organization'] as Map<String, dynamic>;
      expect(organization['cr_number'], '1234567890');
      // Not VAT registered: no VAT number is sent; blanks become null.
      expect(organization['vat_number'], isNull);
      expect(organization['website'], isNull);
      expect(
        (organization['national_address'] as Map)['building_number'],
        '1234',
      );
      expect((organization['national_address'] as Map)['street'], isNull);
    });

    test('OTP verify, check, send and reset use their purposes', () async {
      serve({
        'POST /auth/otp/verify': 'auth_otp_verify',
        'POST /auth/otp/send': 'otp_send_unknown',
        'POST /auth/otp/check': 'error_otp_check_invalid',
        'POST /auth/password/forgot': 'forgot_password',
      });
      final repo = ApiAuthRepository(api, deviceName: 'd');
      final payload = await repo.verifyEmail(email: 'a@b.sa', code: '123456');
      expect(payload.me.organization.trialAvailable, isTrue);
      final sent = await repo.sendOtp(
        email: 'a@b.sa',
        purpose: OtpPurpose.passwordReset,
      );
      expect(sent.expiresAt, isNull);
      await expectLater(
        repo.checkResetCode(email: 'a@b.sa', code: '000000'),
        throwsA(isA<ApiException>().having((e) => e.code, 'code', 'otp_invalid')),
      );
      await repo.forgotPassword('a@b.sa');
      final bodies = http.requests.map(bodyOf).toList();
      expect(bodies[0]['purpose'], 'email_verification');
      expect(bodies[0]['device_name'], 'd');
      expect(bodies[1]['purpose'], 'password_reset');
      expect(bodies[2]['purpose'], 'password_reset');
    });

    test('legal documents by code', () async {
      serve({'GET /legal/terms': 'legal_terms'});
      final document = await ApiLegalRepository(api).document(LegalCode.terms);
      expect(document.title, 'الشروط والأحكام');
    });
  });

  group('lookups', () {
    test('cached per language and revalidated with the ETag', () async {
      var language = 'ar';
      var calls = 0;
      http = StubAdapter((options) {
        calls++;
        if (options.headers['If-None-Match'] == '"e-ar"') return (304, null);
        final envelope = fixtureEnvelope('lookups');
        return (200, envelope['body']);
      })..responseHeaders = {'etag': ['"e-ar"']};
      api = ApiClient.create(
        env: testEnv(),
        tokens: InMemoryTokenStore(),
        clock: ServerClock(),
        languageCode: () => language,
        onUnauthorized: () {},
        httpClientAdapter: http,
      );
      final repo = ApiLookupsRepository(api, languageCode: () => language);

      final first = await repo.lookups();
      final cached = await repo.lookups();
      expect(identical(first, cached), isTrue);
      expect(calls, 1);

      final revalidated = await repo.lookups(refresh: true);
      expect(http.requests.last.options.headers['If-None-Match'], '"e-ar"');
      expect(identical(first, revalidated), isTrue);

      language = 'en';
      await repo.lookups();
      expect(calls, 3);
      expect(http.requests.last.options.headers['Accept-Language'], 'en');
      expect(
        await repo.closeReasons(CloseReasonKind.cancel),
        hasLength(4),
      );
    });
  });

  group('competitions', () {
    test('list sends role, group, search, sort and paging', () async {
      serve({'GET /competitions': 'competitions_participant_a'});
      final page = await ApiCompetitionsRepository(api).list(
        role: CompetitionListRole.participant,
        group: CompetitionStatusGroup.active,
        direction: Direction.tender,
        query: '  توريد ',
        page: 2,
      );
      expect(page.items, isNotEmpty);
      expect(http.requests.single.options.queryParameters, {
        'role': 'participant',
        'status_group': 'active',
        'direction': 'tender',
        'q': 'توريد',
        'sort': '-updated_at',
        'page': 2,
        'per_page': 20,
      });
    });

    test('show, award views and a 404 that reveals nothing', () async {
      final id = fixtureData('competition_issuer_awarded')['id'];
      serve({
        'GET /competitions/$id': 'competition_issuer_awarded',
        'GET /competitions/$id/award': 'award_issuer_awarded',
      });
      final repo = ApiCompetitionsRepository(api);
      expect((await repo.show('$id')).award, isNotNull);
      expect((await repo.issuerAward('$id'))?.amountMinor, 21800000);
      await expectLater(
        repo.show('missing'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 404)),
      );
    });

    test('an award-less competition reads as null', () async {
      serve({'GET /competitions/x/award': 'award_issuer_closed'});
      expect(await ApiCompetitionsRepository(api).issuerAward('x'), isNull);
    });

    test('cancel posts the reason and the note', () async {
      serve({'POST /competitions/x/cancel': 'competition_issuer_cancelled'});
      final competition = await ApiCompetitionsRepository(
        api,
      ).cancel('x', closeReasonId: 'r', note: 'n');
      expect(competition.status, CompetitionStatus.cancelled);
      expect(bodyOf(http.requests.single), {'close_reason_id': 'r', 'note': 'n'});
    });
  });

  group('invitations', () {
    test('list parses counts; invite posts rows', () async {
      serve({
        'GET /competitions/x/invitations': 'invitations_issuer_live_initial',
        'POST /competitions/x/invitations': 'invitations_issuer_sponsored',
      });
      final repo = ApiInvitationsRepository(api);
      final list = await repo.list(
        'x',
        statuses: {InvitationStatus.sent, InvitationStatus.joined},
      );
      expect(list.countOf(InvitationStatus.joined), 2);
      expect(http.requests.first.options.queryParameters['status'], 'sent,joined');
      await repo.invite('x', const [
        InvitationInput(email: ' a@b.sa ', sponsored: true),
        InvitationInput(organizationId: 'o1'),
      ]);
      expect(bodyOf(http.requests.last)['invitations'], [
        {'email': 'a@b.sa', 'sponsored': true},
        {'organization_id': 'o1', 'sponsored': false},
      ]);
    });

    test('removing a draft (204) returns null', () async {
      http = StubAdapter((_) => (204, null));
      api = client(http);
      expect(await ApiInvitationsRepository(api).remove('x', 'i'), isNull);
    });
  });

  group('live', () {
    test('an offer carries the Idempotency-Key and reads a replay', () async {
      http = StubAdapter(
        (_) => (
          200,
          {
            'data': {
              'offer': {
                'id': 'o',
                'seq': 9,
                'amount_minor': 23800000,
                'stage': 'live',
                'accepted_at': '2026-09-29T17:47:12.914Z',
              },
              'live': fixtureData('live_supplier_a_final_window'),
            },
            'meta': {'server_time': '2026-09-29T17:47:13.000Z'},
          },
        ),
      )..responseHeaders = {'idempotent-replayed': ['true']};
      api = client(http);
      final key = IdempotencyKey.generate();
      final submission = await ApiLiveRepository(
        api,
      ).submitOffer('x', amountMinor: 23800000, idempotencyKey: key);
      expect(IdempotencyKey.isValid(key), isTrue);
      expect(http.requests.single.options.headers['Idempotency-Key'], key);
      expect(bodyOf(http.requests.single), {
        'amount_minor': 23800000,
        'confirm_outlier': false,
      });
      expect(submission.replayed, isTrue);
      expect(submission.live.v, submission.liveJson['v']);
    });

    test('offer log pages by sequence', () async {
      serve({'GET /competitions/x/offers/log': 'offers_log_issuer_final_window'});
      final page = await ApiLiveRepository(api).offersLog('x', afterSeq: 0);
      expect(page.lastSeq, 3);
      expect(page.hasMore, isFalse);
      expect(http.requests.single.options.queryParameters, {
        'after_seq': 0,
        'limit': 200,
      });
    });

    test('snapshots come with their raw JSON for the v guard', () async {
      serve({'GET /competitions/x/live': 'live_issuer_final_window'});
      final (snapshot, raw) = await ApiLiveRepository(api).issuerSnapshot('x');
      expect(raw['v'], snapshot.v);
    });
  });

  group('the rest of the modules', () {
    test('me, organization, team, home, billing, notifications', () async {
      serve({
        'GET /me': 'me_issuer',
        'PATCH /me': 'me_issuer_en',
        'GET /organization': 'organization_issuer',
        'GET /team/members': 'team_members_issuer',
        'GET /home': 'home_issuer',
        'GET /billing/subscription': 'billing_subscription_issuer',
        'GET /billing/invoices': 'billing_invoices_issuer',
        'GET /notifications': 'notifications_supplier_a',
        'GET /notifications/unread-count': 'notifications_unread_count',
        'GET /competitions/x/attachments': 'attachments_issuer_live_initial',
        'GET /competitions/x/comments': 'comments_issuer_live_initial',
        'GET /vendors': 'vendors_issuer',
        'GET /account/deletion': 'account_deletion_none',
      });
      final account = ApiAccountRepository(api);
      expect((await account.me()).user.email, 'issuer.owner@demo.bafo.test');
      await account.updateMe(locale: 'en');
      expect(bodyOf(http.requests.last), {'locale': 'en'});
      expect(
        (await ApiOrganizationRepository(api).organization()).crNumber,
        '1010000001',
      );
      expect((await ApiTeamRepository(api).members()).seats.total, 3);
      expect((await ApiHomeRepository(api).home()).issuer.liveNow, 5);
      expect(
        (await ApiBillingRepository(api).subscription()).seatsTotal,
        3,
      );
      expect(
        (await ApiBillingRepository(api).invoices()).items.single.number,
        'BAFO-INV-2026-000001',
      );
      final notifications = ApiNotificationsRepository(api);
      final page = await notifications.list(unreadOnly: true);
      expect(page.unreadCount, 32);
      expect(http.requests.last.options.queryParameters['unread'], 1);
      expect(await notifications.unreadCount(), 32);
      expect(await ApiAttachmentsRepository(api).list('x'), hasLength(2));
      expect((await ApiCommentsRepository(api).list('x')).items, hasLength(2));
      expect((await ApiVendorsRepository(api).search(query: 'a')).items, hasLength(4));
      expect(await ApiAccountDeletionRepository(api).current(), isNull);
    });
  });
}
