import 'package:bafo/core/theme/semantic_colors.dart';
import 'package:bafo/core/theme/spacing.dart';
import 'package:flutter/gestures.dart';
import 'package:intl/intl.dart' show Bidi;
import 'package:markdown/markdown.dart' as md;
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

/// Renders server Markdown (legal documents, M13) with the BAFO text theme.
///
/// Raw HTML is never rendered (it shows as text) and only `https` links are
/// opened, in the browser. Supports headings, paragraphs, emphasis, lists,
/// block quotes, rules, inline and block code, and simple tables. Each
/// paragraph and heading takes the direction of its own text, so an English
/// line in an Arabic document keeps its punctuation in place.
class MarkdownView extends StatefulWidget {
  const MarkdownView({required this.data, super.key});

  final String data;

  @override
  State<MarkdownView> createState() => _MarkdownViewState();
}

class _MarkdownViewState extends State<MarkdownView> {
  final List<TapGestureRecognizer> _recognizers = [];
  late List<md.Node> _nodes = _parse(widget.data);

  static List<md.Node> _parse(String data) => md.Document(
    extensionSet: md.ExtensionSet.gitHubFlavored,
    encodeHtml: false,
  ).parse(data.replaceAll('\r\n', '\n'));

  @override
  void didUpdateWidget(MarkdownView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.data != widget.data) _nodes = _parse(widget.data);
  }

  @override
  void dispose() {
    _disposeRecognizers();
    super.dispose();
  }

  void _disposeRecognizers() {
    for (final recognizer in _recognizers) {
      recognizer.dispose();
    }
    _recognizers.clear();
  }

  @override
  Widget build(BuildContext context) {
    _disposeRecognizers();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [for (final node in _nodes) ..._block(context, node)],
    );
  }

  List<Widget> _block(BuildContext context, md.Node node, {int depth = 0}) {
    final theme = Theme.of(context);
    final text = theme.textTheme;
    if (node is md.Text) {
      final value = node.text.trim();
      return value.isEmpty
          ? const []
          : [_paragraph(Text(value, style: text.bodyLarge))];
    }
    if (node is! md.Element) return const [];

    switch (node.tag) {
      case 'h1' || 'h2' || 'h3' || 'h4' || 'h5' || 'h6':
        final style = switch (node.tag) {
          'h1' => text.headlineSmall,
          'h2' => text.titleLarge,
          'h3' => text.titleMedium,
          _ => text.titleSmall,
        };
        return [
          Padding(
            padding: const EdgeInsetsDirectional.only(
              top: BafoSpacing.lg,
              bottom: BafoSpacing.sm,
            ),
            child: Semantics(
              header: true,
              child: Text.rich(
                _inline(context, node.children, style),
                textDirection: _directionOf(node.textContent),
              ),
            ),
          ),
        ];
      case 'p':
        return [
          _paragraph(
            Text.rich(
              _inline(context, node.children, text.bodyLarge),
              textDirection: _directionOf(node.textContent),
            ),
          ),
        ];
      case 'ul' || 'ol':
        final ordered = node.tag == 'ol';
        final start = int.tryParse(node.attributes['start'] ?? '') ?? 1;
        final items = (node.children ?? const <md.Node>[])
            .whereType<md.Element>()
            .toList();
        return [
          for (final (index, item) in items.indexed)
            Padding(
              padding: EdgeInsetsDirectional.only(
                start: BafoSpacing.lg * depth,
                bottom: BafoSpacing.xs,
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 24,
                    child: Text(
                      ordered ? '${start + index}.' : '•',
                      style: text.bodyLarge,
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: _listItem(context, item, depth),
                    ),
                  ),
                ],
              ),
            ),
        ];
      case 'blockquote':
        return [
          Container(
            margin: const EdgeInsetsDirectional.only(bottom: BafoSpacing.md),
            padding: const EdgeInsetsDirectional.only(start: BafoSpacing.md),
            decoration: BoxDecoration(
              border: BorderDirectional(
                start: BorderSide(
                  color: theme.colorScheme.outlineVariant,
                  width: 3,
                ),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final child in node.children ?? const <md.Node>[])
                  ..._block(context, child, depth: depth),
              ],
            ),
          ),
        ];
      case 'hr':
        return const [Divider(height: BafoSpacing.xl)];
      case 'pre':
        return [
          Container(
            margin: const EdgeInsetsDirectional.only(bottom: BafoSpacing.md),
            padding: const EdgeInsetsDirectional.all(BafoSpacing.md),
            color: theme.colorScheme.surfaceContainerLow,
            child: Directionality(
              textDirection: TextDirection.ltr,
              child: Text(
                node.textContent,
                style: text.bodySmall?.copyWith(fontFamily: 'monospace'),
              ),
            ),
          ),
        ];
      case 'table':
        return [_table(context, node)];
      default:
        final children = node.children;
        if (children == null) return const [];
        return [
          for (final child in children) ..._block(context, child, depth: depth),
        ];
    }
  }

  List<Widget> _listItem(BuildContext context, md.Element item, int depth) {
    final children = item.children ?? const <md.Node>[];
    final inline = <md.Node>[];
    final widgets = <Widget>[];
    void flush() {
      if (inline.isEmpty) return;
      widgets.add(
        Text.rich(
          _inline(context, List.of(inline), Theme.of(context).textTheme.bodyLarge),
        ),
      );
      inline.clear();
    }

    for (final child in children) {
      if (child is md.Element &&
          const {'ul', 'ol', 'p', 'blockquote', 'pre'}.contains(child.tag)) {
        flush();
        widgets.addAll(
          child.tag == 'p'
              ? [
                  Text.rich(
                    _inline(
                      context,
                      child.children,
                      Theme.of(context).textTheme.bodyLarge,
                    ),
                  ),
                ]
              : _block(context, child, depth: depth + 1),
        );
      } else {
        inline.add(child);
      }
    }
    flush();
    return widgets;
  }

  /// RTL when the text is mostly Arabic, else LTR (a block in the other
  /// language keeps its own punctuation and alignment).
  static TextDirection _directionOf(String text) =>
      Bidi.detectRtlDirectionality(text)
          ? TextDirection.rtl
          : TextDirection.ltr;

  Widget _paragraph(Widget child) => Padding(
    padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.md),
    child: child,
  );

  Widget _table(BuildContext context, md.Element table) {
    final rows = <md.Element>[];
    void collect(md.Node node) {
      if (node is md.Element) {
        if (node.tag == 'tr') {
          rows.add(node);
        } else {
          node.children?.forEach(collect);
        }
      }
    }

    collect(table);
    final text = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsetsDirectional.only(bottom: BafoSpacing.md),
      child: Table(
        border: TableBorder.all(color: Theme.of(context).colorScheme.outlineVariant),
        children: [
          for (final row in rows)
            TableRow(
              children: [
                for (final cell in row.children?.whereType<md.Element>() ??
                    const <md.Element>[])
                  Padding(
                    padding: const EdgeInsetsDirectional.all(BafoSpacing.sm),
                    child: Text.rich(
                      _inline(
                        context,
                        cell.children,
                        cell.tag == 'th'
                            ? text.titleSmall
                            : text.bodyMedium,
                      ),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }

  InlineSpan _inline(
    BuildContext context,
    List<md.Node>? nodes,
    TextStyle? style,
  ) {
    final spans = <InlineSpan>[];
    for (final node in nodes ?? const <md.Node>[]) {
      if (node is md.Text) {
        spans.add(TextSpan(text: node.text));
        continue;
      }
      if (node is! md.Element) continue;
      switch (node.tag) {
        case 'strong':
          spans.add(
            _inline(
              context,
              node.children,
              (style ?? const TextStyle()).copyWith(fontWeight: FontWeight.w700),
            ),
          );
        case 'em':
          spans.add(
            _inline(
              context,
              node.children,
              (style ?? const TextStyle()).copyWith(fontStyle: FontStyle.italic),
            ),
          );
        case 'del':
          spans.add(
            _inline(
              context,
              node.children,
              (style ?? const TextStyle()).copyWith(
                decoration: TextDecoration.lineThrough,
              ),
            ),
          );
        case 'code':
          spans.add(
            TextSpan(
              text: node.textContent,
              style: (style ?? const TextStyle()).copyWith(
                fontFamily: 'monospace',
                backgroundColor: Theme.of(context).colorScheme.surfaceContainer,
              ),
            ),
          );
        case 'br':
          spans.add(const TextSpan(text: '\n'));
        case 'a':
          final href = node.attributes['href'] ?? '';
          final uri = Uri.tryParse(href);
          final recognizer = uri != null && uri.scheme == 'https'
              ? (TapGestureRecognizer()
                  ..onTap = () =>
                      launchUrl(uri, mode: LaunchMode.externalApplication))
              : null;
          if (recognizer != null) _recognizers.add(recognizer);
          spans.add(
            TextSpan(
              text: node.textContent,
              recognizer: recognizer,
              style: (style ?? const TextStyle()).copyWith(
                color: context.semanticColors.link,
                decoration: TextDecoration.underline,
              ),
            ),
          );
        default:
          spans.add(_inline(context, node.children, style));
      }
    }
    return TextSpan(style: style, children: spans);
  }
}
