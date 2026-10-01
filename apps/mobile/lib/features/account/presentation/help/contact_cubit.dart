import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/account/data/contact_repository.dart';
import 'package:bafo/features/account/domain/contact_message.dart';
import 'package:bafo/features/auth/presentation/login_cubit.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

sealed class ContactState extends Equatable {
  const ContactState();

  @override
  List<Object?> get props => [];
}

final class ContactInitial extends ContactState {
  const ContactInitial();
}

final class ContactSending extends ContactState {
  const ContactSending();
}

/// `POST /contact` answered 201.
final class ContactSent extends ContactState {
  const ContactSent();
}

final class ContactFailure extends ContactState {
  const ContactFailure(this.error, {this.retryAt});

  final ApiException error;

  /// 429 (`guest-forms`: 5 per hour): when the form may be sent again.
  final DateTime? retryAt;

  String? fieldError(String path) => error.fieldError(path);

  /// Shown above the form (not a field error).
  bool get isFormLevel => error.fieldErrors.isEmpty;

  @override
  List<Object?> get props => [error, retryAt];
}

/// M60: the contact form (`POST /contact`, honeypot always empty).
class ContactCubit extends Cubit<ContactState> {
  ContactCubit(this._contact, {DateTime Function()? now})
    : _now = now ?? DateTime.now,
      super(const ContactInitial());

  final ContactRepository _contact;
  final DateTime Function() _now;

  Future<void> send(ContactMessage message) async {
    if (state is ContactSending) return;
    emit(const ContactSending());
    try {
      await _contact.send(message);
      emit(const ContactSent());
    } on ApiException catch (error) {
      emit(ContactFailure(error, retryAt: retryAtFor(error, _now())));
    }
  }

  /// Back to an empty form after a sent message.
  void reset() => emit(const ContactInitial());

  /// A request can end after the screen closed: its answer is dropped.
  @override
  void emit(ContactState state) {
    if (!isClosed) super.emit(state);
  }
}
