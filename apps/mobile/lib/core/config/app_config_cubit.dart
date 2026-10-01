import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/app_gate.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/config/app_config_repository.dart';
import 'package:bafo/core/config/env.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class AppConfigState extends Equatable {
  const AppConfigState(this.config);

  /// The best known config (fresh, cached or null).
  final AppConfig? config;

  @override
  List<Object?> get props => [config];
}

final class AppConfigInitial extends AppConfigState {
  const AppConfigInitial([super.config]);
}

final class AppConfigLoading extends AppConfigState {
  const AppConfigLoading([super.config]);
}

final class AppConfigLoaded extends AppConfigState {
  const AppConfigLoaded(AppConfig super.config);

  @override
  AppConfig get config => super.config!;
}

/// The fetch failed; [config] is the cached copy, if any.
final class AppConfigFailure extends AppConfigState {
  const AppConfigFailure(this.error, [super.config]);

  final ApiException error;

  @override
  List<Object?> get props => [error, config];
}

/// Loads `GET /app-config` at start-up and on resume, and applies it:
/// * realtime settings go to the [RealtimeClient] (ARCHITECTURE.md §9.1);
/// * `min_version` above this build closes the gate as update-required (M03);
/// * `maintenance.enabled` closes it as maintenance (M04), and a config
///   without maintenance reopens a maintenance gate.
class AppConfigCubit extends Cubit<AppConfigState> {
  AppConfigCubit({
    required this._repository,
    required this._gate,
    required this._realtime,
    required this._env,
    required this._client,
  }) : super(AppConfigInitial(_repository.current)) {
    final cached = _repository.current;
    if (cached != null) _realtime.configure(_env.resolveRealtime(cached.realtime));
  }

  final AppConfigRepository _repository;
  final AppGateCubit _gate;
  final RealtimeClient _realtime;
  final Env _env;
  final ClientInfo _client;

  Future<void> load() async {
    if (state is AppConfigLoading) return;
    emit(AppConfigLoading(state.config));
    try {
      final config = await _repository.fetch();
      _apply(config);
      emit(AppConfigLoaded(config));
    } on ApiException catch (error) {
      emit(AppConfigFailure(error, _repository.current));
    }
  }

  /// "Retry" on the maintenance screen: re-reads the config, which reopens
  /// the gate when maintenance is over. Without an answer the gate reopens
  /// and the next blocked API response closes it again.
  Future<void> retryGate() async {
    await load();
    if (state is AppConfigFailure && _gate.state is AppGateMaintenance) {
      _gate.reopen();
    }
  }

  void _apply(AppConfig config) {
    _realtime.configure(_env.resolveRealtime(config.realtime));
    if (config.requiresUpdate(_client.platform, _client.appVersion)) {
      _gate.updateRequired();
    } else if (config.maintenanceEnabled) {
      _gate.maintenance(config.maintenanceMessage);
    } else if (_gate.state is AppGateMaintenance) {
      _gate.reopen();
    }
  }
}
