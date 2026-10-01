import 'dart:async';
import 'dart:convert';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/utils/digits.dart';
import 'package:bafo/core/utils/validators.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/organization/organization_cubit.dart';
import 'package:bafo/features/profile/domain/profile_models.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:material_ui/material_ui.dart';

/// The organisation edit form (M55, fields as W27): every register field
/// except the CR number, which is immutable. The e-mail, phone and profile
/// document are not edited here.
class OrganizationForm extends StatefulWidget {
  const OrganizationForm({
    required this.state,
    required this.onSave,
    required this.onCancel,
    required this.onFieldEdited,
    required this.onRetryLookups,
    super.key,
  });

  final OrganizationLoaded state;
  final ValueChanged<OrganizationUpdate> onSave;
  final VoidCallback onCancel;
  final ValueChanged<String> onFieldEdited;
  final VoidCallback onRetryLookups;

  @override
  State<OrganizationForm> createState() => _OrganizationFormState();
}

class _OrganizationFormState extends State<OrganizationForm> {
  final _form = GlobalKey<FormState>();
  late final OrganizationUpdate _initial = OrganizationUpdate.from(
    widget.state.organization,
  );
  late final _name = TextEditingController(text: _initial.name);
  late final _legalAr = TextEditingController(text: _initial.legalNameAr);
  late final _legalEn = TextEditingController(text: _initial.legalNameEn);
  late final _vat = TextEditingController(text: _initial.vatNumber);
  late final _city = TextEditingController(text: _initial.city);
  late final _website = TextEditingController(text: _initial.website);
  late final _building = TextEditingController(
    text: _initial.nationalAddress.buildingNumber,
  );
  late final _street = TextEditingController(
    text: _initial.nationalAddress.street,
  );
  late final _district = TextEditingController(
    text: _initial.nationalAddress.district,
  );
  late final _postal = TextEditingController(
    text: _initial.nationalAddress.postalCode,
  );
  late final _additional = TextEditingController(
    text: _initial.nationalAddress.additionalNumber,
  );
  late final _short = TextEditingController(
    text: _initial.nationalAddress.shortAddress,
  );
  late bool _vatRegistered = _initial.vatRegistered;
  late String? _regionId = _initial.regionId.isEmpty ? null : _initial.regionId;
  late Set<String> _categoryIds = {..._initial.categoryIds};
  late bool _visibleInSuggestions = _initial.visibleInSuggestions;

  List<TextEditingController> get _controllers => [
    _name,
    _legalAr,
    _legalEn,
    _vat,
    _city,
    _website,
    _building,
    _street,
    _district,
    _postal,
    _additional,
    _short,
  ];

  /// The form as it would be sent.
  OrganizationUpdate get value {
    String? text(TextEditingController controller) => controller.text;
    String? digits(TextEditingController controller) =>
        normalizeDigits(controller.text.trim());
    return OrganizationUpdate(
      name: _name.text,
      regionId: _regionId ?? '',
      city: _city.text,
      vatRegistered: _vatRegistered,
      vatNumber: digits(_vat),
      legalNameAr: text(_legalAr),
      legalNameEn: text(_legalEn),
      website: text(_website),
      nationalAddress: NationalAddress(
        buildingNumber: digits(_building),
        street: text(_street),
        district: text(_district),
        postalCode: digits(_postal),
        additionalNumber: digits(_additional),
        shortAddress: normalizeDigits(_short.text.trim().toUpperCase()),
      ),
      categoryIds: _categoryIds.toList(),
      visibleInSuggestions: _visibleInSuggestions,
    );
  }

  /// Unsaved changes. The JSON sent is what matters (blanks equal nulls,
  /// the category order does not).
  bool get dirty {
    String canonical(OrganizationUpdate update) {
      final json = update.toJson();
      json['category_ids'] = [...update.categoryIds]..sort();
      return jsonEncode(json);
    }

    return canonical(value) != canonical(_initial);
  }

  Future<void> _cancel() async {
    if (dirty) {
      final l10n = context.l10n;
      final discard = await showConfirmDialog(
        context,
        title: l10n.accountDiscardTitle,
        message: l10n.accountDiscardMessage,
        confirmLabel: l10n.accountDiscardAction,
        destructive: true,
      );
      if (!discard) return;
    }
    widget.onCancel();
  }

