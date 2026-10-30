import 'package:academy_app/app/theme/app_theme.dart';
import 'package:academy_app/app/theme/branding.dart';
import 'package:academy_app/app/theme/stage.dart';
import 'package:academy_app/core/l10n/l10n.dart';
import 'package:academy_app/features/classroom/data/picture_in_picture.dart';
import 'package:academy_app/features/classroom/domain/room_stage.dart';
import 'package:academy_app/features/classroom/presentation/widgets/room/room_controls.dart';
import 'package:academy_app/features/classroom/presentation/widgets/room/room_view.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

const _me = RoomPerson(identity: 'me', name: 'سلمى', isLocal: true, camera: true, micOn: true);
const _teacher = RoomPerson(identity: 't', name: 'أ. خالد', camera: true, micOn: true);
const _sharing = RoomPerson(identity: 't', name: 'أ. خالد', camera: true, screen: true);

RoomPerson _other(String id, {bool speaking = false}) => RoomPerson(identity: id, name: 'طالب $id', camera: true, speaking: speaking);

/// A picture for a tile with video: a plain box tests can find.
Widget? _video(Tile tile) => tile.hasVideo ? ColoredBox(key: ValueKey('video-${tile.key}'), color: const Color(0xFF334455)) : null;

class _Taps {
  final heard = <String>[];
  RoomActions get actions => RoomActions(
    onMic: () => heard.add('mic'),
    onCam: () => heard.add('cam'),
    onFlip: () => heard.add('flip'),
    onAudioOnly: () => heard.add('audio'),
    onRecord: () => heard.add('record'),
    onPanel: () => heard.add('panel'),
    onLeave: () => heard.add('leave'),
  );
}

Widget _room(
  List<RoomPerson> people, {
  _Taps? taps,
  RoomControlsState controls = const RoomControlsState(ready: true, micOn: true, camOn: true),
  bool recording = false,
  bool pip = false,
  bool calm = false,
  Widget? panel,
}) => MaterialApp(
  theme: buildTheme(Branding.fallback, Stage.classic),
  locale: const Locale('ar'),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  builder: (context, child) => MediaQuery(
    data: MediaQuery.of(context).copyWith(disableAnimations: calm),
    child: child!,
  ),
  home: Scaffold(
    body: RoomView(
      people: people,
      video: _video,
      title: 'أ. خالد',
      elapsed: const Duration(minutes: 5, seconds: 7),
      recording: recording,
      quality: RoomQuality.good,
      controls: controls,
      actions: (taps ?? _Taps()).actions,
      pip: pip,
      panel: panel,
    ),
  ),
);

Future<void> _show(WidgetTester tester, Widget room, {Size size = const Size(420, 860)}) async {
  await tester.binding.setSurfaceSize(size);
  addTearDown(() => tester.binding.setSurfaceSize(null));
  await tester.pumpWidget(room);
  await tester.pump(const Duration(milliseconds: 600));
}

