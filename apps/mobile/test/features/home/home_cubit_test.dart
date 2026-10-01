import 'dart:async';

import 'package:bafo/core/api/api_exception.dart';
import 'package:bafo/features/home/data/home_repository.dart';
import 'package:bafo/features/home/domain/home_models.dart';
import 'package:bafo/features/home/presentation/home_cubit.dart';
import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../helpers/fakes.dart';

class _MockHome extends Mock implements HomeRepository {}

void main() {
  late _MockHome repository;
  final issuer = Home.fromJson(fixtureData('home_issuer'));
  final supplier = Home.fromJson(fixtureData('home_supplier_a'));
  const offline = ApiException(code: ApiErrorCode.network);

  setUp(() => repository = _MockHome());

  group('HomeCubit', () {
    blocTest<HomeCubit, HomeState>(
      'loads GET /home once',
      setUp: () => when(repository.home).thenAnswer((_) async => issuer),
      build: () => HomeCubit(home: repository),
      act: (cubit) => cubit.load(),
      expect: () => [HomeLoaded(issuer)],
      verify: (_) => verify(repository.home).called(1),
    );

    blocTest<HomeCubit, HomeState>(
      'a failed first load is an error state, and retry shows loading again',
      setUp: () {
        var calls = 0;
        when(repository.home).thenAnswer((_) async {
          if (calls++ == 0) throw offline;
          return issuer;
        });
      },
      build: () => HomeCubit(home: repository),
      act: (cubit) async {
        await cubit.load();
        await cubit.load();
      },
      expect: () => [
        const HomeFailure(offline),
        const HomeLoading(),
        HomeLoaded(issuer),
      ],
    );

    blocTest<HomeCubit, HomeState>(
      'a refresh keeps the data on screen and replaces it',
      setUp: () => when(repository.home).thenAnswer((_) async => supplier),
      build: () => HomeCubit(home: repository),
      seed: () => HomeLoaded(issuer),
      act: (cubit) => cubit.refresh(),
      expect: () => [
        HomeLoaded(issuer, refreshing: true),
        HomeLoaded(supplier),
      ],
    );

    blocTest<HomeCubit, HomeState>(
      'a failed refresh keeps the stale data under an error (S8)',
      setUp: () => when(repository.home).thenThrow(offline),
      build: () => HomeCubit(home: repository),
      seed: () => HomeLoaded(issuer),
      act: (cubit) => cubit.refresh(),
      expect: () => [
        HomeLoaded(issuer, refreshing: true),
        HomeLoaded(issuer, refreshError: offline),
      ],
    );

    blocTest<HomeCubit, HomeState>(
      'concurrent loads share one request',
      setUp: () => when(repository.home).thenAnswer((_) async => issuer),
      build: () => HomeCubit(home: repository),
      act: (cubit) => Future.wait([cubit.load(), cubit.refresh()]),
      expect: () => [HomeLoaded(issuer)],
      verify: (_) => verify(repository.home).called(1),
    );

    test('notification.created refetches once after the debounce', () async {
      when(repository.home).thenAnswer((_) async => issuer);
      final created = StreamController<Object?>.broadcast();
      final cubit = HomeCubit(
        home: repository,
        notifications: created.stream,
        debounce: const Duration(milliseconds: 20),
      );
      await cubit.load();
      created
        ..add({'notification': <String, Object?>{}, 'unread_count': 1})
        ..add({'notification': <String, Object?>{}, 'unread_count': 2});
      await Future<void>.delayed(const Duration(milliseconds: 60));
      verify(repository.home).called(2);
      await cubit.close();
      await created.close();
    });

    test('Home parses the alerts and days_left of the API', () {
      final alert = HomeAlert.fromJson({
        'code': 'subscription_expiring',
        'params': {'days_left': 3},
      });
      expect(alert.daysLeft, 3);
      // API mismatch A-1: an empty `params` is `[]`.
      expect(
        HomeAlert.fromJson({'code': 'plan_required', 'params': <Object>[]})
            .params,
        isEmpty,
      );
    });
  });
}
