import 'package:bafo/core/api/json.dart';
import 'package:equatable/equatable.dart';

/// A private file (API.md §2.5). Download it through [downloadPath] with the
/// bearer header (`FileDownloadService`); never put a token in its URL.
final class FileRef extends Equatable {
  const FileRef({
    required this.id,
    required this.name,
    required this.downloadPath,
    this.mimeType,
    this.extension,
    this.sizeBytes,
    this.createdAt,
  });

  factory FileRef.fromJson(Json json) => FileRef(
    id: json.str('id'),
    name: json.str('name'),
    mimeType: json.strOrNull('mime_type'),
    extension: json.strOrNull('extension'),
    sizeBytes: json.intOrNull('size_bytes'),
    downloadPath: json.str('download_path'),
    createdAt: json.dateOrNull('created_at'),
  );

  final String id;
  final String name;
  final String? mimeType;
  final String? extension;
  final int? sizeBytes;

  /// Server path, e.g. `/api/app/v1/files/{id}/download`.
  final String downloadPath;
  final DateTime? createdAt;

  @override
  List<Object?> get props => [
    id,
    name,
    mimeType,
    extension,
    sizeBytes,
    downloadPath,
    createdAt,
  ];
}

/// `OrganizationSummary` (API.md §2.2): the public face of an organisation.
///
/// Participants never see other participants' identities: this is only ever
/// present where the projection allows it (issuers, the viewer's own org).
final class OrganizationSummary extends Equatable {
  const OrganizationSummary({
    required this.id,
    required this.name,
    this.logoUrl,
    this.verified = false,
  });

  factory OrganizationSummary.fromJson(Json json) => OrganizationSummary(
    id: json.str('id'),
    name: json.str('name'),
    logoUrl: json.strOrNull('logo_url'),
    verified: json.flag('verified'),
  );

  final String id;
  final String name;
  final String? logoUrl;
  final bool verified;

  @override
  List<Object?> get props => [id, name, logoUrl, verified];
}
