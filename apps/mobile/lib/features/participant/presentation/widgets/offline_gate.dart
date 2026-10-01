import 'package:bafo/core/network/network_status_cubit.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';

/// Whether mutations are disabled because the app is offline (SCREENS.md
/// S8, M05). Rebuilds the caller when the status changes; false when no
/// [NetworkStatusCubit] is provided.
bool watchOffline(BuildContext context) {
  try {
    return context.select<NetworkStatusCubit, bool>(
      (cubit) => cubit.state.isOffline,
    );
  } on ProviderNotFoundException {
    return false;
  }
}
