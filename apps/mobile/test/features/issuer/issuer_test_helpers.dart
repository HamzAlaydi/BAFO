import 'dart:convert';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/files/file_download_service.dart';
import 'package:bafo/core/lookups/lookups_repository.dart';
import 'package:bafo/core/models/competition_enums.dart';
import 'package:bafo/core/models/file_ref.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/features/competitions/data/competition_content_repositories.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/attachment.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/invitations/data/invitations_repository.dart';
import 'package:bafo/features/invitations/domain/invitation_models.dart';
import 'package:bafo/features/issuer/data/issuer_report_repository.dart';
import 'package:bafo/features/live/data/live_repository.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

export 'package:bafo/core/models/competition_enums.dart';

export '../../helpers/fakes.dart';

class MockCompetitions extends Mock implements CompetitionsRepository {}

class MockAttachments extends Mock implements AttachmentsRepository {}

class MockInvitations extends Mock implements InvitationsRepository {}

class MockVendors extends Mock implements VendorsRepository {}

class MockLive extends Mock implements LiveRepository {}

class MockLookups extends Mock implements LookupsRepository {}

class MockReports extends Mock implements IssuerReportRepository {}

class MockDownloads extends Mock implements FileDownloadService {}

/// Fallback values for `any()` of non-primitive parameters.
void registerIssuerFallbacks() {
  registerFallbackValue(
    const CompetitionDraftInput(
      title: 't',
      categoryId: 'c',
      regionId: 'r',
      direction: Direction.tender,
      format: CompetitionFormat.live,
    ),
  );
  registerFallbackValue(<InvitationInput>[]);
  registerFallbackValue(const FileRef(id: 'f', name: 'f', downloadPath: '/f'));
  registerFallbackValue(<String, Object?>{});
  registerFallbackValue(CompetitionStatusGroup.all);
  registerFallbackValue(CloseReasonKind.cancel);
}

/// A deep copy of fixture data, so a test can change it.
Map<String, dynamic> copyOf(Map<String, dynamic> json) =>
    jsonDecode(jsonEncode(json)) as Map<String, dynamic>;

Competition issuerCompetition(
  String fixture, [
  void Function(Map<String, dynamic>)? edit,
]) {
  final json = copyOf(fixtureData(fixture));
  edit?.call(json);
  return Competition.fromJson(json);
}

Lookups fixtureLookups() => Lookups.fromJson(fixtureData('lookups'));

List<Attachment> fixtureAttachments() =>
    fixtureList('attachments_issuer_live_initial')
        .map(Attachment.fromJson)
        .toList();

Paged<CompetitionListItem> issuerPage({
  int page = 1,
  bool hasMore = false,
  List<CompetitionListItem>? items,
}) => Paged(
  items:
      items ??
      fixtureList('competitions_issuer')
          .map(CompetitionListItem.fromJson)
          .toList(),
  meta: PageMeta(currentPage: page, perPage: 20, hasMore: hasMore),
);

ApiException apiError(
  String code, {
  int? status,
  Map<String, List<String>> fieldErrors = const {},
  Map<String, dynamic> details = const {},
}) => ApiException(
  code: code,
  statusCode: status,
  fieldErrors: fieldErrors,
  details: details,
  message: 'server message for $code',
);
