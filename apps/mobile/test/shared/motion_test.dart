import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/app/theme/branding.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/shared/motion/bell_ring.dart';
import 'package:academy_app/shared/motion/done_moment.dart';
import 'package:academy_app/shared/motion/juice.dart';
import 'package:academy_app/shared/motion/motion.dart';
import 'package:academy_app/shared/motion/motion_list.dart';
import 'package:academy_app/shared/widgets/lms.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

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
  home: Scaffold(body: Center(child: child)),
);

void _still(WidgetTester tester) {
  tester.platformDispatcher.accessibilityFeaturesTestValue = const FakeAccessibilityFeatures(disableAnimations: true);
  addTearDown(tester.platformDispatcher.clearAccessibilityFeaturesTestValue);
}

/// Sounds played and buzzes felt while [body] runs.
Future<(List<Sound>, int)> _felt(WidgetTester tester, Future<void> Function() body) async {
  final played = <Sound>[];
  var buzzes = 0;
  Juice.player = (s) async => played.add(s);
  tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
    if (call.method == 'HapticFeedback.vibrate') buzzes++;
    return null;
  });
  addTearDown(() {
    Juice.player = null;
    Juice.sounds = true;
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null);
  });
  await body();
  return (played, buzzes);
}

void main() {
  testWidgets('a card that opens something gives under the finger and ticks; one that does not, does neither', (tester) async {
    var opened = 0;
    final (played, buzzes) = await _felt(tester, () async {
      await tester.pumpWidget(_screen(LmsCard(onTap: () => opened++, child: const Text('الحصة القادمة'))));
      final press = await tester.startGesture(tester.getCenter(find.text('الحصة القادمة')));
      await tester.pump(const Duration(milliseconds: 150));
      expect(tester.widget<AnimatedScale>(find.byType(AnimatedScale)).scale, lessThan(1));
      await press.up();
      await tester.pumpAndSettle();
      expect(tester.widget<AnimatedScale>(find.byType(AnimatedScale)).scale, 1);
    });
    expect(opened, 1);
    expect(played, [Sound.tapSoft]);
    expect(buzzes, 1);

    await tester.pumpWidget(_screen(const LmsCard(child: Text('بس للعرض'))));
    expect(find.byType(PressScale), findsNothing);
  });

  testWidgets('the kids\' cards stay as they were: no give and no tick', (tester) async {
    await tester.pumpWidget(
      _screen(
        LmsCard(onTap: () {}, child: const Text('x')),
        stage: Stage.kids,
      ),
    );
    expect(find.byType(PressScale), findsNothing);
  });

  testWidgets('sounds off means no sound and no buzz either', (tester) async {
    final (played, buzzes) = await _felt(tester, () async {
      Juice.sounds = false;
      await tester.pumpWidget(_screen(MenuRow(label: 'الرسائل', onTap: () {})));
      await tester.tap(find.text('الرسائل'));
      await tester.pumpAndSettle();
    });
    expect(played, isEmpty);
    expect(buzzes, 0);
  });

  testWidgets('under less motion a press does not move', (tester) async {
    _still(tester);
    await tester.pumpWidget(_screen(PressScale(onTap: () {}, child: const Text('ادخل'))));
    final press = await tester.startGesture(tester.getCenter(find.text('ادخل')));
    await tester.pump(const Duration(milliseconds: 150));
    expect(tester.widget<AnimatedScale>(find.byType(AnimatedScale)).scale, 1);
    await press.up();
  });

  testWidgets('a scroll that starts on a card lets go of it', (tester) async {
    await tester.pumpWidget(
      _screen(
        PressScale(
          child: SizedBox(width: 200, height: 80, child: InkWell(onTap: () {})),
        ),
      ),
    );
    final drag = await tester.startGesture(tester.getCenter(find.byType(InkWell)));
    await tester.pump(const Duration(milliseconds: 150));
    await drag.moveBy(const Offset(0, 40));
    await tester.pumpAndSettle();
    expect(tester.widget<AnimatedScale>(find.byType(AnimatedScale)).scale, 1);
    await drag.up();
  });

  testWidgets('something done: a check, the title read aloud, and then it goes by itself', (tester) async {
    final (played, _) = await _felt(tester, () async {
      await tester.pumpWidget(
        _screen(
          Builder(
            builder: (c) => TextButton(
              onPressed: () => DoneMoment.show(c, title: 'الإيصال اتبعت!'),
              child: const Text('أرسل'),
            ),
          ),
        ),
      );
      await tester.tap(find.text('أرسل'));
      await tester.pump(const Duration(milliseconds: 400));
      expect(find.text('الإيصال اتبعت!'), findsOneWidget);
      // Taps go through to the screen under it.
      expect(find.ancestor(of: find.text('الإيصال اتبعت!'), matching: find.byType(IgnorePointer)), findsWidgets);
      await tester.pump(DoneMoment.lengthFor(MotionFlavour.playful));
      await tester.pump(const Duration(seconds: 3));
      expect(find.text('الإيصال اتبعت!'), findsNothing);
    });
    expect(played, [Sound.success]);
  });

  testWidgets('a teen\'s moment is calm: a softer note', (tester) async {
    final (played, _) = await _felt(tester, () async {
      await tester.pumpWidget(
        _screen(
          Builder(
            builder: (c) => TextButton(
              onPressed: () => DoneMoment.show(c, title: 'الواجب اتسلّم'),
              child: const Text('سلّم'),
            ),
          ),
          stage: Stage.teen,
        ),
      );
      await tester.tap(find.text('سلّم'));
      await tester.pump(const Duration(seconds: 4));
    });
    expect(played, [Sound.coinSoft]);
  });

  testWidgets('under less motion the moment still says it, standing still, and goes', (tester) async {
    _still(tester);
    await tester.pumpWidget(
      _screen(
        Builder(
          builder: (c) => TextButton(
            onPressed: () => DoneMoment.show(c, title: 'اتلغت'),
            child: const Text('الغي'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('الغي'));
    await tester.pump();
    expect(find.text('اتلغت'), findsOneWidget);
    expect(tester.widget<Opacity>(find.ancestor(of: find.text('اتلغت'), matching: find.byType(Opacity)).first).opacity, 1);
    await tester.pump(const Duration(seconds: 2));
    expect(find.text('اتلغت'), findsNothing);
  });

  testWidgets('a number counts up, with decimals and its own words', (tester) async {
    await tester.pumpWidget(_screen(CountUp(758.5, decimals: 1, format: (v) => '${v.toStringAsFixed(1)} ر.س')));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('758.5 ر.س'), findsNothing);
    await tester.pumpAndSettle();
    expect(find.text('758.5 ر.س'), findsOneWidget);

    _still(tester);
    await tester.pumpWidget(_screen(const CountUp(12, suffix: ' حصة')));
    expect(find.text('12 حصة'), findsOneWidget);
  });

  testWidgets('a list opens a row in place and closes one that leaves', (tester) async {
    Widget list(List<String> items) => _screen(
      SizedBox(
        height: 400,
        child: MotionList<String>(items: items, idOf: (s) => s, itemBuilder: (c, s) => Text(s)),
      ),
    );
    await tester.pumpWidget(list(['واجب', 'حصة']));
    await tester.pumpWidget(list(['رسالة', 'واجب', 'حصة']));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.byType(SizeTransition), findsWidgets);
    await tester.pumpAndSettle();
    expect(find.text('رسالة'), findsOneWidget);

    await tester.pumpWidget(list(['رسالة', 'حصة']));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('واجب'), findsOneWidget, reason: 'still closing');
    await tester.pumpAndSettle();
    expect(find.text('واجب'), findsNothing);
    expect(tester.getTopLeft(find.text('رسالة')).dy, lessThan(tester.getTopLeft(find.text('حصة')).dy));
  });

  testWidgets('the bell rings when something new comes, not when things are read', (tester) async {
    Widget bell(int n) => _screen(BellRing(count: n, child: const Icon(Icons.notifications_none_rounded)));
    double angle() => tester
        .widget<Transform>(find.descendant(of: find.byType(BellRing), matching: find.byType(Transform)))
        .transform
        .getRotation()
        .entry(1, 0);
    await tester.pumpWidget(bell(1));
    await tester.pumpWidget(bell(2));
    await tester.pump(const Duration(milliseconds: 60));
    expect(angle(), isNot(0));
    await tester.pumpAndSettle();
    await tester.pumpWidget(bell(0));
    await tester.pump(const Duration(milliseconds: 60));
    expect(angle(), 0);
  });

  testWidgets('each look moves its own way: families bounce, middle school springs, teens stay calm', (tester) async {
    late MotionFlavour family;
    await tester.pumpWidget(
      _screen(
        Builder(
          builder: (c) {
            family = c.flavour;
            return const SizedBox();
          },
        ),
      ),
    );
    expect(family, MotionFlavour.playful);
    for (final (stage, want) in [
      (Stage.kids, MotionFlavour.kids),
      (Stage.middle, MotionFlavour.lively),
      (Stage.teen, MotionFlavour.calm),
    ]) {
      late MotionFlavour got;
      await tester.pumpWidget(
        _screen(
          Builder(
            builder: (c) {
              got = c.flavour;
              return const SizedBox();
            },
          ),
          stage: stage,
        ),
      );
      // The theme moves to the new look over a moment.
      await tester.pumpAndSettle();
      expect(got, want);
    }
  });
}
