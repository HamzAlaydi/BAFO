import 'dart:async';

import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/models/lookups.dart';

/// `GET /lookups` (API.md §1.2): regions, categories, close reasons and
/// presets, cached per language with the server's `ETag`.
///
/// Names are localised by `Accept-Language`, so each language has its own
/// cache entry; switching language reads (or revalidates) the other one.
abstract interface class LookupsRepository {
  /// The lookups in the current language. Served from memory when cached;
  /// [refresh] revalidates with `If-None-Match` (a 304 keeps the cache).
  Future<Lookups> lookups({bool refresh = false});

  /// Close reasons of [kind] (from [lookups]).
  Future<List<CloseReason>> closeReasons(CloseReasonKind kind);
}

final class ApiLookupsRepository implements LookupsRepository {
  ApiLookupsRepository(this._api, {required this._languageCode});

  final ApiClient _api;
  final String Function() _languageCode;
  final Map<String, _CachedLookups> _cache = {};
  final Map<String, Future<Lookups>> _inFlight = {};

  @override
  Future<Lookups> lookups({bool refresh = false}) {
    final language = _languageCode();
    final cached = _cache[language];
    if (cached != null && !refresh) return Future.value(cached.lookups);
    // A block body: returning the removed future would make whenComplete
    // wait on itself.
    return _inFlight[language] ??= _fetch(language, cached).whenComplete(() {
      _inFlight.remove(language);
    });
  }

  Future<Lookups> _fetch(String language, _CachedLookups? cached) async {
    final response = await _api.get(
      'lookups',
      // Accept-Language comes from the interceptor (the same language).
      headers: {if (cached?.etag != null) 'If-None-Match': cached!.etag!},
      allowNotModified: cached != null,
    );
    if (response.notModified && cached != null) return cached.lookups;
    final lookups = Lookups.fromJson(response.dataMap);
    _cache[language] = _CachedLookups(lookups, response.header('etag'));
    return lookups;
  }

  @override
  Future<List<CloseReason>> closeReasons(CloseReasonKind kind) async =>
      (await lookups()).closeReasonsOf(kind);
}

final class _CachedLookups {
  const _CachedLookups(this.lookups, this.etag);

  final Lookups lookups;
  final String? etag;
}
