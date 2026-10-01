import 'package:bafo/core/api/json.dart';
import 'package:equatable/equatable.dart';

enum CommentAuthorKind implements WireEnum {
  issuer('issuer'),
  participant('participant'),

  /// The viewer's own organisation (render «أنتم» / "You").
  me('me'),
  unknown('unknown');

  const CommentAuthorKind(this.wire);

  @override
  final String wire;

  static CommentAuthorKind parse(Object? raw) => parseWire(values, raw, unknown);
}

/// The `author` projection (API.md §2.7): other participants appear by alias
/// only; [organizationName] is set only where the server includes it.
final class CommentAuthor extends Equatable {
  const CommentAuthor({required this.kind, this.aliasNo, this.organizationName});

  factory CommentAuthor.fromJson(Json json) => CommentAuthor(
    kind: CommentAuthorKind.parse(json['kind']),
    aliasNo: json.intOrNull('alias_no'),
    organizationName: json.strOrNull('organization_name'),
  );

  final CommentAuthorKind kind;
  final int? aliasNo;
  final String? organizationName;

  @override
  List<Object?> get props => [kind, aliasNo, organizationName];
}

/// A Q&A question, announcement or reply.
final class Comment extends Equatable {
  const Comment({
    required this.id,
    required this.body,
    required this.author,
    required this.createdAt,
    this.parentId,
    this.replies = const [],
  });

  factory Comment.fromJson(Json json) => Comment(
    id: json.str('id'),
    parentId: json.strOrNull('parent_id'),
    body: json.str('body'),
    author: CommentAuthor.fromJson(json.obj('author')),
    createdAt: json.date('created_at'),
    replies: json.list('replies', Comment.fromJson),
  );

  final String id;

  /// Null for top-level comments.
  final String? parentId;
  final String body;
  final CommentAuthor author;
  final DateTime createdAt;

  /// Oldest first.
  final List<Comment> replies;

  Comment withReplies(List<Comment> replies) => Comment(
    id: id,
    parentId: parentId,
    body: body,
    author: author,
    createdAt: createdAt,
    replies: replies,
  );

  @override
  List<Object?> get props => [id, parentId, body, author, createdAt, replies];
}
