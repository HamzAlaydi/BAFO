import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const unknown = SessionUnknown();
  const signedOut = SessionUnauthenticated();
  const signedIn = SessionAuthenticated();

  String? auth(
    SessionState session,
    String location, {
    bool firstRun = false,
  }) => authRedirect(session, Uri.parse(location), onboardingDue: firstRun);

  group('while the session is restored', () {
    test('everything waits on the splash', () {
      expect(auth(unknown, '/home'), '/splash');
      expect(auth(unknown, '/splash'), isNull);
    });

    test('a deep link is kept for later', () {
      expect(
        auth(unknown, '/competitions/01j'),
        '/splash?from=%2Fcompetitions%2F01j',
      );
    });
  });

  group('signed out', () {
    test('protected pages go to login', () {
      expect(auth(signedOut, '/home'), '/login');
      expect(auth(signedOut, '/splash'), '/login');
      expect(auth(signedOut, '/account'), '/login?from=%2Faccount');
      expect(auth(signedOut, '/billing'), '/login?from=%2Fbilling');
    });

    test('an explicit sign-out does not keep the page for the next user', () {
      const afterSignOut = SessionUnauthenticated(signedOut: true);
      expect(auth(afterSignOut, '/account'), '/login');
      expect(auth(afterSignOut, '/competitions/01j/live'), '/login');
      expect(auth(afterSignOut, '/login'), isNull);
    });

    test('the first run shows the welcome slides, keeping the deep link', () {
      expect(auth(signedOut, '/home', firstRun: true), '/welcome');
      expect(
        auth(signedOut, '/splash?from=%2Fcompetitions%2F01j', firstRun: true),
        '/welcome?from=%2Fcompetitions%2F01j',
      );
    });

    test('the deep link survives the splash', () {
      expect(
        auth(signedOut, '/splash?from=%2Fcompetitions%2F01j'),
        '/login?from=%2Fcompetitions%2F01j',
      );
    });

    test('the sign-in flow and legal pages stay put', () {
      for (final path in [
        '/login',
        '/login?from=%2Faccount',
        '/welcome',
        '/register',
        '/verify',
        '/forgot-password',
        '/reset-password',
        '/legal/terms',
      ]) {
        expect(auth(signedOut, path), isNull, reason: path);
      }
    });
  });

  group('signed in', () {
    test('leaves splash and the sign-in flow for home', () {
      for (final path in [
        '/splash',
        '/login',
        '/register',
        '/verify',
        '/welcome',
      ]) {
        expect(auth(signedIn, path), '/home', reason: path);
      }
    });

    test('legal pages stay reachable', () {
      expect(auth(signedIn, '/legal/privacy'), isNull);
    });

    test('returns to the deep link', () {
      expect(auth(signedIn, '/login?from=%2Faccount'), '/account');
      expect(
        auth(signedIn, '/splash?from=%2Fcompetitions%2F01j%2Flive'),
        '/competitions/01j/live',
      );
    });

    test('never follows an external redirect target', () {
      expect(
        auth(signedIn, '/login?from=https%3A%2F%2Fevil.example%2F'),
        '/home',
      );
      expect(auth(signedIn, '/login?from=%2F%2Fevil.example'), '/home');
    });

    test('protected pages stay put', () {
      expect(auth(signedIn, '/home'), isNull);
      expect(auth(signedIn, '/competitions'), isNull);
      expect(auth(signedIn, '/billing'), isNull);
    });
  });

  group('app gate', () {
    String? app(AppGateState gate, SessionState session, String location) =>
        appRedirect(
          gate: gate,
          session: session,
          location: Uri.parse(location),
        );

    test('a required update blocks every screen, signed in or not', () {
      const gate = AppGateUpdateRequired();
      expect(app(gate, signedIn, '/home'), '/update-required');
      expect(app(gate, signedOut, '/login'), '/update-required');
      expect(app(gate, signedIn, '/update-required'), isNull);
    });

    test('maintenance blocks until the gate reopens', () {
      const gate = AppGateMaintenance('');
      expect(app(gate, signedIn, '/competitions'), '/maintenance');
      expect(app(gate, signedIn, '/maintenance'), isNull);
    });

    test('the account gate blocks a signed-in user only', () {
      const gate = AppGateAccountBlocked(code: 'account_inactive');
      expect(app(gate, signedIn, '/home'), '/account-blocked');
      expect(app(gate, signedIn, '/account-blocked'), isNull);
      expect(app(gate, signedOut, '/login'), isNull);
    });

    test('an open gate leaves the gate screens', () {
      expect(app(const AppGateOpen(), signedIn, '/maintenance'), '/home');
      expect(app(const AppGateOpen(), signedIn, '/account-blocked'), '/home');
      expect(app(const AppGateOpen(), signedIn, '/account'), isNull);
    });
  });
}
