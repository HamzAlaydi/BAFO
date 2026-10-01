import 'package:bafo/core/storage/preferences_store.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

final class OnboardingState extends Equatable {
  const OnboardingState({this.page = 0, this.completed = false});

  /// 0-based slide index.
  final int page;

  /// The slides were finished or skipped; they are not shown again.
  final bool completed;

  @override
  List<Object?> get props => [page, completed];
}

/// The welcome slides (M02): shown once on the first run without a session.
class OnboardingCubit extends Cubit<OnboardingState> {
  OnboardingCubit(this._preferences) : super(const OnboardingState());

  final PreferencesStore _preferences;

  static const int pageCount = 3;

  /// Whether the slides are still due on this device.
  static bool isDue(PreferencesStore preferences) =>
      preferences.getString(PreferenceKeys.onboardingSeen) == null;

  void pageChanged(int page) {
    if (page != state.page) emit(OnboardingState(page: page.clamp(0, pageCount - 1)));
  }

  /// Finished or skipped: remember it and let the router move on.
  Future<void> complete() async {
    await _preferences.setString(PreferenceKeys.onboardingSeen, '1');
    emit(OnboardingState(page: state.page, completed: true));
  }
}
