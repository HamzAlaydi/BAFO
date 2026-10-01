import 'package:bafo/core/api/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

/// Whether the API lets this build in (CONVENTIONS.md §5.1).
sealed class AppGateState extends Equatable {
  const AppGateState();

  @override
  List<Object?> get props => [];
}

/// Normal operation.
final class AppGateOpen extends AppGateState {
  const AppGateOpen();
}

/// 426 `app_version_unsupported`: this version is below the minimum.
final class AppGateUpdateRequired extends AppGateState {
  const AppGateUpdateRequired();
}

/// 503 `maintenance`. [message] is the server's localised text (may be empty).
final class AppGateMaintenance extends AppGateState {
  const AppGateMaintenance(this.message);

  final String message;

  @override
  List<Object?> get props => [message];
}

/// 403 `account_inactive` or `organization_suspended` on an authenticated
/// request (S8 "Account gate"): a full-page state with the support contacts
/// and Sign out. [message] is the server's localised text.
final class AppGateAccountBlocked extends AppGateState {
  const AppGateAccountBlocked({required this.code, this.message = ''});

  final String code;
  final String message;

  @override
  List<Object?> get props => [code, message];
}

/// Blocks the app on a force-update, maintenance or account-gate answer; the
/// router sends the user to the matching screen while the gate is closed.
class AppGateCubit extends Cubit<AppGateState> {
  AppGateCubit() : super(const AppGateOpen());

  void updateRequired() => emit(const AppGateUpdateRequired());

  void maintenance(String message) => emit(AppGateMaintenance(message));

  void accountBlocked(String code, String message) {
    // Update and maintenance gates win: they block everyone.
    if (state is AppGateOpen || state is AppGateAccountBlocked) {
      emit(AppGateAccountBlocked(code: code, message: message));
    }
  }

  /// Lets the user try again; the next blocked response closes it again.
  void reopen() => emit(const AppGateOpen());
}

/// Feeds 426, 503 `maintenance` and the 403 account gate into
/// [AppGateCubit]. The account gate only counts on requests that carried a
/// token (a login answering `account_inactive` is a form error instead).
class AppGateInterceptor extends Interceptor {
  AppGateInterceptor(this._gate);

  final AppGateCubit _gate;

  static const Set<String> _accountGateCodes = {
    'account_inactive',
    'organization_suspended',
  };

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final response = err.response;
    if (response != null) {
      final error = ApiException.fromResponse(
        statusCode: response.statusCode,
        body: response.data,
      );
      if (response.statusCode == 426 ||
          error.code == ApiErrorCode.appVersionUnsupported) {
        _gate.updateRequired();
      } else if (response.statusCode == 503 &&
          error.code == ApiErrorCode.maintenance) {
        _gate.maintenance(error.message);
      } else if (response.statusCode == 403 &&
          _accountGateCodes.contains(error.code) &&
          err.requestOptions.headers.containsKey('Authorization')) {
        _gate.accountBlocked(error.code, error.message);
      }
    }
    handler.next(err);
  }
}
