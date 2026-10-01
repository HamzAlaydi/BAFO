import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/router/app_router.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/auth/data/auth_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:bafo/features/auth/presentation/auth_routes.dart';
import 'package:bafo/features/auth/presentation/register_cubit.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// M07–M09: create a company account in three steps. Client rules give
/// feedback per step; the server decides, and its field errors send the
/// user back to the step that holds the field. Success → M10.
class RegisterScreen extends StatelessWidget {
  const RegisterScreen({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => RegisterCubit(
      auth: context.read<AuthRepository>(),
      lookups: context.read<LookupsRepository>(),
    )..loadLookups(),
    child: const _RegisterView(),
  );
}

class _RegisterView extends StatefulWidget {
  const _RegisterView();

  @override
  State<_RegisterView> createState() => _RegisterViewState();
}

class _RegisterViewState extends State<_RegisterView> {
  final _forms = List.generate(
    RegisterState.stepCount,
    (_) => GlobalKey<FormState>(),
  );
  final _scroll = ScrollController();

  // Step 1: account.
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();

  // Step 2: company.
  final _company = TextEditingController();
  final _cr = TextEditingController();
  final _city = TextEditingController();
  final _vat = TextEditingController();
  final _legalAr = TextEditingController();
  final _legalEn = TextEditingController();
  final _website = TextEditingController();
  String? _regionId;
  bool _vatRegistered = false;

  // Step 3: address and consent.
  final _building = TextEditingController();
  final _street = TextEditingController();
  final _district = TextEditingController();
  final _postal = TextEditingController();
  final _additional = TextEditingController();
  final _short = TextEditingController();
  Set<String> _categoryIds = {};
  bool _visibleInSuggestions = true;
  bool _acceptTerms = false;
  bool _acceptPrivacy = false;
  bool _consentTried = false;

  List<TextEditingController> get _controllers => [
    _name,
    _email,
    _phone,
    _password,
    _confirmation,
    _company,
    _cr,
    _city,
    _vat,
    _legalAr,
    _legalEn,
    _website,
    _building,
    _street,
    _district,
    _postal,
    _additional,
    _short,
  ];

  @override
  void dispose() {
    for (final controller in _controllers) {
      controller.dispose();
    }
    _scroll.dispose();
    super.dispose();
  }

  RegisterCubit get _cubit => context.read<RegisterCubit>();

  void _next(int step) {
    FocusScope.of(context).unfocus();
    if (!(_forms[step].currentState?.validate() ?? false)) return;
    _cubit.next();
    _scrollToTop();
  }

  void _scrollToTop() {
    if (_scroll.hasClients) _scroll.jumpTo(0);
  }

  void _submit() {
    FocusScope.of(context).unfocus();
    setState(() => _consentTried = true);
    final valid = _forms[2].currentState?.validate() ?? false;
    if (!valid || !_acceptTerms || !_acceptPrivacy) return;
    String? blank(TextEditingController c) =>
        c.text.trim().isEmpty ? null : normalizeDigits(c.text.trim());
    _cubit.submit(
      RegistrationRequest(
        name: _name.text,
        email: _email.text,
        phone: PhoneField.toE164(_phone.text),
        password: _password.text,
        passwordConfirmation: _confirmation.text,
        locale: context.languageCode,
        organization: OrganizationRegistration(
          name: _company.text,
          crNumber: normalizeDigits(_cr.text.trim()),
          regionId: _regionId ?? '',
          city: _city.text,
          vatRegistered: _vatRegistered,
          vatNumber: _vatRegistered ? normalizeDigits(_vat.text.trim()) : null,
          legalNameAr: _legalAr.text,
          legalNameEn: _legalEn.text,
          website: _website.text,
          nationalAddress: NationalAddress(
            buildingNumber: blank(_building),
            street: _street.text.trim(),
            district: _district.text.trim(),
            postalCode: blank(_postal),
            additionalNumber: blank(_additional),
            shortAddress: blank(_short)?.toUpperCase(),
          ),
          categoryIds: _categoryIds.toList(),
          visibleInSuggestions: _visibleInSuggestions,
        ),
        acceptTerms: _acceptTerms,
        acceptPrivacy: _acceptPrivacy,
      ),
    );
  }

