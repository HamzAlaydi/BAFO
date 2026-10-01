import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/l10n/locale_cubit.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/auth_routes.dart';
import 'package:bafo/features/auth/presentation/legal_screen.dart';
import 'package:bafo/features/auth/presentation/register_screen.dart';
import 'package:bafo/features/auth/presentation/verify_email_screen.dart';
import 'package:bafo/features/auth/presentation/welcome_screen.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:bafo/features/billing/presentation/plan_status_screen.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/single_child_widget.dart';

import '../../helpers/fakes.dart';
import '../../helpers/pump.dart';

class _FixtureLookups implements LookupsRepository {
  final Lookups data = Lookups.fromJson(fixtureData('lookups'));

  @override
  Future<Lookups> lookups({bool refresh = false}) async => data;

  @override
  Future<List<CloseReason>> closeReasons(CloseReasonKind kind) async =>
      data.closeReasonsOf(kind);
}

class _FixtureLegal implements LegalRepository {
  _FixtureLegal({this.missing = false});

  final bool missing;

  @override
  Future<LegalDocument> document(LegalCode code) async {
    if (missing) throw const ApiException(code: 'not_found', statusCode: 404);
    return LegalDocument.fromJson(fixtureData('legal_terms'));
  }
}

class _FixtureBilling implements BillingRepository {
  @override
  Future<SubscriptionOverview> subscription() async =>
      SubscriptionOverview.fromJson(fixtureData('billing_subscription_issuer'));

  @override
  Future<Paged<InvoiceSummary>> invoices({int page = 1, int perPage = 20}) =>
      throw UnimplementedError();
}

