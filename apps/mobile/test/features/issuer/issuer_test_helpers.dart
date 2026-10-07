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

/// The captured lookups plus the six tier presets of RELEASE_SCOPE.md §2.2
/// (the Catalog seeder's values), for the tier cards.
Lookups tieredLookups() =>
    Lookups.fromJson(withTierPresets(copyOf(fixtureData('lookups'))));

/// Adds the six tier presets of RELEASE_SCOPE.md §2.2 to a `GET /lookups`
/// body (mutated and returned).
Map<String, dynamic> withTierPresets(Map<String, dynamic> json) {
  Map<String, dynamic> rules({
    required String rank,
    required int minParticipants,
    int? stepBps,
    Map<String, dynamic>? autoExtend,
  }) => {
    'min_step_minor': null,
    'min_step_bps': stepBps,
    'amount_granularity_minor': 100,
    'must_beat': 'own',
    'rank_visibility': rank,
    'show_prices': false,
    'auto_extend':
        autoExtend ??
        {
          'enabled': false,
          'window_seconds': null,
          'by_seconds': null,
          'max_extensions': null,
        },
    'final_window_minutes': null,
    'bafo_round': {'enabled': false, 'duration_minutes': null},
    'min_participants': minParticipants,
    'result_publication': 'outcome_only',
  };
  final tiers = <Map<String, dynamic>>[];
  for (final direction in ['tender', 'auction']) {
    tiers.addAll([
      {
        'id': 'preset-$direction-simple',
        'code': '${direction}_live_simple',
        'tier': 'simple',
        'name': 'بسيطة',
        'description':
            'يحسّن كل متنافس عرضه بحرّية ويعرف فقط إن كان متصدراً. '
            'تُغلق المنافسة في موعدها دون تمديد.',
        'direction': direction,
        'format': 'live',
        'rules': rules(rank: 'leading_flag', minParticipants: 1),
      },
      {
        'id': 'preset-$direction-standard',
        'code': '${direction}_live_standard',
        'tier': 'standard',
        'name': 'قياسية',
        'description':
            'حد أدنى للتحسين 0.5%. إذا تغيّر العرض المتصدر في آخر 3 دقائق '
            'يُمدَّد الإغلاق 3 دقائق (حتى 10 مرات).',
        'direction': direction,
        'format': 'live',
        'rules': rules(
          stepBps: 50,
          rank: 'leading_flag',
          minParticipants: 2,
          autoExtend: {
            'enabled': true,
            'window_seconds': 180,
            'by_seconds': 180,
            'max_extensions': 10,
          },
        ),
      },
      {
        'id': 'preset-$direction-protected',
        'code': '${direction}_live_protected',
        'tier': 'protected',
        'name': 'حماية قصوى',
        'description':
            'لا يرى المتنافسون ترتيبهم ولا أسعار غيرهم. حد أدنى للتحسين 1%، '
            'وتمديد 5 دقائق عند أي تغيير في العرض المتصدر خلال آخر 5 دقائق '
            '(حتى 20 مرة).',
        'direction': direction,
        'format': 'live',
        'rules': rules(
          stepBps: 100,
          rank: 'none',
          minParticipants: 2,
          autoExtend: {
            'enabled': true,
            'window_seconds': 300,
            'by_seconds': 300,
            'max_extensions': 20,
          },
        ),
      },
    ]);
  }
  json['presets'] = [...tiers, ...(json['presets'] as List)];
  return json;
}

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
