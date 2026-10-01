import 'package:equatable/equatable.dart';

/// The result of a user action, for a one-off toast. [id] keeps two equal
/// results apart, so the listener fires for each.
final class ActionOutcome<K extends Enum> extends Equatable {
  const ActionOutcome(this.kind, this.id);

  final K kind;
  final int id;

  @override
  List<Object?> get props => [kind, id];
}

/// Hands out increasing outcome ids.
mixin OutcomeIds {
  int _lastOutcomeId = 0;

  ActionOutcome<K> outcome<K extends Enum>(K kind) =>
      ActionOutcome(kind, ++_lastOutcomeId);
}
