import 'package:bafo/core/api/api_client.dart';
import 'package:bafo/core/api/json.dart';
import 'package:equatable/equatable.dart';

/// `meta.pagination` of a page-based app v1 list (API.md §0.5).
final class PageMeta extends Equatable {
  const PageMeta({
    required this.currentPage,
    required this.perPage,
    required this.hasMore,
    this.total,
    this.lastPage,
  });

  factory PageMeta.fromJson(Json json) => PageMeta(
    currentPage: json.intOrNull('current_page') ?? 1,
    perPage: json.intOrNull('per_page') ?? defaultPerPage,
    hasMore: json.flag('has_more'),
    total: json.intOrNull('total'),
    lastPage: json.intOrNull('last_page'),
  );

  /// A single page holding everything (lists the API does not paginate).
  const PageMeta.single(int count)
    : currentPage = 1,
      perPage = count,
      hasMore = false,
      total = count,
      lastPage = 1;

  /// CONVENTIONS.md §9.3: app lists use 20 per page.
  static const int defaultPerPage = 20;

  final int currentPage;
  final int perPage;
  final bool hasMore;
  final int? total;
  final int? lastPage;

  int get nextPage => currentPage + 1;

  @override
  List<Object?> get props => [currentPage, perPage, hasMore, total, lastPage];
}

/// One page of [items] plus its [meta].
final class Paged<T> extends Equatable {
  const Paged({required this.items, required this.meta});

  /// Parses `data` (a list) and `meta.pagination` of [response].
  factory Paged.fromResponse(
    ApiResponse response,
    T Function(Json json) parse,
  ) {
    final items = response.dataList.map(parse).toList(growable: false);
    final pagination = response.meta['pagination'];
    return Paged(
      items: items,
      meta: pagination is Json
          ? PageMeta.fromJson(pagination)
          : PageMeta.single(items.length),
    );
  }

  final List<T> items;
  final PageMeta meta;

  bool get hasMore => meta.hasMore;

  @override
  List<Object?> get props => [items, meta];
}

/// Query parameters for page-based lists.
Map<String, Object> pageQuery(int page, int perPage) => {
  'page': page,
  'per_page': perPage,
};
