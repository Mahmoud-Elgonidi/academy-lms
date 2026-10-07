import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/theme/app_theme.dart';
import '../../../../core/auth/session_cubit.dart';
import '../../../../core/l10n/l10n.dart';
import '../../../../core/state/load_cubit.dart';
import '../../../../shared/motion/motion.dart';
import '../../../../shared/widgets/lms.dart';
import '../../../../shared/widgets/load_view.dart';
import '../../../challenges/presentation/widgets/teasers.dart';
import '../../data/home_loader.dart';
import '../../domain/journey.dart';
import '../widgets/next_lesson_card.dart';
import '../../../notifications/presentation/widgets/notification_bell.dart';

/// A child's home: their stars, the next lesson, and the journey through
/// their course as a winding path of stops, with "you're here".
class KidsHomePage extends StatelessWidget {
  const KidsHomePage({super.key});

  @override
  Widget build(BuildContext context) => BlocProvider(
    create: (c) => LoadCubit<StudentHome>(c.read<HomeLoader>().studentHome)..load(),
    child: Scaffold(
      body: SafeArea(
        child: LoadView<StudentHome>(builder: (context, home) => _KidsHome(home: home)),
      ),
    ),
  );
}

class _KidsHome extends StatelessWidget {
  const _KidsHome({required this.home});

  final StudentHome home;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final c = context.colors;
    final name = context.select<SessionCubit, String>((s) => s.state.current?.userName ?? '');
    final course = home.journey.courses.firstOrNull;
    final steps = course?.worlds.expand((w) => w.lessons).toList() ?? const <MapStep>[];
    final done = steps.where((s) => s.done).length;
    final next = course?.next.firstOrNull;
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
      physics: const AlwaysScrollableScrollPhysics(),
      children: staggered(context, [
        Row(
          children: [
            GestureDetector(onTap: () => context.push('/account'), child: InitialAvatar(name, size: 54)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l.kidsHello, style: context.text.bodyLarge?.copyWith(color: c.muted)),
                  Text(name.split(' ').first, style: context.text.headlineMedium),
                ],
              ),
            ),
            const NotificationBell(),
            Semantics(
              label: '${home.journey.stars} ${l.stars}',
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: c.rewardSoft, borderRadius: BorderRadius.circular(999)),
                child: Row(
                  children: [
                    Icon(Icons.star_rounded, color: c.reward, size: 26),
                    const SizedBox(width: 4),
                    CountUp(home.journey.stars, style: context.text.titleLarge),
                  ],
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        NextLessonCard(lesson: home.next, onOpened: () => context.read<LoadCubit<StudentHome>>().load()),
        const SizedBox(height: 14),
        const ChallengesTeaser(route: '/kids/missions'),
        const SizedBox(height: 14),
        if (course != null)
          LmsCard(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l.kidsJourney(course.program ?? course.subject), style: context.text.titleLarge),
                const SizedBox(height: 4),
                Text(
                  [l.kidsStations(done), if (next != null) l.kidsNext(next.title)].join(' '),
                  style: context.text.bodyMedium?.copyWith(color: c.muted),
                ),
                const SizedBox(height: 8),
                JourneyMap(steps: steps, name: name),
              ],
            ),
          ),
      ]),
    );
  }
}

/// The course as a path: done stops with a star, the current one bouncing
/// with the child's initial, the rest waiting. Shows the stops around where
/// the child is.
class JourneyMap extends StatelessWidget {
  const JourneyMap({required this.steps, required this.name, super.key});

  final List<MapStep> steps;
  final String name;

  static const _row = 92.0;
  static const _xs = [0.5, 0.8, 0.5, 0.2];

