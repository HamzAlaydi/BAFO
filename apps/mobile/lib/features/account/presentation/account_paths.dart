import 'package:bafo/core/router/app_router.dart';

/// The account tab's paths (SCREENS.md §3.1 route tree, M51–M61). The plan
/// status (M58) is the root-level `/billing`.
abstract final class AccountPaths {
  static const String root = AppRoutes.account;
  static const String profile = '$root/profile';
  static const String password = '$root/password';
  static const String organization = '$root/organization';
  static const String team = '$root/team';
  static const String teamNew = '$team/new';
  static String teamMember(String membershipId) => '$team/$membershipId';
  static const String invoices = '$root/invoices';
  static const String settings = '$root/settings';
  static const String help = '$root/help';
  static const String deleteAccount = '$root/delete-account';
}
