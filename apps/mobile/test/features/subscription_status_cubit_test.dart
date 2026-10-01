import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/billing/presentation/subscription_status_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../helpers/fakes.dart';

class _MockBilling extends Mock implements BillingRepository {}

void main() {
  group('SubscriptionStatusCubit', () {
    late _MockBilling billing;

    setUp(() => billing = _MockBilling());

    Future<SessionCubit> sessionWith(Me me) async {
      final session = SessionCubit(
        tokens: InMemoryTokenStore(),
        unauthorized: const Stream.empty(),
        fetchMe: () async => me,
      );
      await session.signIn(AuthTokenPayload(token: 't', me: me));
      addTearDown(session.close);
      return session;
    }

    test('with billing.view it reads the overview', () async {
      when(billing.subscription).thenAnswer(
        (_) async => SubscriptionOverview.fromJson(
          fixtureData('billing_subscription_issuer'),
        ),
      );
      final cubit = SubscriptionStatusCubit(
        billing: billing,
        session: await sessionWith(fixtureMe()),
      );
      await cubit.load();
      final status = (cubit.state as SubscriptionStatusLoaded).status;
      expect(status.plan?.code, 'pro');
      expect(status.source, SubscriptionSource.paid);
      expect(status.seatsTotal, 3);
      await cubit.close();
    });

    test('without billing.view it shows Me.subscription only', () async {
      final cubit = SubscriptionStatusCubit(
        billing: billing,
        session: await sessionWith(fixtureMe('me_member')),
      );
      await cubit.load();
      final status = (cubit.state as SubscriptionStatusLoaded).status;
      expect(status.plan?.code, 'pro');
      expect(status.seatsUsed, 3);
      verifyNever(billing.subscription);
      await cubit.close();
    });

    blocTest<SubscriptionStatusCubit, SubscriptionStatusState>(
      'no subscription reads as "no plan"',
      build: () => SubscriptionStatusCubit(
        billing: billing,
        session: SessionCubit(
          tokens: InMemoryTokenStore(),
          unauthorized: const Stream.empty(),
          fetchMe: () async => fixtureMe('me_supplier_b'),
        ),
      ),
      act: (cubit) => cubit.load(),
      verify: (cubit) => expect(
        (cubit.state as SubscriptionStatusLoaded).status.hasPlan,
        isFalse,
      ),
    );
  });
}
