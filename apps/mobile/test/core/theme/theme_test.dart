import 'package:bafo/core/theme/spacing.dart';
import 'package:bafo/core/theme/theme.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  test('FABs use the brand container, radius and a low elevation', () {
    final fab = BafoTheme.light('ar').floatingActionButtonTheme;

    expect(fab.backgroundColor, BafoTheme.lightScheme.primaryContainer);
    expect(fab.foregroundColor, BafoTheme.lightScheme.onPrimaryContainer);
    expect(
      fab.shape,
      RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(BafoRadii.card),
      ),
    );
    // Focus never adds a heavier shadow than the resting state.
    expect(fab.elevation, lessThanOrEqualTo(2));
    expect(fab.focusElevation, fab.elevation);
  });

  testWidgets('the team invite FAB renders without a dark ring', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: BafoTheme.light('ar'),
        home: Scaffold(
          floatingActionButton: FloatingActionButton.extended(
            onPressed: () {},
            icon: const Icon(Icons.person_add_alt_1_outlined),
            label: const Text('دعوة عضو'),
          ),
        ),
      ),
    );

    final material = tester.widget<Material>(
      find.descendant(
        of: find.byType(FloatingActionButton),
        matching: find.byType(Material),
      ),
    );
    expect(material.color, BafoTheme.lightScheme.primaryContainer);
    expect(material.elevation, lessThanOrEqualTo(2));
    expect(
      material.shape,
      RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(BafoRadii.card),
      ),
    );
  });
}
