import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/billing/data/billing_repository.dart';
import 'package:bafo/features/billing/domain/billing_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class InvoicesState extends Equatable {
  const InvoicesState();

  @override
  List<Object?> get props => [];
}

final class InvoicesLoading extends InvoicesState {
  const InvoicesLoading();
}

/// No `billing.view` (from `me.permissions`, or a 403).
final class InvoicesForbidden extends InvoicesState {
  const InvoicesForbidden();
}

final class InvoicesFailure extends InvoicesState {
  const InvoicesFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

final class InvoicesLoaded extends InvoicesState {
  const InvoicesLoaded({
    required this.invoices,
    required this.page,
    required this.hasMore,
    this.loadingMore = false,
    this.pageError,
  });

  final List<InvoiceSummary> invoices;
  final int page;
  final bool hasMore;
  final bool loadingMore;
  final ApiException? pageError;

  @override
  List<Object?> get props => [invoices, page, hasMore, loadingMore, pageError];
}

/// The organisation's invoices, read-only (`GET /billing/invoices`,
/// `billing.view`): number, date, type and e-invoice status. No amounts and
/// no PDF: both are on the web (`billing.invoices_on_web`, CD6).
class InvoicesCubit extends Cubit<InvoicesState> {
  InvoicesCubit({required this._billing, required this._canView})
    : super(const InvoicesLoading());

  final BillingRepository _billing;
  final bool _canView;

  Future<void> load() async {
    if (!_canView) {
      emit(const InvoicesForbidden());
      return;
    }
    // A pull to refresh keeps the rows on screen until the answer.
    if (state is InvoicesFailure) emit(const InvoicesLoading());
    try {
      final page = await _billing.invoices();
      emit(
        InvoicesLoaded(
          invoices: page.items,
          page: page.meta.currentPage,
          hasMore: page.hasMore,
        ),
      );
    } on ApiException catch (error) {
      emit(
        error.statusCode == 403
            ? const InvoicesForbidden()
            : InvoicesFailure(error),
      );
    }
  }

  Future<void> loadMore() async {
    final current = state;
    if (current is! InvoicesLoaded || !current.hasMore || current.loadingMore) {
      return;
    }
    emit(
      InvoicesLoaded(
        invoices: current.invoices,
        page: current.page,
        hasMore: current.hasMore,
        loadingMore: true,
      ),
    );
    try {
      final next = await _billing.invoices(page: current.page + 1);
      final known = {for (final invoice in current.invoices) invoice.id};
      emit(
        InvoicesLoaded(
          invoices: [
            ...current.invoices,
            ...next.items.where((invoice) => !known.contains(invoice.id)),
          ],
          page: next.meta.currentPage,
          hasMore: next.hasMore,
        ),
      );
    } on ApiException catch (error) {
      emit(
        InvoicesLoaded(
          invoices: current.invoices,
          page: current.page,
          hasMore: current.hasMore,
          pageError: error,
        ),
      );
    }
  }

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(InvoicesState state) {
    if (!isClosed) super.emit(state);
  }
}
