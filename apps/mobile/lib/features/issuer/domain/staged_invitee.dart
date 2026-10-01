import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:equatable/equatable.dart';

/// Where a staged invitee comes from (SCREENS.md M40 segments).
enum InviteeSource { suggestion, email, vendor }

/// A row waiting to be sent with `POST …/invitations` (all or nothing).
final class StagedInvitee extends Equatable {
  const StagedInvitee({
    required this.source,
    required this.label,
    this.email,
    this.organizationId,
    this.vendorId,
    this.sponsored = false,
  });

  factory StagedInvitee.email(String email) => StagedInvitee(
    source: InviteeSource.email,
    label: email.trim(),
    email: email.trim(),
  );

  final InviteeSource source;

  /// What the chip shows: the organisation, vendor or e-mail.
  final String label;
  final String? email;
  final String? organizationId;
  final String? vendorId;

  /// Counts only in the `selected` sponsorship mode.
  final bool sponsored;

  /// De-duplication key: one row per organisation, vendor or e-mail.
  String get key => switch (source) {
    InviteeSource.suggestion => 'org:$organizationId',
    InviteeSource.vendor => 'vendor:$vendorId',
    InviteeSource.email => 'email:${email?.toLowerCase()}',
  };

  StagedInvitee withSponsored(bool value) => StagedInvitee(
    source: source,
    label: label,
    email: email,
    organizationId: organizationId,
    vendorId: vendorId,
    sponsored: value,
  );

  InvitationInput toInput({bool coverFees = true}) => InvitationInput(
    email: email,
    organizationId: organizationId,
    vendorId: vendorId,
    sponsored: coverFees && sponsored,
  );

  @override
  List<Object?> get props => [
    source,
    label,
    email,
    organizationId,
    vendorId,
    sponsored,
  ];
}

/// A pasted e-mail list split on commas, semicolons, spaces or new lines
/// (SCREENS.md W15 step 6): [valid] addresses (lower-cased duplicates
/// removed) and the [invalid] pieces to show back.
({List<String> valid, List<String> invalid}) splitEmails(String text) {
  final valid = <String>[];
  final seen = <String>{};
  final invalid = <String>[];
  for (final piece in text.split(RegExp(r'[\s,;،]+'))) {
    final value = piece.trim();
    if (value.isEmpty) continue;
    if (!isEmailAddress(value)) {
      invalid.add(value);
    } else if (seen.add(value.toLowerCase())) {
      valid.add(value);
    }
  }
  return (valid: valid, invalid: invalid);
}

final RegExp _email = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

/// The same e-mail rule as `Validators.email`.
bool isEmailAddress(String value) => _email.hasMatch(value.trim());

/// Per-row problems of an all-or-nothing `POST …/invitations` (422): the
/// message of `errors."invitations.{i}.<field>"` and the machine code of
/// `details.item_codes` for the same path, by row index.
Map<int, ({String? code, String message})> invitationRowErrors({
  required Map<String, List<String>> fieldErrors,
  required Map<String, dynamic> details,
}) {
  final itemCodes = details['item_codes'];
  final result = <int, ({String? code, String message})>{};
  final pattern = RegExp(r'^invitations\.(\d+)\.');
  for (final entry in fieldErrors.entries) {
    final match = pattern.firstMatch(entry.key);
    if (match == null || entry.value.isEmpty) continue;
    final index = int.parse(match.group(1)!);
    final code = itemCodes is Map ? itemCodes[entry.key] : null;
    result.putIfAbsent(
      index,
      () => (code: code is String ? code : null, message: entry.value.first),
    );
  }
  if (itemCodes is Map) {
    for (final entry in itemCodes.entries) {
      final match = pattern.firstMatch('${entry.key}');
      final code = entry.value;
      if (match == null || code is! String) continue;
      final index = int.parse(match.group(1)!);
      result.putIfAbsent(index, () => (code: code, message: ''));
    }
  }
  return result;
}
