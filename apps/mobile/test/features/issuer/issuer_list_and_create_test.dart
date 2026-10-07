import 'package:bafo/core/api/pagination.dart';
import 'package:bafo/core/config/app_config.dart';
import 'package:bafo/features/competitions/data/competitions_repository.dart';
import 'package:bafo/features/competitions/domain/competition.dart';
import 'package:bafo/features/competitions/domain/issuer_models.dart';
import 'package:bafo/features/issuer/domain/competition_draft_form.dart';
import 'package:bafo/features/issuer/presentation/create/create_competition_cubit.dart';
import 'package:bafo/features/issuer/presentation/list/issued_list_bloc.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/scope.dart';
import 'issuer_test_helpers.dart';

void main() {
  setUpAll(registerIssuerFallbacks);

  group('IssuedListBloc (M33)', () {
    late MockCompetitions competitions;

    setUp(() => competitions = MockCompetitions());

    void answer({
      CompetitionStatusGroup? group,
      String? query,
      int page = 1,
      required Paged<CompetitionListItem> result,
    }) => when(
      () => competitions.list(
        role: CompetitionListRole.issuer,
        group: group ?? any(named: 'group'),
        query: query ?? any(named: 'query'),
        page: page,
      ),
    ).thenAnswer((_) async => result);

    blocTest<IssuedListBloc, IssuedListState>(
      'loads the active segment as the issuer',
      setUp: () => answer(
        group: CompetitionStatusGroup.active,
        result: issuerPage(hasMore: true),
      ),
      build: () => IssuedListBloc(competitions: competitions),
      act: (bloc) => bloc.add(const IssuedListStarted()),
      expect: () => [
        const IssuedListLoading(
          group: CompetitionStatusGroup.active,
          query: '',
        ),
        isA<IssuedListLoaded>()
            .having((s) => s.items, 'items', hasLength(12))
            .having((s) => s.hasMore, 'hasMore', isTrue),
      ],
      verify: (_) => verify(
        () => competitions.list(
          role: CompetitionListRole.issuer,
          group: CompetitionStatusGroup.active,
          query: '',
          page: 1,
        ),
      ).called(1),
    );

    blocTest<IssuedListBloc, IssuedListState>(
      'a segment change reloads; the query is debounced and only the last one is sent',
      setUp: () => answer(result: issuerPage()),
      build: () => IssuedListBloc(
        competitions: competitions,
        debounce: const Duration(milliseconds: 20),
      ),
      act: (bloc) async {
        bloc.add(const IssuedListFilterChanged(CompetitionStatusGroup.draft));
        await Future<void>.delayed(Duration.zero);
        bloc
          ..add(const IssuedListQueryChanged('ت'))
          ..add(const IssuedListQueryChanged('توريد'));
      },
      wait: const Duration(milliseconds: 80),
      verify: (bloc) {
        expect(bloc.state.group, CompetitionStatusGroup.draft);
        expect(bloc.state.query, 'توريد');
        verifyNever(
          () => competitions.list(
            role: CompetitionListRole.issuer,
            group: any(named: 'group'),
            query: 'ت',
            page: any(named: 'page'),
          ),
        );
        verify(
          () => competitions.list(
            role: CompetitionListRole.issuer,
            group: CompetitionStatusGroup.draft,
            query: 'توريد',
            page: 1,
          ),
        ).called(1);
      },
    );

    blocTest<IssuedListBloc, IssuedListState>(
      'infinite scroll appends the next page without duplicates',
      setUp: () {
        final all = issuerPage().items;
        answer(
          page: 1,
          result: issuerPage(items: all.take(10).toList(), hasMore: true),
        );
        answer(
          page: 2,
          result: Paged(
            items: all.skip(8).toList(),
            meta: const PageMeta(currentPage: 2, perPage: 20, hasMore: false),
          ),
        );
      },
      build: () => IssuedListBloc(competitions: competitions),
      act: (bloc) async {
        bloc.add(const IssuedListStarted());
        await Future<void>.delayed(Duration.zero);
        bloc
          ..add(const IssuedListNextPageRequested())
          ..add(const IssuedListNextPageRequested());
      },
      verify: (bloc) {
        final state = bloc.state as IssuedListLoaded;
        expect(state.items, hasLength(12));
        expect(state.hasMore, isFalse);
        expect(state.page, 2);
      },
    );

    blocTest<IssuedListBloc, IssuedListState>(
      'a first-load error shows the failure; a failed refresh keeps the rows',
      setUp: () {
        var calls = 0;
        when(
          () => competitions.list(
            role: CompetitionListRole.issuer,
            group: any(named: 'group'),
            query: any(named: 'query'),
            page: any(named: 'page'),
          ),
        ).thenAnswer((_) async {
          calls++;
          if (calls == 1) throw apiError('network_error');
          if (calls == 2) return issuerPage();
          throw apiError('server_error', status: 500);
        });
      },
      build: () => IssuedListBloc(competitions: competitions),
      act: (bloc) async {
        bloc.add(const IssuedListStarted());
        await Future<void>.delayed(Duration.zero);
        bloc.add(const IssuedListStarted());
        await Future<void>.delayed(Duration.zero);
        bloc.add(const IssuedListRefreshed());
      },
      expect: () => [
        isA<IssuedListLoading>(),
        isA<IssuedListFailure>(),
        isA<IssuedListLoading>(),
        isA<IssuedListLoaded>().having(
          (s) => s.refreshing,
          'refreshing',
          isFalse,
        ),
        isA<IssuedListLoaded>().having(
          (s) => s.refreshing,
          'refreshing',
          isTrue,
        ),
        isA<IssuedListLoaded>()
            .having((s) => s.refreshing, 'refreshing', isFalse)
            .having((s) => s.items, 'items', hasLength(12)),
      ],
    );
  });

  group('CreateCompetitionCubit (M34–M37)', () {
    late MockCompetitions competitions;
    late MockLookups lookupsRepository;
    final lookups = fixtureLookups();
    final now = DateTime.utc(2026, 10, 1, 9);
    final office = lookups.categories.firstWhere(
      (c) => c.code == 'office_supplies',
    );
    final region = lookups.regions.first;
    final draft = Competition.fromJson(fixtureData('competition_issuer_draft'));

    setUp(() {
      competitions = MockCompetitions();
      lookupsRepository = MockLookups();
      when(() => lookupsRepository.lookups()).thenAnswer((_) async => lookups);
    });

    CreateCompetitionCubit build({
      bool auction = true,
      FeatureFlags flags = FeatureFlags.none,
    }) => CreateCompetitionCubit(
      competitions: competitions,
      lookups: lookupsRepository,
      now: () => now,
      auctionEnabled: auction,
      flags: flags,
    );

    void fill(CreateCompetitionCubit cubit) {
      cubit
        ..setDirection(Direction.tender)
        ..setFormat(CompetitionFormat.live);
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
          startPriceMinor: () => 25000000,
          scheduledCloseAt: () => now.add(const Duration(days: 3)),
        ),
      );
      expect(cubit.next(), isTrue);
    }

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'loads the lookups and starts on step 1',
      build: build,
      act: (cubit) => cubit.load(),
      expect: () => [
        const CreateCompetitionLoading(),
        isA<CreateCompetitionEditing>()
            .having((s) => s.step, 'step', CreateStep.type)
            .having((s) => s.isDirty, 'dirty', isFalse),
      ],
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'the only matching preset is picked; auctions stay off without the flag',
      // The sealed format is a hidden feature: scope `full` (RELEASE_SCOPE §1.6).
      build: () => build(auction: false, flags: ScopeFlags.full),
      act: (cubit) async {
        await cubit.load();
        cubit
          ..setDirection(Direction.auction)
          ..setDirection(Direction.tender)
          ..setFormat(CompetitionFormat.sealed);
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.form.direction, Direction.tender);
        expect(state.form.presetCode, 'sealed_rfq');
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'Next shows the step problems and stays; fixing them clears them',
      build: build,
      act: (cubit) async {
        await cubit.load();
        expect(cubit.next(), isFalse);
        cubit
          ..setDirection(Direction.tender)
          ..setFormat(CompetitionFormat.live);
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.step, CreateStep.type);
        expect(state.problems, isEmpty);
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'Save draft posts the preset rules with prices and opens the draft',
      setUp: () =>
          when(() => competitions.create(any())).thenAnswer((_) async => draft),
      build: build,
      act: (cubit) async {
        await cubit.load();
        fill(cubit);
        await cubit.submit();
      },
      verify: (cubit) {
        expect(cubit.state, CreateCompetitionSaved(draft));
        final input =
            verify(() => competitions.create(captureAny())).captured.single
                as CompetitionDraftInput;
        expect(input.presetCode, 'standard_live_tender');
        expect(input.rules!.startPriceMinor, 25000000);
        expect(input.biddingOpensAt, isNull);
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'server field errors jump back to their step; business errors stay on review',
      setUp: () {
        var calls = 0;
        when(() => competitions.create(any())).thenAnswer((_) async {
          calls++;
          if (calls == 1) {
            throw apiError(
              'validation_failed',
              status: 422,
              fieldErrors: {
                'category_id': ['الفئة لا تسمح بالمزايدات'],
              },
            );
          }
          throw apiError('issuer_plan_required', status: 403);
        });
      },
      build: build,
      act: (cubit) async {
        await cubit.load();
        fill(cubit);
        await cubit.submit();
        final afterValidation = cubit.state as CreateCompetitionEditing;
        expect(afterValidation.step, CreateStep.basics);
        expect(
          afterValidation.serverErrors[DraftField.category],
          'الفئة لا تسمح بالمزايدات',
        );
        cubit
          ..next()
          ..next();
        await cubit.submit();
      },
      verify: (cubit) {
        final state = cubit.state as CreateCompetitionEditing;
        expect(state.step, CreateStep.review);
        expect(state.submitting, isFalse);
        expect(state.submitError?.code, 'issuer_plan_required');
      },
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'back and goTo move to earlier steps only',
      build: build,
      act: (cubit) async {
        await cubit.load();
        fill(cubit);
        cubit.goTo(CreateStep.basics);
        expect(cubit.back(), isTrue);
        expect(cubit.back(), isFalse);
      },
      verify: (cubit) => expect(
        (cubit.state as CreateCompetitionEditing).step,
        CreateStep.type,
      ),
    );

    blocTest<CreateCompetitionCubit, CreateCompetitionState>(
      'a lookups failure is shown',
      setUp: () =>
          when(() => lookupsRepository.lookups())
              .thenThrow(apiError('network_error')),
      build: build,
      act: (cubit) => cubit.load(),
      expect: () => [
        const CreateCompetitionLoading(),
        isA<CreateCompetitionFailure>(),
      ],
    );
  });

  test('the sponsorship fixture stays price-free on mobile', () {
    final sponsorship = Sponsorship.fromJson(
      fixtureData('sponsorship_issuer_sponsored'),
    );
    // The model has no price field: mobile never shows the pass price.
    expect(sponsorship.toString(), isNot(contains('20000')));
    expect(sponsorship.mode, 'selected');
  });
}
