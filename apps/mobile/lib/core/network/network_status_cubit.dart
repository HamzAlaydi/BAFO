import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/realtime/realtime_client.dart';
import 'package:dio/dio.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

enum NetworkStatus {
  online,

  /// Requests fail without reaching the server. Mutations are disabled and
  /// the offline banner shows (SCREENS.md S8, M05).
  offline;

  bool get isOffline => this == offline;
}

/// Whether the API is reachable, fed by request outcomes
/// ([NetworkStatusInterceptor]) and the realtime connection (SCREENS.md
/// §3.1). While offline it probes the API every [probeInterval] (the probe
/// goes through the same interceptor, so a success flips it back online).
class NetworkStatusCubit extends Cubit<NetworkStatus> {
  NetworkStatusCubit({
    required this._probe,
    this.probeInterval = const Duration(seconds: 10),
    Stream<RealtimeConnectionState>? realtime,
  }) : super(NetworkStatus.online) {
    _realtime = realtime?.listen((state) {
      if (state == RealtimeConnectionState.connected) reportReachable();
    });
  }

  final Future<void> Function() _probe;
  final Duration probeInterval;
  StreamSubscription<RealtimeConnectionState>? _realtime;
  Timer? _probeTimer;

  /// A request could not reach the server (no network, DNS, timeout).
  void reportUnreachable() {
    if (isClosed) return;
    if (state != NetworkStatus.offline) emit(NetworkStatus.offline);
    _probeTimer ??= Timer.periodic(probeInterval, (_) => _runProbe());
  }

  /// The server answered (any status) or the socket connected.
  void reportReachable() {
    _probeTimer?.cancel();
    _probeTimer = null;
    if (!isClosed && state != NetworkStatus.online) emit(NetworkStatus.online);
  }

  /// Probes now, e.g. from the banner's retry action.
  Future<void> check() => _runProbe();

  Future<void> _runProbe() async {
    try {
      await _probe();
    } on Object {
      // The interceptor already reported the outcome.
    }
  }

  @override
  Future<void> close() async {
    _probeTimer?.cancel();
    await _realtime?.cancel();
    return super.close();
  }
}

/// Reports every request outcome to [NetworkStatusCubit]: an answer from the
/// server (even an error status) means online; a transport failure means
/// offline. Cancelled requests say nothing.
class NetworkStatusInterceptor extends Interceptor {
  NetworkStatusInterceptor(this._status);

  final NetworkStatusCubit Function() _status;

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    _status().reportReachable();
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response != null) {
      _status().reportReachable();
    } else if (ApiException.fromDioException(err).isConnectivity) {
      _status().reportUnreachable();
    }
    handler.next(err);
  }
}