  void _onState(BuildContext context, RegisterState state) {
    final result = state.result;
    if (state.status == RegisterStatus.succeeded && result != null) {
      context.pushReplacement(
        AppRoutes.verify,
        extra: VerifyEmailArgs(
          email: result.email,
          expiresAt: result.otpExpiresAt,
        ),
      );
      return;
    }
    final error = state.error;
    if (error != null) BafoToast.error(context, errorMessage(context.l10n, error));
    if (state.fieldErrors.isNotEmpty) {
      BafoToast.error(context, context.l10n.authRegisterFixErrors);
      _scrollToTop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return BlocConsumer<RegisterCubit, RegisterState>(
      listenWhen: (previous, current) =>
          current.status == RegisterStatus.succeeded ||
          (previous.isSubmitting && !current.isSubmitting),
      listener: _onState,
      builder: (context, state) {
        final titles = [
          l10n.authRegisterStepAccount,
          l10n.authRegisterStepCompany,
          l10n.authRegisterStepAddress,
        ];
        final step = state.step;
        return PopScope(
          canPop: step == 0,
          onPopInvokedWithResult: (didPop, _) {
            if (!didPop) _cubit.back();
          },
          child: Scaffold(
            appBar: BafoAppBar(
              title: l10n.authRegisterTitle,
              actions: const [LanguageSwitchButton()],
            ),
            body: SafeArea(
              child: Column(
                children: [
                  Expanded(
                    child: SingleChildScrollView(
                      controller: _scroll,
                      padding: BafoSpacing.pagePadding,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          StepperHeader(
                            current: step + 1,
                            total: RegisterState.stepCount,
                            title: titles[step],
                          ),
                          const SizedBox(height: BafoSpacing.xl),
                          IndexedStack(
                            index: step,
                            children: [
                              _accountStep(context, state),
                              _companyStep(context, state),
                              _addressStep(context, state),
                            ],
                          ),
                          const SizedBox(height: BafoSpacing.lg),
                          Wrap(
                            alignment: WrapAlignment.center,
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              Text(
                                l10n.authRegisterHaveAccount,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                              BafoButton.text(
                                label: l10n.authRegisterSignIn,
                                onPressed: () => context.go(AppRoutes.login),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  _actions(context, state),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _actions(BuildContext context, RegisterState state) {
    final l10n = context.l10n;
    final step = state.step;
    final last = step == RegisterState.stepCount - 1;
    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(
        BafoSpacing.page,
        BafoSpacing.sm,
        BafoSpacing.page,
        BafoSpacing.page,
      ),
      child: Row(
        children: [
          if (step > 0) ...[
            Expanded(
              child: BafoButton.outline(
                key: const Key('register.back'),
                label: l10n.commonActionsBack,
                onPressed: state.isSubmitting ? null : _cubit.back,
              ),
            ),
            const SizedBox(width: BafoSpacing.md),
          ],
          Expanded(
            flex: 2,
            child: last
                ? CooldownButton(
                    key: const Key('register.submit'),
                    label: l10n.authRegisterSubmit,
                    loading: state.isSubmitting,
                    availableAt: state.retryAt,
                    onPressed: _submit,
                  )
                : BafoButton(
                    key: const Key('register.next'),
                    label: l10n.commonActionsNext,
                    expand: true,
                    onPressed: () => _next(step),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _gap() => const SizedBox(height: BafoSpacing.lg);

  Widget _accountStep(BuildContext context, RegisterState state) {
    final l10n = context.l10n;
    return Form(
      key: _forms[0],
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            BafoTextField(
              key: const Key('register.name'),
              label: l10n.authFieldsNameLabel,
              controller: _name,
              required: true,
              errorText: state.fieldError('name'),
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.name],
              onChanged: (_) => _cubit.fieldEdited('name'),
              validator: (value) =>
                  Validators.required(value, l10n) ??
                  Validators.maxLength(value, 150, l10n),
            ),
            _gap(),
            BafoTextField(
              key: const Key('register.email'),
              label: l10n.authFieldsEmailLabel,
              hint: l10n.authFieldsEmailHint,
              controller: _email,
              required: true,
              errorText: state.fieldError('email'),
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              textDirection: TextDirection.ltr,
              autofillHints: const [AutofillHints.email],
              onChanged: (_) => _cubit.fieldEdited('email'),
              validator: (value) => Validators.email(value, l10n),
            ),
            if (state.fieldError('email') != null) _signInInstead(context),
            _gap(),
            PhoneField(
              key: const Key('register.phone'),
              controller: _phone,
              errorText: state.fieldError('phone'),
              textInputAction: TextInputAction.next,
              onChanged: (_) => _cubit.fieldEdited('phone'),
            ),
            _gap(),
            PasswordField(
              key: const Key('register.password'),
              controller: _password,
              showRules: true,
              errorText: state.fieldError('password'),
              autofillHints: const [AutofillHints.newPassword],
              textInputAction: TextInputAction.next,
              onChanged: (_) => _cubit.fieldEdited('password'),
              validator: (value) => Validators.password(value, l10n),
            ),
            _gap(),
            PasswordField(
              key: const Key('register.confirmation'),
              controller: _confirmation,
              label: l10n.authFieldsPasswordConfirmLabel,
              errorText: state.fieldError('password_confirmation'),
              autofillHints: const [AutofillHints.newPassword],
              textInputAction: TextInputAction.done,
              validator: (value) =>
                  Validators.passwordConfirmation(value, _password.text, l10n),
            ),
          ],
        ),
      ),
    );
  }

  Widget _signInInstead(BuildContext context) => Align(
    alignment: AlignmentDirectional.centerStart,
    child: BafoButton.text(
      label: context.l10n.authRegisterSignIn,
      onPressed: () => context.go(AppRoutes.login),
    ),
  );

  Widget _companyStep(BuildContext context, RegisterState state) {
    final l10n = context.l10n;
    final lookups = state.lookups;
    return Form(
      key: _forms[1],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          BafoTextField(
            key: const Key('register.company'),
            label: l10n.organizationFieldsNameLabel,
            controller: _company,
            required: true,
            errorText: state.fieldError('organization.name'),
            textInputAction: TextInputAction.next,
            autofillHints: const [AutofillHints.organizationName],
            onChanged: (_) => _cubit.fieldEdited('organization.name'),
            validator: (value) =>
                Validators.required(value, l10n) ??
                Validators.maxLength(value, 150, l10n),
          ),
          _gap(),
          DigitsField(
            key: const Key('register.cr'),
            label: l10n.organizationFieldsCrLabel,
            controller: _cr,
            maxLength: 10,
            required: true,
            helperText: l10n.organizationFieldsCrHelper,
            errorText: state.fieldError('organization.cr_number'),
            textInputAction: TextInputAction.next,
            onChanged: (_) => _cubit.fieldEdited('organization.cr_number'),
            validator: (value) => Validators.cr(value, l10n),
          ),
          if (state.fieldError('organization.cr_number') != null)
            _signInInstead(context),
          _gap(),
          if (lookups == null && state.lookupsError != null)
            ErrorState(
              error: state.lookupsError,
              onRetry: () => _cubit.loadLookups(refresh: true),
            )
          else if (lookups == null)
            const LoadingSkeleton(height: 56, radius: BafoRadii.input)
          else
            BafoDropdown<String>(
              key: const Key('register.region'),
              label: '${l10n.organizationFieldsRegionLabel} *',
              value: _regionId,
              errorText: state.fieldError('organization.region_id'),
              items: [
                for (final region in lookups.regions)
                  BafoDropdownItem(value: region.id, label: region.name),
              ],
              onChanged: (value) {
                setState(() => _regionId = value);
                _cubit.fieldEdited('organization.region_id');
              },
              validator: (value) =>
                  value == null ? l10n.validationRequired : null,
            ),
          _gap(),
          BafoTextField(
            key: const Key('register.city'),
            label: l10n.organizationFieldsCityLabel,
            controller: _city,
            required: true,
            errorText: state.fieldError('organization.city'),
            textInputAction: TextInputAction.next,
            onChanged: (_) => _cubit.fieldEdited('organization.city'),
            validator: (value) =>
                Validators.required(value, l10n) ??
                Validators.maxLength(value, 100, l10n),
          ),
          _gap(),
          SwitchListTile(
            key: const Key('register.vatRegistered'),
            contentPadding: EdgeInsetsDirectional.zero,
            title: Text(l10n.organizationFieldsVatRegisteredLabel),
            value: _vatRegistered,
            onChanged: (value) => setState(() => _vatRegistered = value),
          ),
          if (_vatRegistered) ...[
            DigitsField(
              key: const Key('register.vat'),
              label: l10n.organizationFieldsVatLabel,
              controller: _vat,
              maxLength: 15,
              required: true,
              helperText: l10n.organizationFieldsVatHelper,
              errorText: state.fieldError('organization.vat_number'),
              onChanged: (_) => _cubit.fieldEdited('organization.vat_number'),
              validator: (value) => Validators.vat(value, l10n),
            ),
            _gap(),
          ],
          BafoTextField(
            label: l10n.organizationFieldsLegalNameArLabel,
            controller: _legalAr,
            optional: true,
            errorText: state.fieldError('organization.legal_name_ar'),
            textInputAction: TextInputAction.next,
            validator: (value) => Validators.maxLength(value, 200, l10n),
          ),
          _gap(),
          BafoTextField(
            label: l10n.organizationFieldsLegalNameEnLabel,
            controller: _legalEn,
            optional: true,
            errorText: state.fieldError('organization.legal_name_en'),
            textDirection: TextDirection.ltr,
            textInputAction: TextInputAction.next,
            validator: (value) => Validators.maxLength(value, 200, l10n),
          ),
          _gap(),
          BafoTextField(
            label: l10n.organizationFieldsWebsiteLabel,
            hint: l10n.organizationFieldsWebsiteHint,
            controller: _website,
            optional: true,
            errorText: state.fieldError('organization.website'),
            keyboardType: TextInputType.url,
            textDirection: TextDirection.ltr,
            autofillHints: const [AutofillHints.url],
            onChanged: (_) => _cubit.fieldEdited('organization.website'),
            validator: (value) => Validators.httpsUrl(value, l10n),
          ),
        ],
      ),
    );
  }

  Widget _addressStep(BuildContext context, RegisterState state) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final categories = state.lookups?.categories ?? const [];
    final selectedNames = [
      for (final category in categories)
        if (_categoryIds.contains(category.id)) category.name,
    ];
    String? addressError(String part) =>
        state.fieldError('organization.national_address.$part');

    return Form(
      key: _forms[2],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.organizationAddressTitle, style: theme.textTheme.titleSmall),
          const SizedBox(height: BafoSpacing.xxs),
          Text(
            l10n.organizationAddressHelper,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          _gap(),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: DigitsField(
                  label: l10n.organizationAddressBuildingNumber,
                  controller: _building,
                  maxLength: 4,
                  optional: true,
                  errorText: addressError('building_number'),
                  validator: (value) => Validators.optionalDigits(value, 4, l10n),
                ),
              ),
              const SizedBox(width: BafoSpacing.md),
              Expanded(
                child: DigitsField(
                  label: l10n.organizationAddressAdditionalNumber,
                  controller: _additional,
                  maxLength: 4,
                  optional: true,
                  errorText: addressError('additional_number'),
                  validator: (value) => Validators.optionalDigits(value, 4, l10n),
                ),
              ),
            ],
          ),
          _gap(),
          BafoTextField(
            label: l10n.organizationAddressStreet,
            controller: _street,
            optional: true,
            errorText: addressError('street'),
            validator: (value) => Validators.maxLength(value, 150, l10n),
          ),
          _gap(),
          BafoTextField(
            label: l10n.organizationAddressDistrict,
            controller: _district,
            optional: true,
            errorText: addressError('district'),
            validator: (value) => Validators.maxLength(value, 150, l10n),
          ),
          _gap(),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: DigitsField(
                  label: l10n.organizationAddressPostalCode,
                  controller: _postal,
                  maxLength: 5,
                  optional: true,
                  errorText: addressError('postal_code'),
                  validator: (value) => Validators.optionalDigits(value, 5, l10n),
                ),
              ),
              const SizedBox(width: BafoSpacing.md),
              Expanded(
                child: BafoTextField(
                  label: l10n.organizationAddressShortAddress,
                  controller: _short,
                  optional: true,
                  hint: 'ABCD1234',
                  textDirection: TextDirection.ltr,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [UpperCaseFormatter()],
                  errorText: addressError('short_address'),
                  validator: (value) =>
                      Validators.optionalShortAddress(value, l10n),
                ),
              ),
            ],
          ),
          const SizedBox(height: BafoSpacing.xl),
          Text(
            l10n.organizationFieldsCategoriesLabel,
            style: theme.textTheme.titleSmall,
          ),
          const SizedBox(height: BafoSpacing.xxs),
          Text(
            l10n.organizationFieldsCategoriesHelper,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: BafoSpacing.sm),
          if (selectedNames.isNotEmpty)
            Wrap(
              spacing: BafoSpacing.xs,
              runSpacing: BafoSpacing.xs,
              children: [
                for (final name in selectedNames) StatusPill(label: name),
              ],
            ),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: BafoButton.outline(
              key: const Key('register.categories'),
              label: l10n.commonSelectedCount(_categoryIds.length),
              icon: Icons.category_outlined,
              onPressed: categories.isEmpty
                  ? null
                  : () async {
                      final picked = await showMultiSelectSheet<String>(
                        context,
                        title: l10n.organizationFieldsCategoriesLabel,
                        options: [
                          for (final category in categories)
                            SelectOption(value: category.id, label: category.name),
                        ],
                        selected: _categoryIds,
                        max: 20,
                        maxMessage: l10n.validationCategoriesMax,
                      );
                      if (picked != null) setState(() => _categoryIds = picked);
                    },
            ),
          ),
          if (state.fieldError('organization.category_ids') != null)
            Text(
              state.fieldError('organization.category_ids')!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.error,
              ),
            ),
          SwitchListTile(
            contentPadding: EdgeInsetsDirectional.zero,
            title: Text(l10n.organizationFieldsVisibleInSuggestions),
            value: _visibleInSuggestions,
            onChanged: (value) => setState(() => _visibleInSuggestions = value),
          ),
          const Divider(height: BafoSpacing.xl),
          ConsentCheckbox(
            key: const Key('register.terms'),
            value: _acceptTerms,
            label: l10n.authRegisterAcceptTerms,
            onChanged: (value) => setState(() => _acceptTerms = value),
            onRead: () => context.push(AppRoutes.legal(LegalCode.terms.wire)),
            errorText:
                state.fieldError('accept_terms') ??
                (_consentTried && !_acceptTerms
                    ? l10n.authRegisterConsentRequired
                    : null),
          ),
          ConsentCheckbox(
            key: const Key('register.privacy'),
            value: _acceptPrivacy,
            label: l10n.authRegisterAcceptPrivacy,
            onChanged: (value) => setState(() => _acceptPrivacy = value),
            onRead: () => context.push(AppRoutes.legal(LegalCode.privacy.wire)),
            errorText:
                state.fieldError('accept_privacy') ??
                (_consentTried && !_acceptPrivacy
                    ? l10n.authRegisterConsentRequired
                    : null),
          ),
        ],
      ),
    );
  }
}