  @override
  Widget build(BuildContext context) {
    if (steps.isEmpty) return const SizedBox.shrink();
    final here = math.max(0, steps.indexWhere((s) => s.state == 'here'));
    final start = math.max(0, here - 3);
    final shown = steps.sublist(start, math.min(steps.length, start + 7));
    final c = context.colors;
    return LayoutBuilder(
      builder: (context, box) {
        final w = box.maxWidth;
        final points = [for (var i = 0; i < shown.length; i++) Offset(w * _xs[(start + i) % _xs.length], _row * i + _row / 2)];
        return SizedBox(
          height: _row * shown.length,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned.fill(
                child: TweenAnimationBuilder<double>(
                  tween: Tween(begin: context.calm ? 1 : 0, end: 1),
                  duration: Duration(milliseconds: 160 * shown.length),
                  curve: Curves.easeInOutCubic,
                  builder: (context, drawn, _) =>
                      CustomPaint(painter: _PathPainter(points, c.brand, c.line, shown.where((s) => s.done).length, drawn)),
                ),
              ),
              for (var i = 0; i < shown.length; i++)
                Positioned(
                  left: points[i].dx - 70,
                  top: points[i].dy - 30,
                  width: 140,
                  child: _Stop(step: shown[i], number: start + i + 1, name: name).appear(context, i + 1, rise: 0.4),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _Stop extends StatelessWidget {
  const _Stop({required this.step, required this.number, required this.name});

  final MapStep step;
  final int number;
  final String name;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final here = step.state == 'here';
    final size = here ? 60.0 : 48.0;
    final Widget dot = Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: here ? c.reward : (step.done ? c.brand : c.card),
        border: Border.all(color: here ? Colors.white : (step.done ? c.brand : c.line), width: here ? 4 : 3),
        boxShadow: [BoxShadow(color: (here ? c.reward : c.line).withValues(alpha: 0.5), offset: const Offset(0, 4))],
      ),
      child: here
          ? Text(
              name.characters.firstOrNull ?? '',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24),
            )
          : step.done
          ? const Icon(Icons.star_rounded, color: Colors.white, size: 26)
          : Text(
              '$number',
              style: TextStyle(color: c.muted, fontWeight: FontWeight.w800, fontSize: 18),
            ),
    );
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (here) _Bounce(child: dot) else dot,
        const SizedBox(height: 3),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
          decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(999)),
          child: Text(
            here ? context.l10n.youAreHere : step.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: here ? 14 : 12.5,
              fontWeight: here ? FontWeight.w800 : FontWeight.w600,
              color: here ? c.ink : (step.done ? c.brandDeep : c.muted),
            ),
          ),
        ),
      ],
    );
  }
}

class _Bounce extends StatefulWidget {
  const _Bounce({required this.child});

  final Widget child;

  @override
  State<_Bounce> createState() => _BounceState();
}

class _BounceState extends State<_Bounce> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (context, child) => Transform.translate(offset: Offset(0, -5 * Curves.easeInOut.transform(_c.value)), child: child),
    child: widget.child,
  );
}

/// The road between stops: solid in the brand colour up to the last done
/// stop, dashed after.
class _PathPainter extends CustomPainter {
  _PathPainter(this.points, this.done, this.ahead, this.doneCount, this.drawn);

  final List<Offset> points;
  final Color done;
  final Color ahead;
  final int doneCount;

  /// How much of the road is drawn yet (0..1), as it draws itself in.
  final double drawn;

  @override
  void paint(Canvas canvas, Size size) {
    if (points.length < 2) return;
    final reach = drawn * (points.length - 1);
    for (var i = 0; i < points.length - 1 && i < reach; i++) {
      final a = points[i];
      final b = points[i + 1];
      final whole = Path()
        ..moveTo(a.dx, a.dy)
        ..cubicTo(a.dx, (a.dy + b.dy) / 2, b.dx, (a.dy + b.dy) / 2, b.dx, b.dy);
      final part = math.min(1.0, reach - i);
      final path = part >= 1 ? whole : _partOf(whole, part);
      final walked = i < doneCount;
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 7
        ..strokeCap = StrokeCap.round
        ..color = walked ? done.withValues(alpha: 0.55) : ahead;
      if (walked) {
        canvas.drawPath(path, paint);
      } else {
        for (final metric in path.computeMetrics()) {
          for (var d = 0.0; d < metric.length; d += 18) {
            canvas.drawPath(metric.extractPath(d, math.min(d + 9, metric.length)), paint);
          }
        }
      }
    }
  }

  static Path _partOf(Path path, double share) {
    final out = Path();
    for (final metric in path.computeMetrics()) {
      out.addPath(metric.extractPath(0, metric.length * share), Offset.zero);
    }
    return out;
  }

  @override
  bool shouldRepaint(_PathPainter old) => old.points != points || old.doneCount != doneCount || old.done != done || old.drawn != drawn;
}
