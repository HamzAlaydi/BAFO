import 'dart:async';

import 'package:bafo/core/l10n/l10n.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/me.dart';
import 'package:bafo/core/session/session_cubit.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/features/account/presentation/account_widgets.dart';
import 'package:bafo/features/account/presentation/organization/organization_cubit.dart';
import 'package:bafo/features/account/presentation/organization/organization_form.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/profile/data/profile_repositories.dart';
import 'package:bafo/widgets/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// M55 Organisation details (W27 fields). Everyone reads them; users with
/// `organization.update` edit them and the logo. The billing-profile banner
/// lists what invoicing still needs, with no purchase action. The profile
/// PDF is downloadable here and uploaded on the web.
class OrganizationScreen extends StatelessWidget {
  const OrganizationScreen({
    this.pickImage = pickImageFromDevice,
    this.startEditing = false,
    super.key,
  });

  final ImagePick pickImage;

  /// Open straight in the form (the home billing-profile alert).
  final bool startEditing;

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (context) => OrganizationCubit(
      organizations: context.read<OrganizationRepository>(),
      lookups: context.read<LookupsRepository>(),
      session: context.read<SessionCubit>(),
    )..load(),
    child: _OrganizationView(pickImage: pickImage, startEditing: startEditing),
  );
}

class _OrganizationView extends StatefulWidget {
  const _OrganizationView({
    required this.pickImage,
    required this.startEditing,
  });

  final ImagePick pickImage;
  final bool startEditing;

  @override
  State<_OrganizationView> createState() => _OrganizationViewState();
}

class _OrganizationViewState extends State<_OrganizationView> {
  late bool _editing = widget.startEditing;

  Future<void> _changeLogo(OrganizationLoaded state) async {
    final l10n = context.l10n;
    final cubit = context.read<OrganizationCubit>();
    final choice = await showImageSheet(
      context,
      title: l10n.accountOrganizationLogoChange,
      canRemove: (state.organization.logoUrl ?? '').isNotEmpty,
    );
    switch (choice) {
      case ImageSheetChoice.pick:
        final path = await widget.pickImage();
        if (path != null) await cubit.uploadLogo(path);
      case ImageSheetChoice.remove:
        await cubit.removeLogo();
      case null:
        break;
    }
  }