void main() {
  group('pickStage', () {
    test('a shared screen takes the stage whole, the presenter on it and me floating, never their camera instead', () {
      final plan = pickStage([_me, _sharing]);
      expect(plan.kind, StageKind.spotlight);
      expect(plan.main, const Tile(_sharing, FeedSource.screen));
      expect(plan.main!.fit, TileFit.contain);
      expect(plan.overlay, const Tile(_sharing, FeedSource.camera));
      expect(plan.self, const Tile(_me, FeedSource.camera));
      expect(plan.strip, isEmpty);
    });

    test('with more people the share keeps the stage and the other cameras go in the strip', () {
      final plan = pickStage([_me, _sharing, _other('a')]);
      expect(plan.main?.key, 't:screen');
      expect(plan.overlay?.key, 't:camera');
      expect(plan.strip.map((t) => t.key), ['a:camera', 'me:camera']);
      expect(plan.self, isNull);
    });

    test('one other person: them large (filling), me floating; nobody yet: me', () {
      final pair = pickStage([_me, _teacher]);
      expect(pair.kind, StageKind.pair);
      expect(pair.main?.key, 't:camera');
      expect(pair.main?.fit, TileFit.cover);
      expect(pair.self?.key, 'me:camera');

      final alone = pickStage([_me]);
      expect(alone.kind, StageKind.alone);
      expect(alone.main?.key, 'me:camera');
      expect(pickStage(const []).kind, StageKind.alone);
    });

    test('three others: everyone in a grid, the others first', () {
      final plan = pickStage([_me, _other('a'), _other('b'), _other('c')]);
      expect(plan.kind, StageKind.grid);
      expect(plan.grid.map((t) => t.key), ['a:camera', 'b:camera', 'c:camera', 'me:camera']);
    });

    test('a pinned tile goes large and the rest to the strip; a pin on someone who left falls away', () {
      final people = [_me, _other('a'), _other('b'), _other('c')];
      final plan = pickStage(people, pinned: 'b:camera');
      expect(plan.kind, StageKind.spotlight);
      expect(plan.main?.key, 'b:camera');
      expect(plan.pinned, 'b:camera');
      expect(plan.strip.map((t) => t.key), ['a:camera', 'c:camera', 'me:camera']);

      // Pinned over a share: the screen waits in the strip.
      final over = pickStage([_me, _sharing, _other('a')], pinned: 'a:camera');
      expect(over.main?.key, 'a:camera');
      expect(over.strip.map((t) => t.key), contains('t:screen'));

      final gone = pickStage([_me, _other('a'), _other('c')], pinned: 'b:camera');
      expect(gone.pinned, isNull);
      expect(gone.kind, StageKind.grid);
    });

    test('the layout chosen: a grid holds the screen too, the spotlight puts the first other large', () {
      final grid = pickStage([_me, _sharing], layout: RoomLayout.grid);
      expect(grid.kind, StageKind.grid);
      expect(grid.grid.map((t) => t.key), ['t:screen', 't:camera', 'me:camera']);

      final spot = pickStage([_me, _other('a'), _other('b')], layout: RoomLayout.spotlight);
      expect(spot.kind, StageKind.spotlight);
      expect(spot.main?.key, 'a:camera');
      expect(spot.strip.map((t) => t.key), ['b:camera', 'me:camera']);

      expect(nextLayout(RoomLayout.auto), RoomLayout.grid);
      expect(nextLayout(RoomLayout.spotlight), RoomLayout.auto);
    });

    test('a camera that is off still has its tile (the initial); grid columns and the clock', () {
      const dark = RoomPerson(identity: 'x', name: 'x');
      expect(const Tile(dark, FeedSource.camera).hasVideo, isFalse);
      expect(gridColumns(1, 400, 700), 1);
      expect(gridColumns(4, 400, 700), 2);
      expect(gridColumns(3, 900, 400), 3);
      expect(roomClock(const Duration(minutes: 5, seconds: 7)), '05:07');
      expect(roomClock(const Duration(hours: 1, minutes: 2, seconds: 3)), '1:02:03');
    });
  });

  group('the room on screen', () {
    testWidgets('a shared screen shows whole and can be zoomed, its presenter small on it, me floating', (tester) async {
      await _show(tester, _room([_me, _sharing], recording: true));

      expect(find.byKey(const Key('room-screen')), findsOneWidget);
      expect(find.descendant(of: find.byType(InteractiveViewer), matching: find.byKey(const ValueKey('video-t:screen'))), findsOneWidget);
      expect(find.byKey(const ValueKey('overlay-t:camera')), findsOneWidget);
      expect(find.byKey(const Key('self-view')), findsOneWidget);
      expect(find.text('شاشة أ. خالد'), findsOneWidget);
      // The presenter's microphone is off: the badge on their camera.
      expect(find.byKey(const Key('tile-muted')), findsOneWidget);
      expect(find.byKey(const Key('recording-on')), findsOneWidget);
      expect(find.text('05:07'), findsOneWidget);
      expect(find.byKey(const Key('quality-good')), findsOneWidget);
    });

    testWidgets('three others in a grid; whoever speaks has the ring; a tap pins and a second lets go', (tester) async {
      await _show(tester, _room([_me, _other('a'), _other('b', speaking: true), _other('c')]));
      expect(find.byKey(const Key('room-grid')), findsOneWidget);
      expect(find.byKey(const Key('speaking')), findsOneWidget);
      expect(find.descendant(of: find.byKey(const ValueKey('tile-b:camera')), matching: find.byKey(const Key('speaking'))), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('tile-c:camera')));
      await tester.pump(const Duration(milliseconds: 600));
      expect(find.byKey(const Key('room-grid')), findsNothing);
      expect(find.byKey(const Key('room-strip')), findsOneWidget);
      expect(find.byIcon(Icons.push_pin_rounded), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('tile-c:camera')).first);
      await tester.pump(const Duration(milliseconds: 600));
      expect(find.byKey(const Key('room-grid')), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 400));
    });

    testWidgets('the bar: round buttons with their names, leave in red, more options when the phone is narrow', (tester) async {
      final taps = _Taps();
      await _show(
        tester,
        _room(
          [_me, _teacher],
          taps: taps,
          controls: const RoomControlsState(
            ready: true,
            micOn: true,
            camOn: true,
            canFlip: true,
            recordControls: true,
            recordMissing: ['سلمى'],
          ),
        ),
        size: const Size(360, 760),
      );

      expect(find.byTooltip('أغلق الميكروفون'), findsOneWidget);
      expect(find.byTooltip('أغلق الكاميرا'), findsOneWidget);
      expect(find.byTooltip('بدّل الكاميرا'), findsOneWidget);
      expect(find.byTooltip('اخرج من الحصة'), findsOneWidget);
      await tester.tap(find.byKey(const Key('room-mic')));
      await tester.tap(find.byKey(const Key('room-leave')));
      expect(taps.heard, ['mic', 'leave']);

      // No room for all: audio only, the layout and recording are a tap further.
      expect(find.byKey(const Key('record')), findsNothing);
      await tester.tap(find.byKey(const Key('room-more')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));
      expect(find.text('الصوت فقط'), findsOneWidget);
      expect(find.text('لا يمكن التسجيل حتى يوافقوا على التسجيل: سلمى.'), findsOneWidget);
      // Nobody agreed yet: recording cannot start.
      expect(tester.widget<ListTile>(find.byKey(const Key('record-option'))).enabled, isFalse);

      await tester.tap(find.byKey(const Key('room-layout-grid')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));
      expect(find.byKey(const Key('room-grid')), findsOneWidget);
    });

    testWidgets('a wide bar holds every button, a muted microphone in red', (tester) async {
      final taps = _Taps();
      await _show(
        tester,
        _room([_me, _teacher], taps: taps, controls: const RoomControlsState(ready: true, camOn: true, recordControls: true)),
        size: const Size(700, 900),
      );
      expect(find.byKey(const Key('room-more')), findsNothing);
      expect(find.byTooltip('افتح الميكروفون'), findsOneWidget);
      await tester.tap(find.byKey(const Key('record')));
      await tester.tap(find.byKey(const Key('room-audio-only')));
      expect(taps.heard, ['record', 'audio']);
    });

    testWidgets('sideways the bars step aside after four quiet seconds, and a tap brings them back', (tester) async {
      await _show(tester, _room([_me, _sharing]), size: const Size(900, 420));
      expect(find.byKey(const Key('room-mic')).hitTestable(), findsOneWidget);

      await tester.pump(RoomView.barsLinger + const Duration(milliseconds: 300));
      expect(find.byKey(const Key('room-mic')).hitTestable(), findsNothing);
      expect(find.byKey(const Key('room-wake')), findsOneWidget);

      await tester.tap(find.byKey(const Key('room-wake')));
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.byKey(const Key('room-mic')).hitTestable(), findsOneWidget);
      // The first tap only woke the bars: nothing was pinned.
      expect(find.byIcon(Icons.push_pin_rounded), findsNothing);
    });

    testWidgets('in picture-in-picture only the main picture shows', (tester) async {
      await _show(tester, _room([_me, _sharing], pip: true, recording: true));
      expect(find.byKey(const Key('room-pip')), findsOneWidget);
      expect(find.byKey(const ValueKey('video-t:screen')), findsOneWidget);
      expect(find.byKey(const Key('room-mic')), findsNothing);
      expect(find.byKey(const Key('recording-on')), findsNothing);
      expect(find.byKey(const Key('self-view')), findsNothing);
    });

    testWidgets('the mushaf takes the stage with the main picture small on it; a tap on it goes back', (tester) async {
      final taps = _Taps();
      await _show(
        tester,
        _room(
          [_me, _teacher],
          taps: taps,
          panel: const Center(child: Text('صفحة المصحف')),
          controls: const RoomControlsState(ready: true, micOn: true, camOn: true, hasPanel: true, panelOpen: true),
        ),
      );
      expect(find.byKey(const Key('classroom-mushaf')), findsOneWidget);
      expect(find.text('صفحة المصحف'), findsOneWidget);
      await tester.tap(find.byKey(const Key('mushaf-pip')));
      expect(taps.heard, ['panel']);
    });

    testWidgets('with less motion asked for, everything stands still (the recording dot too)', (tester) async {
      await tester.binding.setSurfaceSize(const Size(420, 860));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(_room([_me, _other('a'), _other('b')], recording: true, calm: true));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('recording-on')), findsOneWidget);
      expect(tester.binding.hasScheduledFrame, isFalse);
    });
  });

  group('PictureInPicture', () {
    const channel = MethodChannel('lms/pip');
    final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;

    tearDown(() => messenger.setMockMethodCallHandler(channel, null));

    Future<void> fromAndroid(MethodCall call) =>
        messenger.handlePlatformMessage(channel.name, channel.codec.encodeMethodCall(call), (_) {});

    test('the room turns it on and off, and hears when the window is small', () async {
      final calls = <MethodCall>[];
      messenger.setMockMethodCallHandler(channel, (call) async {
        calls.add(call);
        return true;
      });
      final pip = PictureInPicture(supported: true);

      await pip.setEnabled(true);
      expect(calls.single.method, 'setEnabled');
      expect(calls.single.arguments, true);

      await fromAndroid(const MethodCall('changed', true));
      expect(pip.active.value, isTrue);
      await fromAndroid(const MethodCall('changed', false));
      expect(pip.active.value, isFalse);
      await fromAndroid(const MethodCall('changed', true));

      await pip.setEnabled(false);
      expect(calls.last.arguments, false);
      expect(pip.active.value, isFalse, reason: 'leaving the room ends the small window');
      // Not listening any more.
      await fromAndroid(const MethodCall('changed', true));
      expect(pip.active.value, isFalse);
      pip.dispose();
    });

    test('nothing is asked where there is none (iOS, the web), and a build without the channel goes on', () async {
      final calls = <MethodCall>[];
      messenger.setMockMethodCallHandler(channel, (call) async => calls.add(call));
      final off = PictureInPicture(supported: false);
      await off.setEnabled(true);
      expect(calls, isEmpty);
      off.dispose();

      messenger.setMockMethodCallHandler(channel, null);
      final missing = PictureInPicture(supported: true);
      await missing.setEnabled(true);
      await missing.setEnabled(false);
      expect(missing.active.value, isFalse);
      missing.dispose();
    });
  });
}
