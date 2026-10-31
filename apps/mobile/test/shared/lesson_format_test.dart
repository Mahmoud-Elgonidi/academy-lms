import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/app/theme/branding.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/shared/widgets/lms.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

Widget _screen(Widget child) => MaterialApp(
  theme: buildTheme(Branding.fallback, Stage.classic),
  locale: const Locale('ar'),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: Center(child: child)),
);

void main() {
  testWidgets('a group pill says «جماعية» with its seats as dots, taken first, and is read out whole', (tester) async {
    final semantics = tester.ensureSemantics();
    await tester.pumpWidget(_screen(const GroupPill(taken: 3, capacity: 5)));

    expect(find.text('جماعية'), findsOneWidget);
    expect(find.text('3/5'), findsOneWidget);
    expect(find.byIcon(Icons.groups_rounded), findsOneWidget);
    expect(find.byWidgetPredicate((w) => w is SeatDot && w.taken), findsNWidgets(3));
    expect(find.byWidgetPredicate((w) => w is SeatDot && !w.taken), findsNWidgets(2));
    expect(find.bySemanticsLabel('حصة جماعية، المقاعد المشغولة 3 من 5'), findsOneWidget);

    // Its colours are the academy's info colours, outlined, apart from a lesson's state.
    final box = tester.widget<Container>(find.byKey(const Key('group-pill'))).decoration! as BoxDecoration;
    final context = tester.element(find.byType(GroupPill));
    expect(box.color, context.colors.infoSoft);
    expect(box.border, isNotNull);
    semantics.dispose();
  });

  testWidgets('a big group reads as the count alone, and a one-to-one lesson is said plainly', (tester) async {
    await tester.pumpWidget(
      _screen(const Column(mainAxisSize: MainAxisSize.min, children: [GroupPill(taken: 12, capacity: 20), PrivatePill()])),
    );

    expect(find.text('12/20'), findsOneWidget);
    expect(find.byKey(const Key('seat-dots')), findsNothing);
    expect(find.text('فردية'), findsOneWidget);
    expect(find.descendant(of: find.byType(PrivatePill), matching: find.byType(Icon)), findsNothing);
  });

  testWidgets('seat dots fill as many as are taken, and never more than the seats', (tester) async {
    await tester.pumpWidget(_screen(const SeatDots(taken: 4, capacity: 4, color: Colors.black)));
    expect(find.byWidgetPredicate((w) => w is SeatDot && w.taken), findsNWidgets(4));
    expect(find.byWidgetPredicate((w) => w is SeatDot && !w.taken), findsNothing);
  });
}
