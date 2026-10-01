import 'package:bafo/core/api/json.dart';
import 'package:equatable/equatable.dart';

/// `POST /contact` (API.md §1.1): the help form (M60).
final class ContactMessage extends Equatable {
  const ContactMessage({
    required this.name,
    required this.email,
    required this.subject,
    required this.message,
    this.phone,
    this.company,
  });

  final String name;
  final String email;

  /// E.164 (`+9665XXXXXXXX`), optional.
  final String? phone;
  final String? company;
  final String subject;
  final String message;

  static String? _blankToNull(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  /// The honeypot `website_url` is always sent empty (SCREENS.md S7).
  Json toJson() => {
    'name': name.trim(),
    'email': email.trim(),
    'phone': _blankToNull(phone),
    'company': _blankToNull(company),
    'subject': subject.trim(),
    'message': message.trim(),
    'website_url': '',
  };

  @override
  List<Object?> get props => [name, email, phone, company, subject, message];
}
