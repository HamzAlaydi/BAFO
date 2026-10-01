import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/auth/data/legal_repository.dart';
import 'package:bafo/features/auth/domain/auth_models.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class LegalState extends Equatable {
  const LegalState();

  @override
  List<Object?> get props => [];
}

final class LegalInitial extends LegalState {
  const LegalInitial();
}

final class LegalLoading extends LegalState {
  const LegalLoading();
}

final class LegalLoaded extends LegalState {
  const LegalLoaded(this.document);

  final LegalDocument document;

  @override
  List<Object?> get props => [document];
}

/// No published version (404): S8 **N** «هذه الوثيقة غير متاحة حالياً.».
final class LegalUnavailable extends LegalState {
  const LegalUnavailable();
}

final class LegalFailure extends LegalState {
  const LegalFailure(this.error);

  final ApiException error;

  @override
  List<Object?> get props => [error];
}

/// M13: one legal document in the app language.
class LegalCubit extends Cubit<LegalState> {
  LegalCubit(this._repository, this.code) : super(const LegalInitial());

  final LegalRepository _repository;
  final LegalCode code;

  Future<void> load() async {
    emit(const LegalLoading());
    try {
      emit(LegalLoaded(await _repository.document(code)));
    } on ApiException catch (error) {
      emit(
        error.statusCode == 404 || error.code == 'not_found'
            ? const LegalUnavailable()
            : LegalFailure(error),
      );
    }
  }
}