  void _onState(BuildContext context, OrganizationState state) {
    if (state is! OrganizationLoaded) return;
    final l10n = context.l10n;
    switch (state.outcome?.kind) {
      case OrganizationResult.saved:
        setState(() => _editing = false);
        BafoToast.success(context, l10n.accountSaved);
      case OrganizationResult.logoUpdated:
        BafoToast.success(context, l10n.accountOrganizationLogoUpdated);
      case OrganizationResult.logoRemoved:
        BafoToast.success(context, l10n.accountOrganizationLogoRemoved);
      case null:
        // A logo failure has no form to show it in.
        if (!_editing && state.error != null) {
          BafoToast.error(context, errorMessage(l10n, state.error));
        }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final canEdit = context.select<SessionCubit, bool>(
      (cubit) => cubit.state.me?.can(Permissions.organizationUpdate) ?? false,
    );
    return BlocConsumer<OrganizationCubit, OrganizationState>(
      listenWhen: (previous, current) =>
          current is OrganizationLoaded &&
          (previous is! OrganizationLoaded ||
              previous.outcome != current.outcome ||
              previous.error != current.error),
      listener: _onState,
      builder: (context, state) {
        final cubit = context.read<OrganizationCubit>();
        final loaded = state is OrganizationLoaded ? state : null;
        return Scaffold(
          appBar: BafoAppBar(
            title: l10n.accountOrganizationTitle,
            actions: [
              if (canEdit && loaded != null && !_editing)
                IconButton(
                  key: const Key('organization.edit'),
                  tooltip: l10n.accountOrganizationEdit,
                  icon: const Icon(Icons.edit_outlined),
                  onPressed: () => setState(() => _editing = true),
                ),
            ],
          ),
          body: switch (state) {
            OrganizationLoading() => const LoadingSkeletonList(itemCount: 3),
            OrganizationFailure(:final error) => ErrorState(
              error: error,
              onRetry: cubit.load,
            ),
            OrganizationLoaded() => RefreshIndicator(
              onRefresh: cubit.load,
              child: ListView(
                key: const Key('organization.list'),
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsetsDirectional.fromSTEB(
                  BafoSpacing.page,
                  BafoSpacing.lg,
                  BafoSpacing.page,
                  BafoSpacing.xxl,
                ),
                children: [
                  _Header(
                    state: state,
                    canEdit: canEdit,
                    onChangeLogo: () => unawaited(_changeLogo(state)),
                  ),
                  if (!state.organization.billingProfileComplete) ...[
                    const SizedBox(height: BafoSpacing.md),
                    _BillingProfileBanner(
                      missing: state.organization.billingProfileMissing,
                      onComplete: canEdit && !_editing
                          ? () => setState(() => _editing = true)
                          : null,
                    ),
                  ],
                  if (!canEdit) ...[
                    const SizedBox(height: BafoSpacing.md),
                    InfoNotice(
                      tone: StatusTone.neutral,
                      icon: Icons.lock_outline_rounded,
                      message: l10n.accountOrganizationReadOnly,
                    ),
                  ],
                  if (_editing && canEdit)
                    OrganizationForm(
                      state: state,
                      onSave: (update) => unawaited(cubit.save(update)),
                      onCancel: () => setState(() => _editing = false),
                      onFieldEdited: cubit.fieldEdited,
                      onRetryLookups: () =>
                          unawaited(cubit.loadLookups(refresh: true)),
                    )
                  else
                    _Details(state: state),
                ],
              ),
            ),
          },
        );
      },
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({
    required this.state,
    required this.canEdit,
    required this.onChangeLogo,
  });

  final OrganizationLoaded state;
  final bool canEdit;
  final VoidCallback onChangeLogo;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final organization = state.organization;
    final logoBusy =
        state.busy == OrganizationBusy.uploadingLogo ||
        state.busy == OrganizationBusy.removingLogo;
    return BafoCard(
      child: Column(
        children: [
          Stack(
            alignment: AlignmentDirectional.center,
            children: [
              OrgAvatar(
                name: organization.name,
                logoUrl: organization.logoUrl,
                size: 72,
                decorative: true,
              ),
              if (logoBusy)
                const SizedBox.square(
                  dimension: 72,
                  child: CircularProgressIndicator(strokeWidth: 3),
                ),
            ],
          ),
          const SizedBox(height: BafoSpacing.md),
          Text(
            organization.name,
            textAlign: TextAlign.center,
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: BafoSpacing.xxs),
          // Codes are never truncated (S1): a wrapping line, not a pill.
          Text(
            '${l10n.organizationFieldsCrLabel}: '
            '${ltrIsolate(organization.crNumber)}',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          if (organization.verified) ...[
            const SizedBox(height: BafoSpacing.xs),
            StatusPill(
              label: l10n.accountVerified,
              tone: StatusTone.success,
              icon: Icons.verified_outlined,
            ),
          ],
          if (canEdit) ...[
            const SizedBox(height: BafoSpacing.sm),
            BafoButton.text(
              key: const Key('organization.logo'),
              label: l10n.accountOrganizationLogoChange,
              icon: Icons.image_outlined,
              onPressed: state.isBusy ? null : onChangeLogo,
            ),
            Text(
              l10n.accountOrganizationLogoHint,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// `billing_profile_complete = false`: what invoicing still needs. No
/// purchase action; editors can open the form.
class _BillingProfileBanner extends StatelessWidget {
  const _BillingProfileBanner({required this.missing, this.onComplete});

  final List<String> missing;
  final VoidCallback? onComplete;

  static String labelOf(AppLocalizations l10n, String path) => switch (path) {
    'name' => l10n.organizationFieldsNameLabel,
    'legal_name_ar' => l10n.organizationFieldsLegalNameArLabel,
    'legal_name_en' => l10n.organizationFieldsLegalNameEnLabel,
    'vat_number' || 'vat_registered' => l10n.organizationFieldsVatLabel,
    'region_id' || 'region' => l10n.organizationFieldsRegionLabel,
    'city' => l10n.organizationFieldsCityLabel,
    'national_address' => l10n.organizationAddressTitle,
    'national_address.building_number' =>
      l10n.organizationAddressBuildingNumber,
    'national_address.street' => l10n.organizationAddressStreet,
    'national_address.district' => l10n.organizationAddressDistrict,
    'national_address.postal_code' => l10n.organizationAddressPostalCode,
    'national_address.additional_number' =>
      l10n.organizationAddressAdditionalNumber,
    'national_address.short_address' => l10n.organizationAddressShortAddress,
    _ => l10n.accountOrganizationOtherFields,
  };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final separator = context.languageCode == 'ar' ? '، ' : ', ';
    final labels = {for (final path in missing) labelOf(l10n, path)};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        InfoNotice(
          key: const Key('organization.billingBanner'),
          tone: StatusTone.warning,
          icon: Icons.receipt_long_outlined,
          message: l10n.accountOrganizationBillingIncomplete(
            labels.join(separator),
          ),
        ),
        if (onComplete != null)
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: BafoButton.text(
              key: const Key('organization.complete'),
              label: l10n.accountOrganizationComplete,
              onPressed: onComplete,
            ),
          ),
      ],
    );
  }
}

class _Details extends StatelessWidget {
  const _Details({required this.state});

  final OrganizationLoaded state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final theme = Theme.of(context);
    final organization = state.organization;
    final address = organization.nationalAddress;
    final notSet = l10n.accountNotSet;
    String orNotSet(String? value) =>
        value == null || value.trim().isEmpty ? notSet : value;
    bool isSet(String? value) => value != null && value.trim().isNotEmpty;
    final features = organization.features;

    Widget card(List<KeyValue> items) =>
        BafoCard(child: KeyValueList(items: items));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FormSectionTitle(l10n.accountOrganizationIdentity),
        card([
          KeyValue(l10n.organizationFieldsNameLabel, organization.name),
          KeyValue(
            l10n.organizationFieldsLegalNameArLabel,
            orNotSet(organization.legalNameAr),
          ),
          KeyValue(
            l10n.organizationFieldsLegalNameEnLabel,
            orNotSet(organization.legalNameEn),
            ltr: isSet(organization.legalNameEn),
          ),
          KeyValue(
            l10n.organizationFieldsCrLabel,
            organization.crNumber,
            ltr: true,
          ),
        ]),
        FormSectionTitle(l10n.accountOrganizationTax),
        card([
          KeyValue(
            l10n.organizationFieldsVatRegisteredLabel,
            organization.vatRegistered
                ? l10n.accountOrganizationVatRegistered
                : l10n.accountOrganizationVatNotRegistered,
          ),
          if (organization.vatRegistered)
            KeyValue(
              l10n.organizationFieldsVatLabel,
              orNotSet(organization.vatNumber),
              ltr: isSet(organization.vatNumber),
            ),
        ]),
        FormSectionTitle(l10n.accountOrganizationLocation),
        card([
          KeyValue(
            l10n.organizationFieldsRegionLabel,
            orNotSet(organization.region?.name),
          ),
          KeyValue(
            l10n.organizationFieldsCityLabel,
            orNotSet(organization.city),
          ),
          KeyValue(
            l10n.organizationAddressBuildingNumber,
            orNotSet(address.buildingNumber),
            ltr: isSet(address.buildingNumber),
          ),
          KeyValue(l10n.organizationAddressStreet, orNotSet(address.street)),
          KeyValue(
            l10n.organizationAddressDistrict,
            orNotSet(address.district),
          ),
          KeyValue(
            l10n.organizationAddressPostalCode,
            orNotSet(address.postalCode),
            ltr: isSet(address.postalCode),
          ),
          KeyValue(
            l10n.organizationAddressAdditionalNumber,
            orNotSet(address.additionalNumber),
            ltr: isSet(address.additionalNumber),
          ),
          KeyValue(
            l10n.organizationAddressShortAddress,
            orNotSet(address.shortAddress),
            ltr: isSet(address.shortAddress),
          ),
        ]),
        FormSectionTitle(l10n.accountOrganizationContact),
        card([
          KeyValue(
            l10n.organizationFieldsWebsiteLabel,
            orNotSet(organization.website),
            ltr: isSet(organization.website),
          ),
          KeyValue(
            l10n.accountOrganizationEmail,
            orNotSet(organization.email),
            ltr: isSet(organization.email),
          ),
          KeyValue(
            l10n.accountOrganizationPhone,
            orNotSet(organization.phone),
            ltr: isSet(organization.phone),
          ),
        ]),
        FormSectionTitle(l10n.accountOrganizationActivity),
        BafoCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.organizationFieldsCategoriesLabel,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: BafoSpacing.sm),
              if (organization.categories.isEmpty)
                Text(notSet, style: theme.textTheme.bodyMedium)
              else
                Wrap(
                  spacing: BafoSpacing.xs,
                  runSpacing: BafoSpacing.xs,
                  children: [
                    for (final category in organization.categories)
                      StatusPill(label: category.name),
                  ],
                ),
              const Divider(height: BafoSpacing.xl),
              Row(
                children: [
                  Icon(
                    organization.visibleInSuggestions
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                    size: 20,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                  const SizedBox(width: BafoSpacing.sm),
                  Expanded(
                    child: Text(
                      organization.visibleInSuggestions
                          ? l10n.accountOrganizationSuggestionsOn
                          : l10n.accountOrganizationSuggestionsOff,
                      style: theme.textTheme.bodyMedium,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        FormSectionTitle(l10n.accountOrganizationFeatures),
        BafoCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final (label, on) in [
                (
                  l10n.accountOrganizationFeatureAuction,
                  features.auctionEnabled,
                ),
                (
                  l10n.accountOrganizationFeatureSponsorship,
                  features.sponsorshipEnabled,
                ),
                (l10n.accountOrganizationFeatureApi, features.apiEnabled),
              ])
                Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                    vertical: BafoSpacing.xs,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(label, style: theme.textTheme.bodyMedium),
                      ),
                      StatusPill(
                        label: on
                            ? l10n.accountOrganizationFeatureOn
                            : l10n.accountOrganizationFeatureOff,
                        tone: on ? StatusTone.success : StatusTone.neutral,
                        icon: on
                            ? Icons.check_circle_outline_rounded
                            : Icons.remove_circle_outline_rounded,
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: BafoSpacing.xs),
              Text(
                l10n.accountOrganizationFeaturesNote,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
        FormSectionTitle(l10n.accountOrganizationProfileDocument),
        BafoCard(
          padding: const EdgeInsetsDirectional.all(BafoSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (organization.profileDocument case final document?)
                AttachmentTile(
                  attachment: Attachment(
                    id: document.id,
                    kind: AttachmentKind.document,
                    title: document.name,
                    file: document,
                  ),
                )
              else
                Padding(
                  padding: const EdgeInsetsDirectional.all(BafoSpacing.sm),
                  child: Text(
                    l10n.accountOrganizationProfileDocumentNone,
                    style: theme.textTheme.bodyMedium,
                  ),
                ),
              Padding(
                padding: const EdgeInsetsDirectional.all(BafoSpacing.sm),
                child: Text(
                  l10n.accountOrganizationProfileDocumentWeb,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
