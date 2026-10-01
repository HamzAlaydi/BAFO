import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';

/// Read-only billing (entitlement-only app, SCREENS.md CD6). There is no
/// checkout, trial, coupon or plan call here on purpose: the app never sends
/// a request that could return `purchase_not_available_on_platform`.
abstract interface class BillingRepository {
  /// `GET /billing/subscription` (`billing.view`). Without the permission,
  /// show `me.subscription` instead.
  Future<SubscriptionOverview> subscription();

  /// `GET /billing/invoices` (`billing.view`), newest first.
  Future<Paged<InvoiceSummary>> invoices({
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  });
}

final class ApiBillingRepository implements BillingRepository {
  ApiBillingRepository(this._api);

  final ApiClient _api;

  @override
  Future<SubscriptionOverview> subscription() async =>
      SubscriptionOverview.fromJson(
        (await _api.get('billing/subscription')).dataMap,
      );

  @override
  Future<Paged<InvoiceSummary>> invoices({
    int page = 1,
    int perPage = PageMeta.defaultPerPage,
  }) async => Paged.fromResponse(
    await _api.get('billing/invoices', query: pageQuery(page, perPage)),
    InvoiceSummary.fromJson,
  );
}
