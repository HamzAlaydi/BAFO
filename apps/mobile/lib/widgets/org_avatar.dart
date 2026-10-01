import 'package:cached_network_image/cached_network_image.dart';
import 'package:material_ui/material_ui.dart';

/// An organisation's logo, or its initials when there is no logo (or it fails
/// to load). Works for Arabic and Latin names.
///
/// Participants never see other participants' identities: use this for the
/// user's own organisation and for issuers only.
///
/// Screen readers hear [name] once: pass [decorative] when the name is also
/// shown as text next to the avatar.
class OrgAvatar extends StatelessWidget {
  const OrgAvatar({
    required this.name,
    this.logoUrl,
    this.size = 40,
    this.decorative = false,
    super.key,
  });

  final String name;
  final String? logoUrl;
  final double size;

  /// Excluded from semantics (the name is read from the text beside it).
  final bool decorative;

  /// Up to two initials: the first letter of the first two words.
  static String initialsOf(String name) {
    final words = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .toList();
    if (words.isEmpty) return '';
    final letters = words
        .take(2)
        .map((word) => String.fromCharCode(word.runes.first));
    return letters.join().toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fallback = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      color: scheme.primaryContainer,
      child: Text(
        initialsOf(name),
        style: TextStyle(
          color: scheme.onPrimaryContainer,
          fontSize: size * 0.38,
          fontWeight: FontWeight.w600,
          height: 1,
        ),
      ),
    );
    final url = logoUrl;

    final avatar = ClipOval(
      child: url == null || url.isEmpty
          ? fallback
          : CachedNetworkImage(
              imageUrl: url,
              width: size,
              height: size,
              fit: BoxFit.cover,
              placeholder: (_, _) => fallback,
              errorWidget: (_, _, _) => fallback,
            ),
    );
    if (decorative) return ExcludeSemantics(child: avatar);
    return Semantics(
      label: name,
      image: true,
      excludeSemantics: true,
      child: avatar,
    );
  }
}
