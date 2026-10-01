import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/account/data/contact_repository.dart';
import 'package:bafo/features/account/domain/contact_message.dart';
import 'package:bafo/features/account/presentation/action_outcome.dart';
import 'package:bafo/features/account/presentation/deletion/account_deletion_cubit.dart';
import 'package:bafo/features/account/presentation/help/contact_cubit.dart';
import 'package:bafo/features/account/presentation/invoices/invoices_cubit.dart';
import 'package:bafo/features/account/presentation/organization/organization_cubit.dart';
import 'package:bafo/features/account/presentation/password/change_password_cubit.dart';
import 'package:bafo/features/account/presentation/profile/profile_cubit.dart';
import 'package:bafo/features/account/presentation/team/team_cubit.dart';
import 'package:bafo/features/account/presentation/team/team_member_form_cubit.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:bafo/features/team/data/team_repository.dart';
import 'package:bafo/features/team/domain/team_models.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';
import 'support.dart';

class _MockAccount extends Mock implements AccountRepository {}

class _MockOrganizations extends Mock implements OrganizationRepository {}

class _MockLookups extends Mock implements LookupsRepository {}

class _MockTeam extends Mock implements TeamRepository {}

class _MockBilling extends Mock implements BillingRepository {}

class _MockContact extends Mock implements ContactRepository {}

class _MockDeletion extends Mock implements AccountDeletionRepository {}

const _offline = ApiException(code: ApiErrorCode.network);

ApiException _validation(Map<String, List<String>> errors) => ApiException(
  code: 'validation_failed',
  statusCode: 422,
  fieldErrors: errors,
);

