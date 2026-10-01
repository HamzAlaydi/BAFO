import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/legal_screen.dart';
import 'package:bafo/features/auth/presentation/login_screen.dart';
import 'package:bafo/features/auth/presentation/password_reset_screens.dart';
import 'package:bafo/features/auth/presentation/register_screen.dart';
import 'package:bafo/features/auth/presentation/verify_email_screen.dart';
import 'package:bafo/features/auth/presentation/welcome_screen.dart';
import 'package:go_router/go_router.dart';

/// What the verification screen needs. Passed as `extra`: the e-mail never
/// goes into a location string.
final class VerifyEmailArgs {
  const VerifyEmailArgs({required this.email, this.expiresAt});

  final String email;
  final DateTime? expiresAt;
}

/// The signed-out routes (M02, M06–M13). A screen reached without its
/// `extra` (e.g. after process death) falls back to its entry point.
List<RouteBase> authRoutes() => [
  GoRoute(
    path: AppRoutes.welcome,
    builder: (_, state) => WelcomeScreen(from: state.uri.queryParameters['from']),
  ),
  GoRoute(path: AppRoutes.login, builder: (_, _) => const LoginScreen()),
  GoRoute(path: AppRoutes.register, builder: (_, _) => const RegisterScreen()),
  GoRoute(
    path: AppRoutes.verify,
    redirect: (_, state) =>
        state.extra is VerifyEmailArgs ? null : AppRoutes.login,
    builder: (_, state) =>
        VerifyEmailScreen(args: state.extra! as VerifyEmailArgs),
  ),
  GoRoute(
    path: AppRoutes.forgotPassword,
    builder: (_, _) => const ForgotPasswordScreen(),
  ),
  GoRoute(
    path: AppRoutes.resetPassword,
    redirect: (_, state) =>
        state.extra is String ? null : AppRoutes.forgotPassword,
    builder: (_, state) => ResetPasswordScreen(email: state.extra! as String),
  ),
  GoRoute(
    path: '${AppRoutes.legalPrefix}/:code',
    redirect: (_, state) =>
        LegalCode.tryParse(state.pathParameters['code']) == null
        ? AppRoutes.home
        : null,
    builder: (_, state) =>
        LegalScreen(code: LegalCode.tryParse(state.pathParameters['code'])!),
  ),
];
