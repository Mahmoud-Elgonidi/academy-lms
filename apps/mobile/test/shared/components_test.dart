import 'dart:async';

import 'package:academy_app/app/router/transitions.dart';
import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/app/theme/branding.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/core/network/api_problem.dart';
import 'package:academy_app/shared/widgets/lms.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

/// A screen in the classic look, in Arabic, around [child].
Widget _screen(Widget child, {Stage stage = Stage.classic}) => MaterialApp(
  theme: buildTheme(Branding.fallback, stage),
  locale: const Locale('ar'),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: child),
);

void main() {
  testWidgets('a menu row shows its arrow only when it opens something, and the arrow turns with Arabic', (tester) async {
    await tester.pumpWidget(
      _screen(
        MenuGroup(
          title: 'التواصل',
          children: [
            MenuRow(label: 'الرسايل', icon: Icons.chat_bubble_outline_rounded, count: 2, onTap: () {}),
            const MenuRow(label: 'رقم الإصدار', value: '1.4.0'),
          ],
        ),
      ),
    );
    expect(find.byIcon(Icons.chevron_right_rounded), findsOneWidget);
    expect(Icons.chevron_right_rounded.matchTextDirection, isTrue, reason: 'one icon for both directions: no hand-made flip');
    expect(find.text('2'), findsOneWidget);
    expect(find.text('التواصل'), findsOneWidget);
    expect(find.descendant(of: find.widgetWithText(MenuRow, 'رقم الإصدار'), matching: find.byType(InkWell)), findsNothing);
  });

  testWidgets('a number that opens nothing does not look pressable', (tester) async {
    await tester.pumpWidget(
      _screen(
        const Column(
          children: [StatTile(label: 'في الرصيد', value: Text('2'))],
        ),
      ),
    );
    expect(find.byType(InkWell), findsNothing);
    expect(find.byIcon(Icons.chevron_right_rounded), findsNothing);

    var opened = false;
    await tester.pumpWidget(_screen(StatTile(label: 'مستني تصحيح', value: const Text('3'), onTap: () => opened = true)));
    await tester.tap(find.text('3'));
    expect(opened, isTrue);
    expect(find.byIcon(Icons.chevron_right_rounded), findsOneWidget);
  });

  testWidgets('a sheet opens over the tabs, on the root navigator', (tester) async {
    late BuildContext inner;
    await tester.pumpWidget(
      _screen(
        Navigator(
          onGenerateRoute: (_) => MaterialPageRoute<void>(
            builder: (c) {
              inner = c;
              return const Text('tab');
            },
          ),
        ),
      ),
    );
    final root = Navigator.of(inner, rootNavigator: true);
    expect(Navigator.of(inner), isNot(same(root)), reason: 'the tab has its own navigator');
    late BuildContext sheet;
    unawaited(
      showActionSheet<void>(
        inner,
        title: 'اختار',
        builder: (c) {
          sheet = c;
          return const Text('inside');
        },
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('inside'), findsOneWidget);
    expect(find.text('اختار'), findsOneWidget);
    expect(Navigator.of(sheet), same(root));
  });

  testWidgets('asking before something final: keeping it answers no, confirming answers yes', (tester) async {
    late BuildContext context;
    await tester.pumpWidget(
      _screen(
        Builder(
          builder: (c) {
            context = c;
            return const SizedBox();
          },
        ),
      ),
    );
    var answer = confirmDestructive(context, title: 'تلغي الحصة؟', confirm: 'أيوه، ألغيها');
    await tester.pumpAndSettle();
    expect(find.text('تلغي الحصة؟'), findsOneWidget);
    await tester.tap(find.text('مش دلوقتي'));
    await tester.pumpAndSettle();
    expect(await answer, isFalse);

    answer = confirmDestructive(context, title: 'تلغي الحصة؟', confirm: 'أيوه، ألغيها');
    await tester.pumpAndSettle();
    final confirm = tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'أيوه، ألغيها'));
    final red = confirm.style?.backgroundColor?.resolve({});
    expect(red, buildTheme(Branding.fallback, Stage.classic).extension<LmsColors>()!.danger);
    await tester.tap(find.text('أيوه، ألغيها'));
    await tester.pumpAndSettle();
    expect(await answer, isTrue);
  });

  testWidgets('an error says what kind it is, and a forbidden one offers no retry', (tester) async {
    var retried = 0;
    await tester.pumpWidget(_screen(ErrorState.of(ApiProblem.offline(), onRetry: () => retried++)));
    expect(find.text('مفيش نت'), findsOneWidget);
    await tester.tap(find.text('جرّب تاني'));
    expect(retried, 1);

    await tester.pumpWidget(
      _screen(
        ErrorState.of(
          const ApiProblem(type: 'server', title: 'Server Error', status: 500),
          onRetry: () {},
        ),
      ),
    );
    expect(find.text('حصلت مشكلة عندنا'), findsOneWidget);

    await tester.pumpWidget(
      _screen(
        ErrorState.of(
          const ApiProblem(type: 'forbidden', title: '', status: 403),
          onRetry: () {},
        ),
      ),
    );
    expect(find.text('مش مسموح تشوف ده'), findsOneWidget);
    expect(find.text('جرّب تاني'), findsNothing);

    await tester.pumpWidget(
      _screen(
        ErrorState.of(
          const ApiProblem(type: 'x', title: 'الحصة دي اتلغت.', status: 409),
          onRetry: () {},
        ),
      ),
    );
    expect(find.text('الحصة دي اتلغت.'), findsOneWidget);
  });

  testWidgets('a skeleton stands still when the phone asks for less motion', (tester) async {
    tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
    addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
    await tester.pumpWidget(_screen(const SkeletonList()));
    expect(find.byType(Skeleton), findsWidgets);
    expect(tester.hasRunningAnimations, isFalse);
  });

  test('the kids\' look keeps Material\'s own finish for lists, dialogs, badges and icon buttons', () {
    final kids = buildTheme(Branding.fallback, Stage.kids);
    expect(kids.listTileTheme, const ListTileThemeData());
    expect(kids.dialogTheme, const DialogThemeData());
    expect(kids.badgeTheme, const BadgeThemeData());
    expect(kids.iconButtonTheme, const IconButtonThemeData());
    expect(kids.segmentedButtonTheme, const SegmentedButtonThemeData());
    // The others share one finish.
    final classic = buildTheme(Branding.fallback, Stage.classic);
    expect(classic.iconButtonTheme.style?.minimumSize?.resolve({}), const Size(48, 48));
    expect(classic.badgeTheme.largeSize, 17);
  });

  testWidgets('on an iPhone a pushed screen slides in the platform\'s way, so a swipe goes back', (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          pageBuilder: (c, s) => fadeScalePage(s, const Text('home')),
          routes: [GoRoute(path: 'next', pageBuilder: (c, s) => fadeScalePage(s, const Text('next')))],
        ),
      ],
    );
    await tester.pumpWidget(MaterialApp.router(routerConfig: router));
    unawaited(router.push<void>('/next'));
    await tester.pumpAndSettle();
    expect(ModalRoute.of(tester.element(find.text('next'))), isA<CupertinoRouteTransitionMixin<dynamic>>());
    debugDefaultTargetPlatformOverride = null;
  });
}