void main() {
  setUpAll(() {
    registerFallbackValue(
      const OrganizationUpdate(name: '', regionId: '', city: ''),
    );
    registerFallbackValue(
      const TeamMemberInvite(name: '', email: '', role: MembershipRole.member),
    );
    registerFallbackValue(
      const ContactMessage(name: '', email: '', subject: '', message: ''),
    );
  });

  final issuerMe = fixtureMe();
  final memberMe = fixtureMe('me_member');

  group('ProfileCubit', () {
    late _MockAccount account;
    late SessionCubit session;

    setUp(() async {
      account = _MockAccount();
      session = await signedInSession(issuerMe);
    });

    tearDown(() => session.close());

    blocTest<ProfileCubit, ProfileState>(
      'saves name and phone, then updates the session',
      setUp: () =>
          when(() => account.updateMe(name: 'Sara', phone: '+966500000011'))
              .thenAnswer((_) async => memberMe),
      build: () => ProfileCubit(account: account, session: session),
      act: (cubit) => cubit.save(name: ' Sara ', phone: '+966500000011'),
      expect: () => const [
        ProfileState(busy: ProfileBusy.saving),
        ProfileState(outcome: ActionOutcome(ProfileResult.saved, 1)),
      ],
      verify: (_) => expect(session.state.me, memberMe),
    );

    blocTest<ProfileCubit, ProfileState>(
      'binds server field errors, and forgets them on edit',
      setUp: () =>
          when(
            () => account.updateMe(
              name: any(named: 'name'),
              phone: any(named: 'phone'),
            ),
          ).thenThrow(
            _validation({
              'phone': ['bad phone'],
            }),
          ),
      build: () => ProfileCubit(account: account, session: session),
      act: (cubit) async {
        await cubit.save(name: 'Sara', phone: '+9661');
        cubit.fieldEdited('phone');
      },
      expect: () => const [
        ProfileState(busy: ProfileBusy.saving),
        ProfileState(
          fieldErrors: {
            'phone': ['bad phone'],
          },
        ),
        ProfileState(),
      ],
    );

    blocTest<ProfileCubit, ProfileState>(
      'uploads and removes the avatar through the API',
      setUp: () {
        when(() => account.uploadAvatar('/tmp/a.png'))
            .thenAnswer((_) async => issuerMe);
        when(account.deleteAvatar).thenAnswer((_) async => issuerMe);
      },
      build: () => ProfileCubit(account: account, session: session),
      act: (cubit) async {
        await cubit.uploadAvatar('/tmp/a.png');
        await cubit.removeAvatar();
      },
      expect: () => const [
        ProfileState(busy: ProfileBusy.uploadingAvatar),
        ProfileState(outcome: ActionOutcome(ProfileResult.avatarUpdated, 1)),
        ProfileState(busy: ProfileBusy.removingAvatar),
        ProfileState(outcome: ActionOutcome(ProfileResult.avatarRemoved, 2)),
      ],
    );

    blocTest<ProfileCubit, ProfileState>(
      'a failure that is not a field error is kept for a toast',
      setUp: () => when(
        () => account.uploadAvatar(any()),
      ).thenThrow(const ApiException(code: 'file_too_large', statusCode: 422)),
      build: () => ProfileCubit(account: account, session: session),
      act: (cubit) => cubit.uploadAvatar('/tmp/big.png'),
      expect: () => const [
        ProfileState(busy: ProfileBusy.uploadingAvatar),
        ProfileState(
          error: ApiException(code: 'file_too_large', statusCode: 422),
        ),
      ],
    );
  });

  group('ChangePasswordCubit', () {
    late _MockAccount account;
    const wrong = ApiException(code: 'password_incorrect', statusCode: 422);

    setUp(() => account = _MockAccount());

    blocTest<ChangePasswordCubit, ChangePasswordState>(
      'PUT /me/password, then done',
      setUp: () => when(
        () => account.changePassword(
          currentPassword: 'Old-pass1',
          password: 'New-pass1!',
          passwordConfirmation: 'New-pass1!',
        ),
      ).thenAnswer((_) async {}),
      build: () => ChangePasswordCubit(account),
      act: (cubit) => cubit.submit(
        currentPassword: 'Old-pass1',
        password: 'New-pass1!',
        passwordConfirmation: 'New-pass1!',
      ),
      expect: () => const [ChangePasswordSubmitting(), ChangePasswordDone()],
    );

    blocTest<ChangePasswordCubit, ChangePasswordState>(
      'password_incorrect belongs to the current-password field',
      setUp: () => when(
        () => account.changePassword(
          currentPassword: any(named: 'currentPassword'),
          password: any(named: 'password'),
          passwordConfirmation: any(named: 'passwordConfirmation'),
        ),
      ).thenThrow(wrong),
      build: () => ChangePasswordCubit(account),
      act: (cubit) async {
        await cubit.submit(
          currentPassword: 'x',
          password: 'New-pass1!',
          passwordConfirmation: 'New-pass1!',
        );
        cubit.edited();
      },
      expect: () => [
        const ChangePasswordSubmitting(),
        isA<ChangePasswordFailure>()
            .having((s) => s.currentPasswordWrong, 'current wrong', isTrue)
            .having((s) => s.isFormLevel, 'form level', isFalse),
        const ChangePasswordInitial(),
      ],
    );
  });

  group('OrganizationCubit', () {
    late _MockOrganizations organizations;
    late _MockLookups lookups;
    late SessionCubit session;
    final organization = Organization.fromJson(
      fixtureData('organization_issuer'),
    );
    final lookupData = Lookups.fromJson(fixtureData('lookups'));

    setUp(() async {
      organizations = _MockOrganizations();
      lookups = _MockLookups();
      session = await signedInSession(issuerMe);
    });

    tearDown(() => session.close());

    OrganizationCubit build() => OrganizationCubit(
      organizations: organizations,
      lookups: lookups,
      session: session,
    );

    blocTest<OrganizationCubit, OrganizationState>(
      'loads the organisation, then the lookups for the form',
      setUp: () {
        when(organizations.organization).thenAnswer((_) async => organization);
        when(() => lookups.lookups()).thenAnswer((_) async => lookupData);
      },
      build: build,
      act: (cubit) => cubit.load(),
      expect: () => [
        OrganizationLoaded(organization),
        OrganizationLoaded(organization, lookups: lookupData),
      ],
    );

    blocTest<OrganizationCubit, OrganizationState>(
      'a failed load is an error state; failed lookups do not hide the page',
      setUp: () {
        var calls = 0;
        when(organizations.organization).thenAnswer((_) async {
          if (calls++ == 0) throw _offline;
          return organization;
        });
        when(() => lookups.lookups()).thenThrow(_offline);
      },
      build: build,
      act: (cubit) async {
        await cubit.load();
        await cubit.load();
      },
      expect: () => [
        const OrganizationFailure(_offline),
        const OrganizationLoading(),
        OrganizationLoaded(organization),
        OrganizationLoaded(organization, lookupsError: _offline),
      ],
    );

    blocTest<OrganizationCubit, OrganizationState>(
      'saving waits for PATCH /organization and re-reads Me',
      setUp: () {
        when(() => organizations.update(any()))
            .thenAnswer((_) async => organization);
      },
      build: build,
      seed: () => OrganizationLoaded(organization, lookups: lookupData),
      act: (cubit) => cubit.save(OrganizationUpdate.from(organization)),
      expect: () => [
        OrganizationLoaded(
          organization,
          lookups: lookupData,
          busy: OrganizationBusy.saving,
        ),
        OrganizationLoaded(
          organization,
          lookups: lookupData,
          outcome: const ActionOutcome(OrganizationResult.saved, 1),
        ),
      ],
    );

    blocTest<OrganizationCubit, OrganizationState>(
      'binds validation errors by path',
      setUp: () => when(() => organizations.update(any())).thenThrow(
        _validation({
          'national_address.postal_code': ['5 digits'],
        }),
      ),
      build: build,
      seed: () => OrganizationLoaded(organization),
      act: (cubit) async {
        await cubit.save(OrganizationUpdate.from(organization));
        cubit.fieldEdited('national_address.postal_code');
      },
      expect: () => [
        OrganizationLoaded(organization, busy: OrganizationBusy.saving),
        isA<OrganizationLoaded>()
            .having(
              (s) => s.fieldError('national_address.postal_code'),
              'postal code',
              '5 digits',
            )
            .having((s) => s.error, 'form error', isNull),
        OrganizationLoaded(organization),
      ],
    );

    blocTest<OrganizationCubit, OrganizationState>(
      'the logo upload and removal',
      setUp: () {
        when(() => organizations.uploadLogo('/tmp/logo.png'))
            .thenAnswer((_) async => organization);
        when(organizations.deleteLogo).thenAnswer((_) async => organization);
      },
      build: build,
      seed: () => OrganizationLoaded(organization),
      act: (cubit) async {
        await cubit.uploadLogo('/tmp/logo.png');
        await cubit.removeLogo();
      },
      expect: () => [
        OrganizationLoaded(organization, busy: OrganizationBusy.uploadingLogo),
        OrganizationLoaded(
          organization,
          outcome: const ActionOutcome(OrganizationResult.logoUpdated, 1),
        ),
        OrganizationLoaded(organization, busy: OrganizationBusy.removingLogo),
        OrganizationLoaded(
          organization,
          outcome: const ActionOutcome(OrganizationResult.logoRemoved, 2),
        ),
      ],
    );
  });

  group('TeamCubit', () {
    late _MockTeam team;
    final roster = TeamRoster(
      members: fixtureList('team_members_issuer')
          .map(TeamMember.fromJson)
          .toList(),
      seats: const Seats(used: 3, total: 3),
    );

    setUp(() => team = _MockTeam());

    blocTest<TeamCubit, TeamState>(
      'without team.manage it is forbidden and sends nothing',
      build: () => TeamCubit(team: team, canManage: false),
      act: (cubit) => cubit.load(),
      expect: () => const [TeamForbidden()],
      verify: (_) => verifyZeroInteractions(team),
    );

    blocTest<TeamCubit, TeamState>(
      'loads the roster and the seats',
      setUp: () => when(() => team.members()).thenAnswer((_) async => roster),
      build: () => TeamCubit(team: team, canManage: true),
      act: (cubit) => cubit.load(),
      expect: () => [TeamLoaded(roster)],
    );

    blocTest<TeamCubit, TeamState>(
      'a 403 is forbidden; a failed reload keeps the roster',
      setUp: () {
        var calls = 0;
        when(() => team.members()).thenAnswer((_) async {
          calls++;
          if (calls == 1) return roster;
          if (calls == 2) throw _offline;
          throw const ApiException(code: 'forbidden', statusCode: 403);
        });
      },
      build: () => TeamCubit(team: team, canManage: true),
      act: (cubit) async {
        await cubit.load();
        await cubit.load();
        await cubit.load();
      },
      expect: () => [
        TeamLoaded(roster),
        TeamLoaded(roster, refreshError: _offline),
        const TeamForbidden(),
      ],
    );
  });

  group('TeamMemberFormCubit', () {
    late _MockTeam team;
    final members = fixtureList('team_members_issuer')
        .map(TeamMember.fromJson)
        .toList();
    final member = members.last;
    const seats = ApiException(
      code: 'seat_limit_reached',
      statusCode: 409,
      details: {
        'seats': {'used': 3, 'total': 3},
      },
    );

    setUp(() => team = _MockTeam());

    blocTest<TeamMemberFormCubit, TeamMemberFormState>(
      'invites, and seat_limit_reached shows the seats (no upgrade)',
      setUp: () {
        var calls = 0;
        when(() => team.invite(any())).thenAnswer((_) async {
          if (calls++ == 0) throw seats;
          return member;
        });
      },
      build: () => TeamMemberFormCubit(team: team),
      act: (cubit) async {
        const invite = TeamMemberInvite(
          name: 'Mona',
          email: 'mona@acme.sa',
          role: MembershipRole.member,
        );
        await cubit.invite(invite);
        await cubit.invite(invite);
      },
      expect: () => [
        const TeamMemberFormState(busy: TeamMemberBusy.saving),
        const TeamMemberFormState(seatLimit: Seats(used: 3, total: 3)),
        const TeamMemberFormState(busy: TeamMemberBusy.saving),
        TeamMemberFormState(
          member: member,
          outcome: const ActionOutcome(TeamMemberResult.invited, 1),
        ),
      ],
      verify: (cubit) => expect(cubit.isNew, isTrue),
    );

    blocTest<TeamMemberFormCubit, TeamMemberFormState>(
      'a deep link finds the member in the roster, or not found',
      setUp: () => when(() => team.members()).thenAnswer(
        (_) async =>
            TeamRoster(members: members, seats: const Seats(used: 3, total: 3)),
      ),
      build: () => TeamMemberFormCubit(team: team, membershipId: member.id),
      act: (cubit) => cubit.load(),
      expect: () => [
        const TeamMemberFormState(busy: TeamMemberBusy.loading),
        TeamMemberFormState(member: member),
      ],
    );

    blocTest<TeamMemberFormCubit, TeamMemberFormState>(
      'an unknown membership is not found',
      setUp: () => when(() => team.members()).thenAnswer(
        (_) async =>
            TeamRoster(members: members, seats: const Seats(used: 3, total: 3)),
      ),
      build: () => TeamMemberFormCubit(team: team, membershipId: 'nope'),
      act: (cubit) => cubit.load(),
      expect: () => const [
        TeamMemberFormState(busy: TeamMemberBusy.loading),
        TeamMemberFormState(notFound: true),
      ],
    );

    blocTest<TeamMemberFormCubit, TeamMemberFormState>(
      'updates the role and flags, then deactivates, resends and removes',
      setUp: () {
        when(
          () => team.update(
            member.id,
            role: MembershipRole.admin,
            canAward: true,
            canPurchase: false,
          ),
        ).thenAnswer((_) async => member);
        when(() => team.update(member.id, status: MembershipStatus.inactive))
            .thenAnswer((_) async => member);
        when(() => team.resendInvitation(member.id)).thenAnswer((_) async {});
        when(() => team.remove(member.id)).thenAnswer((_) async {});
      },
      build: () => TeamMemberFormCubit(
        team: team,
        membershipId: member.id,
        member: member,
      ),
      act: (cubit) async {
        await cubit.update(
          role: MembershipRole.admin,
          canAward: true,
          canPurchase: false,
        );
        await cubit.setActive(active: false);
        await cubit.resend();
        await cubit.remove();
      },
      expect: () => [
        TeamMemberFormState(member: member, busy: TeamMemberBusy.saving),
        TeamMemberFormState(
          member: member,
          outcome: const ActionOutcome(TeamMemberResult.saved, 1),
        ),
        TeamMemberFormState(member: member, busy: TeamMemberBusy.deactivating),
        TeamMemberFormState(
          member: member,
          outcome: const ActionOutcome(TeamMemberResult.deactivated, 2),
        ),
        TeamMemberFormState(member: member, busy: TeamMemberBusy.resending),
        TeamMemberFormState(
          member: member,
          outcome: const ActionOutcome(TeamMemberResult.resent, 3),
        ),
        TeamMemberFormState(member: member, busy: TeamMemberBusy.removing),
        TeamMemberFormState(
          member: member,
          outcome: const ActionOutcome(TeamMemberResult.removed, 4),
        ),
      ],
      verify: (cubit) => expect(cubit.isNew, isFalse),
    );

    blocTest<TeamMemberFormCubit, TeamMemberFormState>(
      'cannot_modify_owner is a business error; a taken e-mail a field error',
      setUp: () {
        when(
          () => team.update(
            any(),
            role: any(named: 'role'),
            canAward: any(named: 'canAward'),
            canPurchase: any(named: 'canPurchase'),
          ),
        ).thenThrow(
          const ApiException(code: 'cannot_modify_owner', statusCode: 409),
        );
      },
      build: () => TeamMemberFormCubit(
        team: team,
        membershipId: member.id,
        member: member,
      ),
      act: (cubit) => cubit.update(
        role: MembershipRole.member,
        canAward: false,
        canPurchase: false,
      ),
      expect: () => [
        TeamMemberFormState(member: member, busy: TeamMemberBusy.saving),
        TeamMemberFormState(
          member: member,
          error: const ApiException(
            code: 'cannot_modify_owner',
            statusCode: 409,
          ),
        ),
      ],
    );
  });

  group('InvoicesCubit', () {
    late _MockBilling billing;
    final invoices = fixtureList('billing_invoices_issuer')
        .map(InvoiceSummary.fromJson)
        .toList();

    Paged<InvoiceSummary> page(
      List<InvoiceSummary> items, {
      int number = 1,
      bool hasMore = false,
    }) => Paged(
      items: items,
      meta: PageMeta(currentPage: number, perPage: 20, hasMore: hasMore),
    );

    setUp(() => billing = _MockBilling());

    blocTest<InvoicesCubit, InvoicesState>(
      'without billing.view it is forbidden and sends nothing',
      build: () => InvoicesCubit(billing: billing, canView: false),
      act: (cubit) => cubit.load(),
      expect: () => const [InvoicesForbidden()],
      verify: (_) => verifyZeroInteractions(billing),
    );

    blocTest<InvoicesCubit, InvoicesState>(
      'loads the first page and the next one',
      setUp: () {
        when(() => billing.invoices())
            .thenAnswer((_) async => page(invoices, hasMore: true));
        when(() => billing.invoices(page: 2))
            .thenAnswer((_) async => page(invoices, number: 2));
      },
      build: () => InvoicesCubit(billing: billing, canView: true),
      act: (cubit) async {
        await cubit.load();
        await cubit.loadMore();
      },
      expect: () => [
        InvoicesLoaded(invoices: invoices, page: 1, hasMore: true),
        InvoicesLoaded(
          invoices: invoices,
          page: 1,
          hasMore: true,
          loadingMore: true,
        ),
        // The repeated row is dropped.
        InvoicesLoaded(invoices: invoices, page: 2, hasMore: false),
      ],
    );

    blocTest<InvoicesCubit, InvoicesState>(
      'a 403 is forbidden, other failures are errors',
      setUp: () {
        var calls = 0;
        when(() => billing.invoices()).thenAnswer((_) async {
          if (calls++ == 0) throw _offline;
          throw const ApiException(code: 'forbidden', statusCode: 403);
        });
      },
      build: () => InvoicesCubit(billing: billing, canView: true),
      act: (cubit) async {
        await cubit.load();
        await cubit.load();
      },
      expect: () => const [
        InvoicesFailure(_offline),
        InvoicesLoading(),
        InvoicesForbidden(),
      ],
    );
  });

  group('ContactCubit', () {
    late _MockContact contact;
    final now = DateTime.utc(2026, 10, 1, 9);
    const message = ContactMessage(
      name: 'Sara',
      email: 'sara@acme.sa',
      subject: 'Help',
      message: 'Hello',
    );

    setUp(() => contact = _MockContact());

    blocTest<ContactCubit, ContactState>(
      'sends POST /contact, then shows the confirmation',
      setUp: () =>
          when(() => contact.send(message))
              .thenAnswer((_) async => '01jcontact'),
      build: () => ContactCubit(contact, now: () => now),
      act: (cubit) async {
        await cubit.send(message);
        cubit.reset();
      },
      expect: () => const [ContactSending(), ContactSent(), ContactInitial()],
    );

    blocTest<ContactCubit, ContactState>(
      '429 disables sending for Retry-After',
      setUp: () => when(() => contact.send(any())).thenThrow(
        const ApiException(
          code: 'too_many_requests',
          statusCode: 429,
          details: {'retry_after_seconds': 120},
        ),
      ),
      build: () => ContactCubit(contact, now: () => now),
      act: (cubit) => cubit.send(message),
      expect: () => [
        const ContactSending(),
        isA<ContactFailure>().having(
          (s) => s.retryAt,
          'retryAt',
          now.add(const Duration(seconds: 120)),
        ),
      ],
    );

    test('the honeypot is always sent empty', () {
      expect(message.toJson()['website_url'], '');
      expect(message.toJson()['phone'], isNull);
    });
  });

  group('AccountDeletionCubit', () {
    late _MockDeletion deletion;
    final pending = AccountDeletionRequest(
      id: '01jdel',
      scope: DeletionScope.organization,
      status: 'pending',
      scheduledFor: DateTime.utc(2026, 10, 15),
    );

    setUp(() => deletion = _MockDeletion());

    blocTest<AccountDeletionCubit, AccountDeletionState>(
      'no pending request, then a request with the password',
      setUp: () {
        when(deletion.current).thenAnswer((_) async => null);
        when(() => deletion.request(password: 'secret', reason: null))
            .thenAnswer((_) async => pending);
      },
      build: () => AccountDeletionCubit(deletion),
      act: (cubit) async {
        await cubit.load();
        await cubit.request(password: 'secret', reason: '  ');
      },
      expect: () => [
        const AccountDeletionLoaded(),
        const AccountDeletionLoaded(busy: DeletionBusy.requesting),
        AccountDeletionLoaded(
          pending: pending,
          outcome: const ActionOutcome(DeletionResult.requested, 1),
        ),
      ],
    );

    blocTest<AccountDeletionCubit, AccountDeletionState>(
      'blockers and a wrong password are shown on the form',
      setUp: () {
        var calls = 0;
        when(
          () => deletion.request(
            password: any(named: 'password'),
            reason: any(named: 'reason'),
          ),
        ).thenAnswer((_) async {
          if (calls++ == 0) {
            throw const ApiException(
              code: 'account_deletion_blocked',
              statusCode: 409,
              details: {
                'blockers': [
                  {
                    'type': 'issued_competition',
                    'competition_id': '01jcomp',
                    'title': 'توريد أجهزة',
                  },
                ],
              },
            );
          }
          throw const ApiException(code: 'password_incorrect', statusCode: 422);
        });
      },
      build: () => AccountDeletionCubit(deletion),
      seed: () => const AccountDeletionLoaded(),
      act: (cubit) async {
        await cubit.request(password: 'secret');
        await cubit.request(password: 'wrong');
      },
      expect: () => [
        const AccountDeletionLoaded(busy: DeletionBusy.requesting),
        const AccountDeletionLoaded(
          blockers: [
            DeletionBlocker(
              type: 'issued_competition',
              competitionId: '01jcomp',
              title: 'توريد أجهزة',
            ),
          ],
        ),
        const AccountDeletionLoaded(busy: DeletionBusy.requesting),
        const AccountDeletionLoaded(
          passwordError: ApiException(
            code: 'password_incorrect',
            statusCode: 422,
          ),
        ),
      ],
    );

    blocTest<AccountDeletionCubit, AccountDeletionState>(
      'account_deletion_pending reloads the pending request',
      setUp: () {
        when(
          () => deletion.request(
            password: any(named: 'password'),
            reason: any(named: 'reason'),
          ),
        ).thenThrow(
          const ApiException(code: 'account_deletion_pending', statusCode: 409),
        );
        when(deletion.current).thenAnswer((_) async => pending);
      },
      build: () => AccountDeletionCubit(deletion),
      seed: () => const AccountDeletionLoaded(),
      act: (cubit) => cubit.request(password: 'secret'),
      expect: () => [
        const AccountDeletionLoaded(busy: DeletionBusy.requesting),
        const AccountDeletionLoading(),
        AccountDeletionLoaded(pending: pending),
      ],
    );

    blocTest<AccountDeletionCubit, AccountDeletionState>(
      'cancels the pending request',
      setUp: () => when(deletion.cancel).thenAnswer((_) async {}),
      build: () => AccountDeletionCubit(deletion),
      seed: () => AccountDeletionLoaded(pending: pending),
      act: (cubit) => cubit.cancel(),
      expect: () => [
        AccountDeletionLoaded(pending: pending, busy: DeletionBusy.cancelling),
        const AccountDeletionLoaded(
          outcome: ActionOutcome(DeletionResult.cancelled, 1),
        ),
      ],
    );
  });

  test('an answer that arrives after the screen closed is dropped', () async {
    final organizations = _MockOrganizations();
    final session = await signedInSession(issuerMe);
    final answer = Completer<Organization>();
    when(organizations.organization).thenAnswer((_) => answer.future);
    final cubit = OrganizationCubit(
      organizations: organizations,
      lookups: _MockLookups(),
      session: session,
    );
    final load = cubit.load();
    await cubit.close();
    answer.complete(Organization.fromJson(fixtureData('organization_issuer')));
    await expectLater(load, completes);
    expect(cubit.state, const OrganizationLoading());
    await session.close();
  });

  test('the member fixture has no team or billing permission', () {
    expect(memberMe.can(Permissions.teamManage), isFalse);
    expect(memberMe.can(Permissions.billingView), isFalse);
    expect(issuerMe.can(Permissions.deleteOrganization), isTrue);
  });
}
