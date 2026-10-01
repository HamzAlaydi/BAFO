import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:equatable/equatable.dart';

enum AttachmentKind implements WireEnum {
  document('document'),

  /// Visible to invitees before they join.
  invitationDocument('invitation_document'),

  /// An https link (opens in the browser).
  externalLink('external_link'),
  unknown('unknown');

  const AttachmentKind(this.wire);

  @override
  final String wire;

  static AttachmentKind parse(Object? raw) => parseWire(values, raw, unknown);
}

/// `Attachment` (API.md §2.7): a file ([file]) or a link ([url]).
final class Attachment extends Equatable {
  const Attachment({
    required this.id,
    required this.kind,
    this.title,
    this.file,
    this.url,
    this.isAddendum = false,
    this.sortOrder = 0,
    this.createdAt,
  });

  factory Attachment.fromJson(Json json) => Attachment(
    id: json.str('id'),
    kind: AttachmentKind.parse(json['kind']),
    title: json.strOrNull('title'),
    file: json.parse('file', FileRef.fromJson),
    url: json.strOrNull('url'),
    isAddendum: json.flag('is_addendum'),
    sortOrder: json.intOrNull('sort_order') ?? 0,
    createdAt: json.dateOrNull('created_at'),
  );

  final String id;
  final AttachmentKind kind;
  final String? title;

  /// Null for links.
  final FileRef? file;

  /// Set for links only.
  final String? url;

  /// Added after publish (an addendum participants are told about).
  final bool isAddendum;
  final int sortOrder;
  final DateTime? createdAt;

  bool get isLink => kind == AttachmentKind.externalLink || file == null;

  /// What to show: the title, else the file name, else the URL.
  String get displayName {
    final value = title;
    if (value != null && value.trim().isNotEmpty) return value;
    return file?.name ?? url ?? '';
  }

  @override
  List<Object?> get props => [
    id,
    kind,
    title,
    file,
    url,
    isAddendum,
    sortOrder,
    createdAt,
  ];
}
