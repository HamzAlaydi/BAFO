import 'dart:convert';

import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/core/models/lookups.dart';
import 'package:bafo/core/storage/preferences_store.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/scope.dart';
import 'issuer_test_helpers.dart';

/// RELEASE_SCOPE.md §2.6 and §3 on the creation cubit: tier defaults, the
/// gated sealed format, blur validation (FQ2) and the local autosave (FQ6).
void main() {
  setUpAll(registerIssuerFallbacks);

  late MockCompetitions competitions;
  late MockLookups lookupsRepository;
  late InMemoryPreferencesStore preferences;
  final lookups = tieredLookups();
  final now = DateTime.utc(2026, 10, 1, 9);
  final office = lookups.categories.firstWhere(
    (c) => c.code == 'office_supplies',
  );
  final region = lookups.regions.first;

  setUp(() {
    competitions = MockCompetitions();
    lookupsRepository = MockLookups();
    preferences = InMemoryPreferencesStore();
    when(() => lookupsRepository.lookups()).thenAnswer((_) async => lookups);
  });

  CreateCompetitionCubit build({
    FeatureFlags flags = FeatureFlags.none,
    bool persist = true,
  }) => CreateCompetitionCubit(
    competitions: competitions,
    lookups: lookupsRepository,
    now: () => now,
    auctionEnabled: true,
    flags: flags,
    drafts: persist ? preferences : null,
  );

  group('tier cards and the sealed format', () {
    test('the fixture carries the six tier presets, ordered by tier', () {
      const form = CompetitionDraftForm(
        direction: Direction.tender,
        format: CompetitionFormat.live,
      );
      expect(form.tieredPresetsFor(lookups.presets).map((p) => p.tier), [
        PresetTier.simple,
        PresetTier.standard,
        PresetTier.protected,
      ]);
      expect(form.untieredPresetsFor(lookups.presets).map((p) => p.code), [
        'standard_live_tender',
      ]);
      final legacy = (fixtureData('lookups')['presets'] as List).first;
      expect(Preset.fromJson(legacy as Map<String, dynamic>).tier, isNull);
    });

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'core: the format is live without asking, sealed is refused, '
      'and the standard tier is the default',
      build: build,
      act: (cubit) async {
        await cubit.load();
        final blank = cubit.state as CreateCompetitionEditing;
        expect(blank.form.format, CompetitionFormat.live);
        expect(blank.isDirty, isFalse);
        cubit
          ..setDirection(Direction.tender)
          ..setFormat(CompetitionFormat.sealed);
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.form.format, CompetitionFormat.live);
        expect(state.form.presetCode, 'tender_live_standard');
        expect(state.preset?.tier, PresetTier.standard);
        expect(state.isDirty, isTrue);
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'full: the format is asked; sealed falls back to the sealed template',
      build: () => build(flags: ScopeFlags.full),
      act: (cubit) async {
        await cubit.load();
        expect((cubit.state as CreateCompetitionEditing).form.format, isNull);
        cubit
          ..setDirection(Direction.tender)
          ..setFormat(CompetitionFormat.sealed);
        expect(
          (cubit.state as CreateCompetitionEditing).form.presetCode,
          'sealed_rfq',
        );
        cubit.setFormat(CompetitionFormat.live);
      },
      verify: (cubit) => expect(
        (cubit.state as CreateCompetitionEditing).form.presetCode,
        'tender_live_standard',
      ),
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'a chosen tier survives a direction change when it exists there',
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit
          ..setDirection(Direction.tender)
          ..selectPreset('tender_live_protected')
          ..setDirection(Direction.auction);
      },
      verify: (cubit) => expect(
        // The auction has its own tiers: back to its standard one.
        (cubit.state as CreateCompetitionEditing).form.presetCode,
        'auction_live_standard',
      ),
    );
  });

  group('FQ2 validation on blur and on Next', () {
    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'nothing is shown while typing; a left field shows its problem',
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit.setDirection(Direction.tender);
        expect(cubit.next(), isTrue);
        cubit.update((f) => f.copyWith(title: ''));
        expect((cubit.state as CreateCompetitionEditing).problems, isEmpty);
        cubit.touch(DraftField.title);
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.problems, {DraftField.title: DraftProblem.required});
        expect(state.showAllProblems, isFalse);
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'Next shows every problem of the step; moving on resets the view',
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit.setDirection(Direction.tender);
        expect(cubit.next(), isTrue);
        expect(cubit.next(), isFalse);
        final failed = cubit.state as CreateCompetitionEditing;
        expect(failed.showAllProblems, isTrue);
        expect(
          failed.problems.keys,
          containsAll([
            DraftField.title,
            DraftField.category,
            DraftField.region,
          ]),
        );
        cubit.update(
          (f) => f.copyWith(
            title: 'توريد',
            categoryId: () => office.id,
            regionId: () => region.id,
          ),
        );
        expect((cubit.state as CreateCompetitionEditing).problems, isEmpty);
        expect(cubit.next(), isTrue);
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.step, CreateStep.schedule);
        expect(state.showAllProblems, isFalse);
        expect(state.touched, isEmpty);
      },
    );

    test('the duration problems can state the value and the bound', () {
      final form = CompetitionDraftForm(
        direction: Direction.tender,
        format: CompetitionFormat.live,
        presetCode: 'tender_live_standard',
        scheduledCloseAt: now.add(const Duration(minutes: 4)),
      );
      expect(form.validate(CreateStep.schedule, lookups: lookups, now: now), {
        DraftField.closesAt: DraftProblem.tooShort,
      });
      expect(form.durationFrom(now), const Duration(minutes: 4));
    });
  });

  group('FQ6 local autosave', () {
    final draft = Competition.fromJson(fixtureData('competition_issuer_draft'));

    test('the form round-trips through JSON', () {
      final form = CompetitionDraftForm(
        direction: Direction.auction,
        format: CompetitionFormat.live,
        presetCode: 'auction_live_simple',
        title: 'بيع معدات',
        description: 'وصف',
        categoryId: office.id,
        categoryOtherText: '',
        regionId: region.id,
        startPriceMinor: 5000000,
        reservePriceMinor: 6000000,
        opensOnPublish: false,
        biddingOpensAt: now,
        scheduledCloseAt: now.add(const Duration(days: 3)),
      );
      final restored = CompetitionDraftForm.fromJson(
        jsonDecode(jsonEncode(form.toJson())) as Map<String, dynamic>,
      );
      expect(restored, form);
      expect(const CompetitionDraftForm().toJson()['opens_on_publish'], isTrue);
    });

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'every change is kept locally and comes back on the next open',
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit.setDirection(Direction.tender);
        expect(cubit.next(), isTrue);
        cubit.update((f) => f.copyWith(title: 'توريد أجهزة'));
        await Future<void>.delayed(Duration.zero);
        final saved = preferences.getString(PreferenceKeys.issuerCreateDraft);
        expect(saved, isNotNull);
        expect(
          CompetitionDraftForm.fromJson(
            jsonDecode(saved!) as Map<String, dynamic>,
          ).title,
          'توريد أجهزة',
        );
      },
      verify: (_) async {
        final again = build();
        await again.load();
        final state = again.state as CreateCompetitionEditing;
        expect(state.restored, isTrue);
        expect(state.form.title, 'توريد أجهزة');
        expect(state.form.direction, Direction.tender);
        expect(state.form.presetCode, 'tender_live_standard');
        expect(state.step, CreateStep.type);
        expect(state.isDirty, isTrue);
        again.startOver();
        final blank = again.state as CreateCompetitionEditing;
        expect(blank.restored, isFalse);
        expect(blank.isDirty, isFalse);
        await Future<void>.delayed(Duration.zero);
        expect(preferences.getString(PreferenceKeys.issuerCreateDraft), isNull);
        await again.close();
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'a saved draft is cleared once the server has it, or on discard',
      setUp: () =>
          when(() => competitions.create(any())).thenAnswer((_) async => draft),
      build: build,
      act: (cubit) async {
        await cubit.load();
        cubit.setDirection(Direction.tender);
        expect(cubit.next(), isTrue);
        cubit.update(
          (f) => f.copyWith(
            title: 'توريد',
            categoryId: () => office.id,
            regionId: () => region.id,
          ),
        );
        expect(cubit.next(), isTrue);
        cubit.update(
          (f) => f.copyWith(
            scheduledCloseAt: () => now.add(const Duration(days: 3)),
          ),
        );
        expect(cubit.next(), isTrue);
        await Future<void>.delayed(Duration.zero);
        expect(
          preferences.getString(PreferenceKeys.issuerCreateDraft),
          isNotNull,
        );
        await cubit.submit();
        await Future<void>.delayed(Duration.zero);
      },
      verify: (cubit) {
        expect(cubit.state, CreateCompetitionSaved(draft));
        expect(preferences.getString(PreferenceKeys.issuerCreateDraft), isNull);
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'a stale saved draft drops ids the lookups no longer have and the '
      'sealed format of a later release',
      setUp: () =>
          preferences.values[PreferenceKeys.issuerCreateDraft] = jsonEncode(
            const CompetitionDraftForm(
              direction: Direction.tender,
              format: CompetitionFormat.sealed,
              presetCode: 'sealed_rfq',
              title: 'قديم',
              categoryId: 'gone',
              regionId: 'gone',
            ).toJson(),
          ),
      build: build,
      act: (cubit) => cubit.load(),
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.restored, isTrue);
        expect(state.form.title, 'قديم');
        expect(state.form.format, CompetitionFormat.live);
        expect(state.form.categoryId, isNull);
        expect(state.form.regionId, isNull);
        // `sealed_rfq` does not match live: the standard tier takes over.
        expect(state.form.presetCode, 'tender_live_standard');
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'without a store nothing is written',
      build: () => build(persist: false),
      act: (cubit) async {
        await cubit.load();
        cubit.setDirection(Direction.tender);
        await Future<void>.delayed(Duration.zero);
      },
      verify: (_) => expect(
        preferences.getString(PreferenceKeys.issuerCreateDraft),
        isNull,
      ),
    );
  });
}