  @override
  void dispose() {
    for (final controller in _controllers) {
      controller.dispose();
    }
    super.dispose();
  }

  void _submit() {
    if (!(_form.currentState?.validate() ?? false)) return;
    widget.onSave(value);
  }

  Widget _gap() => const SizedBox(height: BafoSpacing.lg);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final state = widget.state;
    final lookups = state.lookups;
    final saving = state.busy == OrganizationBusy.saving;
    String? error(String path) => state.fieldError(path);
    void edited(String path) => widget.onFieldEdited(path);
    final categories = lookups?.categories ?? const <Category>[];
    final selectedNames = [
      for (final category in categories)
        if (_categoryIds.contains(category.id)) category.name,
    ];

    return UnsavedChangesGuard(
      dirty: dirty && !saving,
      child: Form(
        key: _form,
        onChanged: () => setState(() {}),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (state.error != null) ...[
              InfoNotice(
                tone: StatusTone.danger,
                icon: Icons.error_outline_rounded,
                message: errorMessage(l10n, state.error),
              ),
              _gap(),
            ],
            FormSectionTitle(l10n.accountOrganizationIdentity),
            BafoTextField(
              key: const Key('organization.name'),
              label: l10n.organizationFieldsNameLabel,
              controller: _name,
              required: true,
              errorText: error('name'),
              onChanged: (_) => edited('name'),
              validator: (value) =>
                  Validators.required(value, l10n) ??
                  Validators.maxLength(value, 150, l10n),
            ),
            _gap(),
            BafoTextField(
              key: const Key('organization.legalAr'),
              label: l10n.organizationFieldsLegalNameArLabel,
              controller: _legalAr,
              optional: true,
              errorText: error('legal_name_ar'),
              onChanged: (_) => edited('legal_name_ar'),
              validator: (value) => Validators.maxLength(value, 200, l10n),
            ),
            _gap(),
            BafoTextField(
              key: const Key('organization.legalEn'),
              label: l10n.organizationFieldsLegalNameEnLabel,
              controller: _legalEn,
              optional: true,
              textDirection: TextDirection.ltr,
              errorText: error('legal_name_en'),
              onChanged: (_) => edited('legal_name_en'),
              validator: (value) => Validators.maxLength(value, 200, l10n),
            ),
            _gap(),
            KeyValueList(
              items: [
                KeyValue(
                  l10n.organizationFieldsCrLabel,
                  state.organization.crNumber,
                  ltr: true,
                ),
              ],
            ),
            const SizedBox(height: BafoSpacing.xs),
            Text(
              l10n.accountOrganizationCrReadOnly,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            FormSectionTitle(l10n.accountOrganizationTax),
            SwitchListTile(
              key: const Key('organization.vatRegistered'),
              contentPadding: EdgeInsetsDirectional.zero,
              title: Text(l10n.organizationFieldsVatRegisteredLabel),
              value: _vatRegistered,
              onChanged: (value) => setState(() => _vatRegistered = value),
            ),
            if (_vatRegistered)
              DigitsField(
                key: const Key('organization.vat'),
                label: l10n.organizationFieldsVatLabel,
                controller: _vat,
                maxLength: 15,
                required: true,
                helperText: l10n.organizationFieldsVatHelper,
                errorText: error('vat_number'),
                onChanged: (_) => edited('vat_number'),
                validator: (value) => Validators.vat(value, l10n),
              ),
            FormSectionTitle(l10n.accountOrganizationLocation),
            if (lookups == null && state.lookupsError != null)
              ErrorState(
                error: state.lookupsError,
                onRetry: widget.onRetryLookups,
              )
            else if (lookups == null)
              const LoadingSkeleton(height: 56, radius: BafoRadii.input)
            else
              BafoDropdown<String>(
                key: const Key('organization.region'),
                label: '${l10n.organizationFieldsRegionLabel} *',
                value: lookups.regionById(_regionId ?? '') == null
                    ? null
                    : _regionId,
                errorText: error('region_id'),
                items: [
                  for (final region in lookups.regions)
                    BafoDropdownItem(value: region.id, label: region.name),
                ],
                onChanged: (value) {
                  setState(() => _regionId = value);
                  edited('region_id');
                },
                validator: (value) =>
                    value == null ? l10n.validationRequired : null,
              ),
            _gap(),
            BafoTextField(
              key: const Key('organization.city'),
              label: l10n.organizationFieldsCityLabel,
              controller: _city,
              required: true,
              errorText: error('city'),
              onChanged: (_) => edited('city'),
              validator: (value) =>
                  Validators.required(value, l10n) ??
                  Validators.maxLength(value, 100, l10n),
            ),
            _gap(),
            Text(
              l10n.organizationAddressTitle,
              style: theme.textTheme.titleSmall,
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
                    errorText: error('national_address.building_number'),
                    onChanged: (_) =>
                        edited('national_address.building_number'),
                    validator: (value) =>
                        Validators.optionalDigits(value, 4, l10n),
                  ),
                ),
                const SizedBox(width: BafoSpacing.md),
                Expanded(
                  child: DigitsField(
                    label: l10n.organizationAddressAdditionalNumber,
                    controller: _additional,
                    maxLength: 4,
                    optional: true,
                    errorText: error('national_address.additional_number'),
                    onChanged: (_) =>
                        edited('national_address.additional_number'),
                    validator: (value) =>
                        Validators.optionalDigits(value, 4, l10n),
                  ),
                ),
              ],
            ),
            _gap(),
            BafoTextField(
              label: l10n.organizationAddressStreet,
              controller: _street,
              optional: true,
              errorText: error('national_address.street'),
              onChanged: (_) => edited('national_address.street'),
              validator: (value) => Validators.maxLength(value, 150, l10n),
            ),
            _gap(),
            BafoTextField(
              label: l10n.organizationAddressDistrict,
              controller: _district,
              optional: true,
              errorText: error('national_address.district'),
              onChanged: (_) => edited('national_address.district'),
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
                    errorText: error('national_address.postal_code'),
                    onChanged: (_) => edited('national_address.postal_code'),
                    validator: (value) =>
                        Validators.optionalDigits(value, 5, l10n),
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
                    errorText: error('national_address.short_address'),
                    onChanged: (_) => edited('national_address.short_address'),
                    validator: (value) =>
                        Validators.optionalShortAddress(value, l10n),
                  ),
                ),
              ],
            ),
            FormSectionTitle(l10n.accountOrganizationContact),
            BafoTextField(
              key: const Key('organization.website'),
              label: l10n.organizationFieldsWebsiteLabel,
              hint: l10n.organizationFieldsWebsiteHint,
              controller: _website,
              optional: true,
              keyboardType: TextInputType.url,
              textDirection: TextDirection.ltr,
              errorText: error('website'),
              onChanged: (_) => edited('website'),
              validator: (value) => Validators.httpsUrl(value, l10n),
            ),
            FormSectionTitle(l10n.accountOrganizationActivity),
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
            const SizedBox(height: BafoSpacing.sm),
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: BafoButton.outline(
                key: const Key('organization.categories'),
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
                              SelectOption(
                                value: category.id,
                                label: category.name,
                              ),
                          ],
                          selected: _categoryIds,
                          max: 20,
                          maxMessage: l10n.validationCategoriesMax,
                        );
                        if (picked != null) {
                          setState(() => _categoryIds = picked);
                          edited('category_ids');
                        }
                      },
              ),
            ),
            if (error('category_ids') != null)
              Padding(
                padding: const EdgeInsetsDirectional.only(top: BafoSpacing.xs),
                child: Text(
                  error('category_ids')!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.error,
                  ),
                ),
              ),
            SwitchListTile(
              key: const Key('organization.suggestions'),
              contentPadding: EdgeInsetsDirectional.zero,
              title: Text(l10n.organizationFieldsVisibleInSuggestions),
              value: _visibleInSuggestions,
              onChanged: (value) =>
                  setState(() => _visibleInSuggestions = value),
            ),
            const SizedBox(height: BafoSpacing.xl),
            BafoButton(
              key: const Key('organization.save'),
              label: l10n.commonActionsSave,
              expand: true,
              loading: saving,
              onPressed: saving || !dirty ? null : _submit,
            ),
            const SizedBox(height: BafoSpacing.sm),
            BafoButton.text(
              key: const Key('organization.cancel'),
              label: l10n.commonActionsCancel,
              expand: true,
              onPressed: saving ? null : () => unawaited(_cancel()),
            ),
          ],
        ),
      ),
    );
  }
}
