import 'package:bafo/core/api/json.dart';
import 'package:bafo/core/models/me.dart';
import 'package:equatable/equatable.dart';

/// `Membership` with its user (API.md §2.3), a row of the team list.
final class TeamMember extends Equatable {
  const TeamMember({
    required this.id,
    required this.role,
    required this.status,
    required this.user,
    this.canAward = false,
    this.canPurchase = false,
    this.joinedAt,
    this.invitedAt,
  });

  factory TeamMember.fromJson(Json json) => TeamMember(
    id: json.str('id'),
    role: MembershipRole.parse(json['role']),
    canAward: json.flag('can_award'),
    canPurchase: json.flag('can_purchase'),
    status: MembershipStatus.parse(json['status']),
    joinedAt: json.dateOrNull('joined_at'),
    invitedAt: json.dateOrNull('invited_at'),
    user: User.fromJson(json.obj('user')),
  );

  final String id;
  final MembershipRole role;
  final bool canAward;
  final bool canPurchase;
  final MembershipStatus status;
  final DateTime? joinedAt;
  final DateTime? invitedAt;
  final User user;

  bool get isOwner => role == MembershipRole.owner;

  @override
  List<Object?> get props => [
    id,
    role,
    canAward,
    canPurchase,
    status,
    joinedAt,
    invitedAt,
    user,
  ];
}

/// Seats in use and available (`meta.seats`, `details.seats`).
final class Seats extends Equatable {
  const Seats({required this.used, required this.total});

  factory Seats.fromJson(Json json) =>
      Seats(used: json.intOrNull('used') ?? 0, total: json.intOrNull('total') ?? 0);

  final int used;
  final int total;

  bool get full => used >= total;

  @override
  List<Object?> get props => [used, total];
}

/// `GET /team/members`: every member (≤ 50, not paginated) and the seats.
final class TeamRoster extends Equatable {
  const TeamRoster({required this.members, required this.seats});

  final List<TeamMember> members;
  final Seats seats;

  @override
  List<Object?> get props => [members, seats];
}

/// `POST /team/members`.
final class TeamMemberInvite extends Equatable {
  const TeamMemberInvite({
    required this.name,
    required this.email,
    required this.role,
    this.phone,
    this.canAward,
    this.canPurchase,
  });

  final String name;
  final String email;
  final String? phone;

  /// `admin` or `member`.
  final MembershipRole role;

  /// Null keeps the role default (admin: true; member: false).
  final bool? canAward;
  final bool? canPurchase;

  Json toJson() => {
    'name': name.trim(),
    'email': email.trim(),
    'phone': phone,
    'role': role.wire,
    'can_award': canAward,
    'can_purchase': canPurchase,
  };

  @override
  List<Object?> get props => [name, email, phone, role, canAward, canPurchase];
}