void main() {
  late FakeAuthRepository auth;
  late SessionCubit session;

  setUp(() {
    auth = FakeAuthRepository(payload: fixturePayload('auth_otp_verify'));
    session = SessionCubit(
      tokens: InMemoryTokenStore(),
      unauthorized: const Stream.empty(),
      fetchMe: () async => fixtureMe(),
    );
  });

  tearDown(() => session.close());

  List<SingleChildWidget> providers() => [
    RepositoryProvider<AuthRepository>.value(value: auth),
    RepositoryProvider<LookupsRepository>.value(value: _FixtureLookups()),
    RepositoryProvider<LegalRepository>.value(value: _FixtureLegal()),
    RepositoryProvider<PreferencesStore>.value(
      value: InMemoryPreferencesStore(),
    ),
    BlocProvider<SessionCubit>.value(value: session),
    BlocProvider<LocaleCubit>(
      create: (_) => LocaleCubit(InMemoryPreferencesStore()),
    ),
  ];

  Future<GoRouter> pumpRegister(
    WidgetTester tester, {
    Locale locale = const Locale('ar'),
  }) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 2.5;
    addTearDown(tester.view.reset);
    return pumpRouter(
      tester,
      locale: locale,
      initialLocation: '/register',
      providers: providers(),
      routes: [
        GoRoute(path: '/register', builder: (_, _) => const RegisterScreen()),
        GoRoute(
          path: '/verify',
          builder: (_, state) =>
              VerifyEmailScreen(args: state.extra! as VerifyEmailArgs),
        ),
        GoRoute(
          path: '/login',
          builder: (_, _) => const Scaffold(body: Text('login')),
        ),
      ],
    );
  }

  Future<void> tapKey(WidgetTester tester, String key) async {
    await tester.ensureVisible(find.byKey(Key(key)));
    await tester.tap(find.byKey(Key(key)));
    await tester.pumpAndSettle();
  }

  group('register (M07–M09)', () {
    testWidgets('Arabic validators on step 1', (tester) async {
      await pumpRegister(tester);
      await tapKey(tester, 'register.next');
      expect(find.text('الخطوة 1 من 3'), findsOneWidget);
      expect(find.text('هذا الحقل مطلوب.'), findsWidgets);
      await enterByKey(tester, 'register.email', 'not-an-email');
      await enterByKey(tester, 'register.phone', '412345678');
      await tapKey(tester, 'register.next');
      expect(find.text('أدخل بريداً إلكترونياً صحيحاً.'), findsOneWidget);
      expect(
        find.text('أدخل رقم جوال سعودي من 9 أرقام يبدأ بالرقم 5.'),
        findsOneWidget,
      );
    });

    testWidgets('English validators, LTR', (tester) async {
      await pumpRegister(tester, locale: const Locale('en'));
      await enterByKey(tester, 'register.password', 'short');
      await enterByKey(tester, 'register.confirmation', 'other');
      await tapKey(tester, 'register.next');
      expect(
        find.text('The password does not meet the rules.'),
        findsOneWidget,
      );
      expect(find.text('The passwords do not match.'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.byType(RegisterScreen))),
        TextDirection.ltr,
      );
    });

    testWidgets('three steps, consent, then verification', (tester) async {
      await pumpRegister(tester);
      await enterByKey(tester, 'register.name', 'أحمد العتيبي');
      await enterByKey(tester, 'register.email', 'ahmed@company.sa');
      await enterByKey(tester, 'register.phone', '512345678');
      await enterByKey(tester, 'register.password', 'Bafo-Mobile-2026');
      await enterByKey(tester, 'register.confirmation', 'Bafo-Mobile-2026');
      await tapKey(tester, 'register.next');
      expect(find.text('الخطوة 2 من 3'), findsOneWidget);

      await enterByKey(tester, 'register.company', 'شركة الاختبار');
      await enterByKey(tester, 'register.cr', '١٢٣٤٥٦٧٨٩٠');
      await enterByKey(tester, 'register.city', 'الرياض');
      await tester.tap(find.byKey(const Key('register.region')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('الرياض').last);
      await tester.pumpAndSettle();
      await tapKey(tester, 'register.next');
      expect(find.text('الخطوة 3 من 3'), findsOneWidget);

      await tapKey(tester, 'register.submit');
      expect(find.text('يلزم الموافقة للمتابعة.'), findsNWidgets(2));
      expect(auth.lastRegistration, isNull);

      await tapKey(tester, 'register.terms');
      await tapKey(tester, 'register.privacy');
      await tapKey(tester, 'register.submit');

      final request = auth.lastRegistration!;
      expect(request.phone, '+966512345678');
      expect(request.organization.crNumber, '1234567890');
      expect(request.organization.regionId, isNotEmpty);
      expect(request.locale, 'ar');
      expect(find.byType(VerifyEmailScreen), findsOneWidget);
    });

    testWidgets('server field errors bring back the step that holds them', (
      tester,
    ) async {
      auth.error = ApiException.fromResponse(
        statusCode: 422,
        body: fixtureBody('error_register_taken'),
      );
      await pumpRegister(tester);
      await enterByKey(tester, 'register.name', 'أحمد');
      await enterByKey(
        tester,
        'register.email',
        'supplier-a.owner@demo.bafo.test',
      );
      await enterByKey(tester, 'register.phone', '512345678');
      await enterByKey(tester, 'register.password', 'Bafo-Mobile-2026');
      await enterByKey(tester, 'register.confirmation', 'Bafo-Mobile-2026');
      await tapKey(tester, 'register.next');
      await enterByKey(tester, 'register.company', 'شركة');
      await enterByKey(tester, 'register.cr', '1010000002');
      await enterByKey(tester, 'register.city', 'جدة');
      await tester.tap(find.byKey(const Key('register.region')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('مكة المكرمة').last);
      await tester.pumpAndSettle();
      await tapKey(tester, 'register.next');
      await tapKey(tester, 'register.terms');
      await tapKey(tester, 'register.privacy');
      await tapKey(tester, 'register.submit');

      expect(find.text('الخطوة 1 من 3'), findsOneWidget);
      expect(
        find.text('يوجد حساب مسجل بهذا البريد الإلكتروني.'),
        findsOneWidget,
      );
    });
  });

  group('verify e-mail (M10)', () {
    Future<void> pumpVerify(WidgetTester tester) => pumpRouter(
      tester,
      initialLocation: '/verify',
      initialExtra: VerifyEmailArgs(
        email: 'ahmed@company.sa',
        expiresAt: DateTime.now().toUtc().add(const Duration(minutes: 10)),
      ),
      providers: providers(),
      routes: [
        GoRoute(
          path: '/verify',
          builder: (_, state) =>
              VerifyEmailScreen(args: state.extra! as VerifyEmailArgs),
        ),
      ],
    );

    testWidgets('six digits verify and sign in', (tester) async {
      await pumpVerify(tester);
      expect(find.textContaining('ahmed@company.sa'), findsOneWidget);
      expect(find.textContaining('تنتهي صلاحية الرمز خلال'), findsOneWidget);
      await tester.enterText(find.byKey(const Key('otp.input')), '123456');
      await tester.pump();
      await tester.pump();
      expect(session.state.isAuthenticated, isTrue);
    });

    testWidgets('a wrong code shows the error under the boxes', (tester) async {
      auth.error = ApiException.fromResponse(
        statusCode: 422,
        body: fixtureBody('error_otp_invalid'),
      );
      await pumpVerify(tester);
      await tester.enterText(find.byKey(const Key('otp.input')), '000000');
      await tester.pump();
      await tester.pump();
      expect(find.text('الرمز غير صحيح.'), findsOneWidget);
      expect(session.state.isAuthenticated, isFalse);
      // Resend waits for the 60 s cooldown.
      expect(find.textContaining('إعادة الإرسال بعد'), findsOneWidget);
    });
  });

  testWidgets('welcome: three slides, then sign in (M02)', (tester) async {
    await pumpRouter(
      tester,
      initialLocation: '/welcome',
      providers: providers(),
      routes: [
        GoRoute(path: '/welcome', builder: (_, _) => const WelcomeScreen()),
        GoRoute(
          path: '/login',
          builder: (_, _) => const Scaffold(body: Text('login-screen')),
        ),
      ],
    );
    expect(find.text('اختر لغة التطبيق'), findsOneWidget);
    await tester.tap(find.byKey(const Key('welcome.next')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('welcome.next')));
    await tester.pumpAndSettle();
    expect(find.text('منافسة مباشرة وترسية'), findsOneWidget);
    await tester.tap(find.byKey(const Key('welcome.signIn')));
    await tester.pumpAndSettle();
    expect(find.text('login-screen'), findsOneWidget);
  });

  group('legal (M13)', () {
    testWidgets('renders the document with version', (tester) async {
      await pumpLocalized(
        tester,
        const LegalScreen(code: LegalCode.terms),
        wrapInScaffold: false,
        providers: [
          RepositoryProvider<LegalRepository>.value(value: _FixtureLegal()),
        ],
      );
      await tester.pump();
      await tester.pump();
      expect(find.text('الشروط والأحكام'), findsWidgets);
      expect(find.text('الإصدار \u20662026-10-01\u2069'), findsOneWidget);
    });

    testWidgets('an unpublished document is "not available"', (tester) async {
      await pumpLocalized(
        tester,
        const LegalScreen(code: LegalCode.refund),
        wrapInScaffold: false,
        providers: [
          RepositoryProvider<LegalRepository>.value(
            value: _FixtureLegal(missing: true),
          ),
        ],
      );
      await tester.pump();
      await tester.pump();
      expect(find.text('هذه الوثيقة غير متاحة حالياً.'), findsOneWidget);
    });
  });

  testWidgets('plan status (M58) shows no price, URL or purchase action', (
    tester,
  ) async {
    await session.signIn(fixturePayload('auth_login_issuer'));
    await pumpLocalized(
      tester,
      const PlanStatusScreen(highlightInvoices: true),
      wrapInScaffold: false,
      providers: [
        BlocProvider<SessionCubit>.value(value: session),
        RepositoryProvider<BillingRepository>.value(value: _FixtureBilling()),
      ],
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('باقة برو'), findsOneWidget);
    expect(
      find.text('تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.'),
      findsOneWidget,
    );
    expect(
      find.text('الفواتير متاحة في لوحة تحكم بافو على الويب.'),
      findsOneWidget,
    );
    // Entitlement only (CD6): no buttons, no prices, no links.
    expect(find.byType(ButtonStyleButton), findsNothing);
    expect(find.byType(IconButton), findsNothing);
    final texts = tester
        .widgetList<Text>(find.byType(Text))
        .map((t) => t.data ?? t.textSpan?.toPlainText() ?? '')
        .join('\n');
    expect(texts, isNot(contains('ر.س')));
    expect(texts, isNot(contains('SAR')));
    expect(texts, isNot(contains('http')));
  });
}
